<?php

namespace App\Services;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\InspectionTask;
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
                UserRole::STORE,
                $application->created_by_user_id,
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
}
