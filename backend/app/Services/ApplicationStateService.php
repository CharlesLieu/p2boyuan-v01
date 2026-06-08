<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\InspectionTask;
use App\Models\MerchantPaymentVoucher;
use App\Models\PayoutRecord;
use App\Models\ReviewRecord;
use App\Models\SalesAgent;
use App\Models\StatusLog;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class ApplicationStateService
{
    /**
     * @return array{application: Application, task: InspectionTask}
     */
    public function assign(Application $application, SalesAgent $salesAgent, User $actor, ?string $remark = null): array
    {
        return DB::transaction(function () use ($application, $salesAgent, $actor, $remark): array {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);
            $this->ensureApplicationStatus($application, ApplicationStatus::PENDING_ASSIGNMENT);

            $task = InspectionTask::query()->create([
                'application_id' => $application->id,
                'sales_agent_id' => $salesAgent->id,
                'assigned_by_user_id' => $actor->id,
                'status' => 'ASSIGNED',
            ]);

            $this->transitionApplication(
                $application,
                ApplicationStatus::ASSIGNED,
                UserRole::SALES,
                $this->firstActiveUserIdForSalesAgent($salesAgent),
            );

            $this->log($application, $actor, ApplicationStatus::PENDING_ASSIGNMENT, ApplicationStatus::ASSIGNED, '审核员指派业务员到店验机。', [
                'action' => 'ASSIGN_SALES',
                'salesAgentId' => $salesAgent->id,
                'remark' => $remark,
            ]);

            return [
                'application' => $application->fresh(['store', 'inspectionTasks.salesAgent']),
                'task' => $task->fresh(['salesAgent', 'application']),
            ];
        });
    }

    /**
     * @return array{application: Application, task: InspectionTask}
     */
    public function startInspection(InspectionTask $task, User $actor): array
    {
        return DB::transaction(function () use ($task, $actor): array {
            $task = InspectionTask::query()->with('application')->lockForUpdate()->findOrFail($task->id);
            $application = $task->application;

            $this->ensureTaskStatus($task, 'ASSIGNED');
            $this->ensureApplicationStatus($application, ApplicationStatus::ASSIGNED);

            $task->forceFill([
                'status' => 'IN_PROGRESS',
                'started_at' => now(),
            ])->save();

            $this->transitionApplication(
                $application,
                ApplicationStatus::INSPECTION_IN_PROGRESS,
                UserRole::SALES,
                $this->firstActiveUserIdForSalesAgent($task->salesAgent),
            );

            $this->log($application, $actor, ApplicationStatus::ASSIGNED, ApplicationStatus::INSPECTION_IN_PROGRESS, '业务员开始到店验机。', [
                'action' => 'START_INSPECTION',
                'inspectionTaskId' => $task->id,
            ]);

            return [
                'application' => $application->fresh(['store', 'inspectionTasks.salesAgent']),
                'task' => $task->fresh(['salesAgent', 'application']),
            ];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @return array{application: Application, task: InspectionTask, attachments: array<int, Attachment>}
     */
    public function submitInspection(InspectionTask $task, User $actor, string $result, string $note, array $attachments = []): array
    {
        return DB::transaction(function () use ($task, $actor, $result, $note, $attachments): array {
            $task = InspectionTask::query()->with('application')->lockForUpdate()->findOrFail($task->id);
            $application = $task->application;

            $this->ensureTaskStatus($task, 'IN_PROGRESS');
            $this->ensureApplicationStatus($application, ApplicationStatus::INSPECTION_IN_PROGRESS);

            $task->forceFill([
                'status' => 'SUBMITTED',
                'inspection_note' => $note,
                'submitted_at' => now(),
            ])->save();

            $createdAttachments = collect($attachments)
                ->map(fn (array $attachment) => Attachment::query()->create([
                    'application_id' => $application->id,
                    'uploaded_by_user_id' => $actor->id,
                    'module' => 'INSPECTION',
                    'file_name' => $attachment['fileName'],
                    'file_path' => $attachment['filePath'],
                    'mime_type' => $attachment['mimeType'] ?? null,
                    'file_size' => $attachment['fileSize'] ?? 0,
                    'remark' => $attachment['remark'] ?? null,
                ]))
                ->values()
                ->all();

            $this->transitionApplication($application, ApplicationStatus::PENDING_REVIEW, UserRole::AUDITOR);
            $this->log($application, $actor, ApplicationStatus::INSPECTION_IN_PROGRESS, ApplicationStatus::PENDING_REVIEW, '业务员提交验机结果，等待后台审核。', [
                'action' => 'SUBMIT_INSPECTION',
                'inspectionTaskId' => $task->id,
                'inspectionResult' => $result,
            ]);

            return [
                'application' => $application->fresh(['store', 'inspectionTasks.salesAgent']),
                'task' => $task->fresh(['salesAgent', 'application']),
                'attachments' => $createdAttachments,
            ];
        });
    }

    /**
     * @return array{application: Application, task: InspectionTask}
     */
    public function rejectInspection(InspectionTask $task, User $actor, string $reason): array
    {
        return DB::transaction(function () use ($task, $actor, $reason): array {
            $task = InspectionTask::query()->with('application')->lockForUpdate()->findOrFail($task->id);
            $application = $task->application;

            $this->ensureTaskStatus($task, 'IN_PROGRESS');
            $this->ensureApplicationStatus($application, ApplicationStatus::INSPECTION_IN_PROGRESS);

            $task->forceFill([
                'status' => 'REJECTED',
                'inspection_note' => $reason,
                'submitted_at' => now(),
            ])->save();

            $this->transitionApplication(
                $application,
                ApplicationStatus::NEEDS_SUPPLEMENT,
                UserRole::SALES,
                $this->firstActiveUserIdForSalesAgent($task->salesAgent),
            );

            $this->log($application, $actor, ApplicationStatus::INSPECTION_IN_PROGRESS, ApplicationStatus::NEEDS_SUPPLEMENT, '业务员退回验机任务，要求补充资料。', [
                'action' => 'REJECT_INSPECTION',
                'inspectionTaskId' => $task->id,
                'reason' => $reason,
            ]);

            return [
                'application' => $application->fresh(['store', 'inspectionTasks.salesAgent']),
                'task' => $task->fresh(['salesAgent', 'application']),
            ];
        });
    }

    /**
     * @return array{application: Application, reviewRecord: ReviewRecord, payoutRecord: PayoutRecord}
     */
    public function approve(Application $application, User $actor, ?string $note = null): array
    {
        return DB::transaction(function () use ($application, $actor, $note): array {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);
            $this->ensureApplicationStatus($application, ApplicationStatus::PENDING_REVIEW);

            $reviewRecord = $this->createReviewRecord(
                $application,
                $actor,
                'APPROVE',
                ApplicationStatus::PENDING_REVIEW,
                ApplicationStatus::PENDING_PAYOUT,
                $note,
            );

            $payoutRecord = PayoutRecord::query()->create([
                'application_id' => $application->id,
                'cashier_user_id' => null,
                'amount' => $application->loan_amount,
                'status' => 'PENDING',
                'remark' => '等待出纳上传凭证并确认。',
            ]);

            $this->transitionApplication($application, ApplicationStatus::PENDING_PAYOUT, UserRole::CASHIER);
            $this->log($application, $actor, ApplicationStatus::PENDING_REVIEW, ApplicationStatus::PENDING_PAYOUT, '后台审核通过，进入待放款。', [
                'action' => 'APPROVE_REVIEW',
                'reviewRecordId' => $reviewRecord->id,
                'payoutRecordId' => $payoutRecord->id,
                'note' => $note,
            ]);

            return [
                'application' => $application->fresh(['store', 'payoutRecords.voucherAttachment']),
                'reviewRecord' => $reviewRecord->fresh(['reviewer']),
                'payoutRecord' => $payoutRecord->fresh(['application', 'voucherAttachment']),
            ];
        });
    }

    /**
     * @return array{application: Application, reviewRecord: ReviewRecord}
     */
    public function rejectReview(Application $application, User $actor, string $note): array
    {
        return DB::transaction(function () use ($application, $actor, $note): array {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);
            $this->ensureApplicationStatus($application, ApplicationStatus::PENDING_REVIEW);

            $reviewRecord = $this->createReviewRecord(
                $application,
                $actor,
                'REJECT',
                ApplicationStatus::PENDING_REVIEW,
                ApplicationStatus::REJECTED,
                $note,
            );

            $this->transitionApplication($application, ApplicationStatus::REJECTED, UserRole::AUDITOR);
            $this->log($application, $actor, ApplicationStatus::PENDING_REVIEW, ApplicationStatus::REJECTED, '后台审核驳回申请。', [
                'action' => 'REJECT_REVIEW',
                'reviewRecordId' => $reviewRecord->id,
                'note' => $note,
            ]);

            return [
                'application' => $application->fresh(['store']),
                'reviewRecord' => $reviewRecord->fresh(['reviewer']),
            ];
        });
    }

    /**
     * @return array{application: Application, reviewRecord: ReviewRecord}
     */
    public function requestSupplement(Application $application, User $actor, UserRole $ownerRole, string $note): array
    {
        return DB::transaction(function () use ($application, $actor, $ownerRole, $note): array {
            $application = Application::query()
                ->with(['inspectionTasks.salesAgent'])
                ->lockForUpdate()
                ->findOrFail($application->id);
            $this->ensureApplicationStatus($application, ApplicationStatus::PENDING_REVIEW);

            $ownerUserId = $this->firstActiveUserIdForSalesAgent($application->inspectionTasks->sortByDesc('created_at')->first()?->salesAgent);

            $reviewRecord = $this->createReviewRecord(
                $application,
                $actor,
                'REQUEST_SUPPLEMENT',
                ApplicationStatus::PENDING_REVIEW,
                ApplicationStatus::NEEDS_SUPPLEMENT,
                $note,
            );

            $this->transitionApplication($application, ApplicationStatus::NEEDS_SUPPLEMENT, $ownerRole, $ownerUserId);
            $this->log($application, $actor, ApplicationStatus::PENDING_REVIEW, ApplicationStatus::NEEDS_SUPPLEMENT, '后台审核要求补充资料。', [
                'action' => 'REQUEST_SUPPLEMENT',
                'reviewRecordId' => $reviewRecord->id,
                'ownerRole' => $ownerRole->value,
                'note' => $note,
            ]);

            return [
                'application' => $application->fresh(['store']),
                'reviewRecord' => $reviewRecord->fresh(['reviewer']),
            ];
        });
    }

    /**
     * @param array<int, array<string, mixed>> $attachments
     * @return array{application: Application, attachments: array<int, Attachment>}
     */
    public function submitSupplement(Application $application, User $actor, string $note, array $attachments = []): array
    {
        return DB::transaction(function () use ($application, $actor, $note, $attachments): array {
            $application = Application::query()->lockForUpdate()->findOrFail($application->id);
            $this->ensureApplicationStatus($application, ApplicationStatus::NEEDS_SUPPLEMENT);

            $createdAttachments = collect($attachments)
                ->map(fn (array $attachment) => Attachment::query()->create([
                    'application_id' => $application->id,
                    'uploaded_by_user_id' => $actor->id,
                    'module' => 'SUPPLEMENT',
                    'file_name' => $attachment['fileName'],
                    'file_path' => $attachment['filePath'],
                    'mime_type' => $attachment['mimeType'] ?? null,
                    'file_size' => $attachment['fileSize'] ?? 0,
                    'remark' => $attachment['remark'] ?? null,
                ]))
                ->values()
                ->all();

            $this->transitionApplication($application, ApplicationStatus::PENDING_REVIEW, UserRole::AUDITOR);
            $this->log($application, $actor, ApplicationStatus::NEEDS_SUPPLEMENT, ApplicationStatus::PENDING_REVIEW, '补充资料已提交，回到后台审核。', [
                'action' => 'SUBMIT_SUPPLEMENT',
                'note' => $note,
                'attachmentCount' => count($createdAttachments),
            ]);

            return [
                'application' => $application->fresh(['store']),
                'attachments' => $createdAttachments,
            ];
        });
    }

    /**
     * @param array<string, mixed> $voucher
     * @return array{application: Application, payoutRecord: PayoutRecord, voucher: Attachment}
     */
    public function confirmPayout(PayoutRecord $payoutRecord, User $actor, float $amount, array $voucher, ?string $remark = null, mixed $paidAt = null): array
    {
        return DB::transaction(function () use ($payoutRecord, $actor, $amount, $voucher, $remark, $paidAt): array {
            $payoutRecord = PayoutRecord::query()->with('application')->lockForUpdate()->findOrFail($payoutRecord->id);
            $application = $payoutRecord->application;
            $application->loadMissing('store');

            $this->ensureApplicationStatus($application, ApplicationStatus::PENDING_PAYOUT);

            if ($payoutRecord->status !== 'PENDING') {
                throw new DomainException('当前打款记录状态不允许执行该操作。');
            }

            if ($amount > (float) $application->loan_amount) {
                throw new DomainException('打款金额不能超过申请贷款金额。');
            }

            $voucherAttachment = Attachment::query()->create([
                'application_id' => $application->id,
                'uploaded_by_user_id' => $actor->id,
                'module' => 'PAYOUT',
                'file_name' => $voucher['fileName'],
                'file_path' => $voucher['filePath'],
                'mime_type' => $voucher['mimeType'] ?? null,
                'file_size' => $voucher['fileSize'] ?? 0,
                'remark' => $voucher['remark'] ?? null,
            ]);

            $payoutRecord->forceFill([
                'cashier_user_id' => $actor->id,
                'amount' => $amount,
                'status' => 'PAID',
                'voucher_attachment_id' => $voucherAttachment->id,
                'paid_at' => $paidAt ?: now(),
                'remark' => $remark,
            ])->save();

            MerchantPaymentVoucher::query()->create([
                'voucher_no' => $this->nextVoucherNo(),
                'store_id' => $application->store_id,
                'payout_record_id' => $payoutRecord->id,
                'related_business_no' => $application->application_no,
                'amount' => $amount,
                'status' => 'PAID',
                'paid_at' => $payoutRecord->paid_at,
                'payee_name' => $application->store?->name ?? '商家',
                'payee_account_masked' => $this->maskAccount($application->store?->payment_account),
                'payer_name' => '博远财务',
                'voucher_file' => [
                    'fileName' => $voucher['fileName'],
                    'filePath' => $voucher['filePath'],
                    'mimeType' => $voucher['mimeType'] ?? null,
                    'fileSize' => $voucher['fileSize'] ?? 0,
                ],
                'remark' => $remark,
                'created_by_user_id' => $actor->id,
            ]);

            $this->transitionApplication($application, ApplicationStatus::PAID, UserRole::CASHIER, $actor->id);
            $this->log($application, $actor, ApplicationStatus::PENDING_PAYOUT, ApplicationStatus::PAID, '出纳确认打款并上传凭证。', [
                'action' => 'CONFIRM_PAYOUT',
                'payoutRecordId' => $payoutRecord->id,
                'voucherAttachmentId' => $voucherAttachment->id,
                'amount' => $amount,
                'remark' => $remark,
            ]);

            return [
                'application' => $application->fresh(['store']),
                'payoutRecord' => $payoutRecord->fresh(['application', 'voucherAttachment', 'cashier']),
                'voucher' => $voucherAttachment,
            ];
        });
    }

    private function ensureApplicationStatus(Application $application, ApplicationStatus $expected): void
    {
        $actual = $application->status instanceof ApplicationStatus ? $application->status : ApplicationStatus::from($application->status);

        if ($actual !== $expected) {
            throw new DomainException('当前申请状态不允许执行该操作。');
        }
    }

    private function ensureTaskStatus(InspectionTask $task, string $expected): void
    {
        if ($task->status !== $expected) {
            throw new DomainException('当前验机任务状态不允许执行该操作。');
        }
    }

    private function transitionApplication(Application $application, ApplicationStatus $status, UserRole $ownerRole, ?int $ownerUserId = null): void
    {
        $application->forceFill([
            'status' => $status,
            'current_owner_role' => $ownerRole->value,
            'current_owner_user_id' => $ownerUserId,
        ])->save();
    }

    private function createReviewRecord(Application $application, User $actor, string $action, ApplicationStatus $from, ApplicationStatus $to, ?string $note): ReviewRecord
    {
        return ReviewRecord::query()->create([
            'application_id' => $application->id,
            'reviewer_user_id' => $actor->id,
            'action' => $action,
            'from_status' => $from->value,
            'to_status' => $to->value,
            'note' => $note,
        ]);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function log(Application $application, User $actor, ?ApplicationStatus $from, ApplicationStatus $to, string $message, array $metadata): void
    {
        StatusLog::query()->create([
            'application_id' => $application->id,
            'actor_user_id' => $actor->id,
            'actor_role' => $actor->role instanceof UserRole ? $actor->role->value : $actor->role,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'message' => $message,
            'metadata' => $metadata,
        ]);
    }

    private function firstActiveUserIdForSalesAgent(?SalesAgent $salesAgent): ?int
    {
        if (! $salesAgent) {
            return null;
        }

        return User::query()
            ->where('sales_agent_id', $salesAgent->id)
            ->where('role', UserRole::SALES->value)
            ->where('status', 'ACTIVE')
            ->value('id');
    }

    private function nextVoucherNo(): string
    {
        return 'PV'.now()->format('YmdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
    }

    private function maskAccount(?string $account): string
    {
        if (! $account) {
            return '****';
        }

        if (mb_strlen($account) <= 8) {
            return str_repeat('*', mb_strlen($account));
        }

        return mb_substr($account, 0, 4).str_repeat('*', max(mb_strlen($account) - 8, 0)).mb_substr($account, -4);
    }
}
