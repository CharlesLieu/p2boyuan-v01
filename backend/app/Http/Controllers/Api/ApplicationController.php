<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignSalesRequest;
use App\Http\Requests\ApplicationStoreRequest;
use App\Http\Requests\ReviewDecisionRequest;
use App\Http\Requests\ReviewSupplementRequest;
use App\Http\Requests\SupplementSubmitRequest;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\InspectionTask;
use App\Models\PayoutRecord;
use App\Models\ReviewRecord;
use App\Models\SalesAgent;
use App\Models\User;
use App\Services\ApplicationNumberService;
use App\Services\ApplicationStateService;
use App\Services\StatusLogService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationStateService $stateService,
        private readonly ApplicationNumberService $applicationNumberService,
        private readonly StatusLogService $statusLogService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        if ($this->roleValue($request->user()) === UserRole::STORE->value) {
            return $this->forbidden($request);
        }

        $applications = $this->visibleApplications($request->user())
            ->with(['store', 'createdBy'])
            ->latest()
            ->limit((int) min(max($request->integer('limit', 50), 1), 100))
            ->get()
            ->map(fn (Application $application) => $this->serializeApplication($application))
            ->values();

        return $this->success($request, [
            'items' => $applications,
        ]);
    }

    public function show(Request $request, string $applicationId): JsonResponse
    {
        if ($this->roleValue($request->user()) === UserRole::STORE->value) {
            return $this->forbidden($request);
        }

        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        $application->load(['store', 'createdBy', 'inspectionTasks.salesAgent', 'payoutRecords']);

        return $this->success($request, [
            'application' => $this->serializeApplication($application, includeDetail: true),
        ]);
    }

    public function store(ApplicationStoreRequest $request): JsonResponse
    {
        $user = $request->user();
        $role = $this->roleValue($user);
        $validated = $request->validated();

        if ($role !== UserRole::SUPER_ADMIN->value) {
            return $this->forbidden($request);
        }

        $storeId = $validated['storeId'] ?? null;

        if (! $storeId) {
            return $this->error($request, 'VALIDATION_ERROR', '请选择申请所属门店。', 422);
        }

        $application = $this->createApplicationWithRetry($validated, $user, $storeId);

        if (! $application) {
            return $this->error($request, 'APPLICATION_NO_GENERATION_FAILED', '申请编号生成失败，请稍后重试。', 409);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($application),
        ], '申请已提交，等待后台审核员处理。', 201);
    }

    public function logs(Request $request, string $applicationId): JsonResponse
    {
        if ($this->roleValue($request->user()) === UserRole::STORE->value) {
            return $this->forbidden($request);
        }

        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        $logs = $application->statusLogs()
            ->with('actor')
            ->oldest()
            ->get()
            ->map(fn (StatusLog $log) => $this->serializeLog($log))
            ->values();

        return $this->success($request, [
            'items' => $logs,
        ]);
    }

    public function assign(AssignSalesRequest $request, string $applicationId): JsonResponse
    {
        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        $validated = $request->validated();
        $salesAgent = SalesAgent::query()->findOrFail($validated['salesAgentId']);

        try {
            $result = $this->stateService->assign(
                $application,
                $salesAgent,
                $request->user(),
                $validated['remark'] ?? null,
            );
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'inspectionTask' => $this->serializeInspectionTask($result['task']),
        ], '已指派业务员到店验机。');
    }

    public function approve(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        $validator = Validator::make($request->all(), ReviewDecisionRequest::rulesDefinition());

        if ($validator->fails()) {
            return $this->validationError($request, '审核意见格式不正确。', $validator->errors()->toArray());
        }

        try {
            $result = $this->stateService->approve($application, $request->user(), $validator->validated()['note'] ?? null);
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'reviewRecord' => $this->serializeReviewRecord($result['reviewRecord']),
            'payoutRecord' => $this->serializePayoutRecord($result['payoutRecord']),
        ], '审核已通过，待出纳打款。');
    }

    public function reject(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        $validator = Validator::make($request->all(), [
            'note' => ['required', 'string', 'max:4000'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($request, '驳回原因不能为空或格式不正确。', $validator->errors()->toArray());
        }

        try {
            $result = $this->stateService->rejectReview($application, $request->user(), $validator->validated()['note']);
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'reviewRecord' => $this->serializeReviewRecord($result['reviewRecord']),
        ], '申请已驳回。');
    }

    public function requestSupplement(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        $validator = Validator::make($request->all(), ReviewSupplementRequest::rulesDefinition());

        if ($validator->fails()) {
            return $this->validationError($request, '补资料要求填写不完整或格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        try {
            $result = $this->stateService->requestSupplement(
                $application,
                $request->user(),
                UserRole::from($validated['ownerRole']),
                $validated['note'],
            );
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'reviewRecord' => $this->serializeReviewRecord($result['reviewRecord']),
        ], '已要求补充资料。');
    }

    public function submitSupplement(Request $request, string $applicationId): JsonResponse
    {
        $application = $this->findVisibleApplication($request, $applicationId);

        if (! $application) {
            return $this->notFound($request);
        }

        if (! $this->canSubmitSupplement($request->user(), $application)) {
            return $this->forbidden($request);
        }

        $validator = Validator::make($request->all(), SupplementSubmitRequest::rulesDefinition());

        if ($validator->fails()) {
            return $this->validationError($request, '补充资料填写不完整或格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        try {
            $result = $this->stateService->submitSupplement(
                $application,
                $request->user(),
                $validated['note'],
                $validated['attachments'] ?? [],
            );
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'attachments' => collect($result['attachments'])
                ->map(fn (Attachment $attachment) => $this->serializeAttachment($attachment))
                ->values(),
        ], '补充资料已提交，等待后台复审。');
    }

    private function visibleApplications(User $user): Builder
    {
        $role = $this->roleValue($user);

        return Application::query()
            ->when($role === UserRole::STORE->value, fn (Builder $query) => $query->where('store_id', $user->store_id))
            ->when($role === UserRole::SALES->value, function (Builder $query) use ($user): void {
                $query->whereHas('inspectionTasks', fn (Builder $taskQuery) => $taskQuery->where('sales_agent_id', $user->sales_agent_id));
            })
            ->when($role === UserRole::CASHIER->value, function (Builder $query): void {
                $query->whereIn('status', [
                    ApplicationStatus::PENDING_PAYOUT->value,
                    ApplicationStatus::PAID->value,
                    ApplicationStatus::COMPLETED->value,
                ]);
            });
    }

    private function findVisibleApplication(Request $request, string $applicationId): ?Application
    {
        return $this->visibleApplications($request->user())
            ->whereKey($applicationId)
            ->first();
    }

    private function canSubmitSupplement(User $user, Application $application): bool
    {
        $role = $this->roleValue($user);

        if ($role === UserRole::SUPER_ADMIN->value) {
            return true;
        }

        if (($application->status?->value ?? $application->status) !== ApplicationStatus::NEEDS_SUPPLEMENT->value) {
            return true;
        }

        if ($application->current_owner_role === UserRole::SALES->value) {
            return $role === UserRole::SALES->value
                && $application->current_owner_user_id === $user->id;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplication(Application $application, bool $includeDetail = false): array
    {
        $data = [
            'id' => $application->id,
            'applicationNo' => $application->application_no,
            'sourceType' => $application->source_type,
            'storeId' => $application->store_id,
            'storeName' => $application->store?->name,
            'createdByUserId' => $application->created_by_user_id,
            'currentOwnerRole' => $application->current_owner_role,
            'currentOwnerUserId' => $application->current_owner_user_id,
            'status' => $application->status?->value ?? $application->status,
            'customerName' => $application->customer_name,
            'customerPhone' => $application->customer_phone,
            'idType' => $application->id_type,
            'idNumber' => $application->id_number,
            'customerAddress' => $application->customer_address,
            'brand' => $application->brand,
            'model' => $application->model,
            'color' => $application->color,
            'capacity' => $application->capacity,
            'imei' => $application->imei,
            'deviceCondition' => $application->device_condition,
            'salePrice' => (float) $application->sale_price,
            'loanAmount' => (float) $application->loan_amount,
            'periods' => $application->periods,
            'remark' => $application->remark,
            'createdAt' => $application->created_at?->toISOString(),
            'updatedAt' => $application->updated_at?->toISOString(),
        ];

        if ($includeDetail) {
            $data['inspectionTasks'] = $application->inspectionTasks
                ->map(fn ($task) => [
                    'id' => $task->id,
                    'salesAgentId' => $task->sales_agent_id,
                    'salesAgentName' => $task->salesAgent?->name,
                    'status' => $task->status,
                    'inspectionNote' => $task->inspection_note,
                    'startedAt' => $task->started_at?->toISOString(),
                    'submittedAt' => $task->submitted_at?->toISOString(),
                ])
                ->values();
            $data['payoutRecords'] = $application->payoutRecords
                ->map(fn ($record) => [
                    'id' => $record->id,
                    'amount' => (float) $record->amount,
                    'status' => $record->status,
                    'paidAt' => $record->paid_at?->toISOString(),
                    'remark' => $record->remark,
                ])
                ->values();
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLog(StatusLog $log): array
    {
        return [
            'id' => $log->id,
            'applicationId' => $log->application_id,
            'actorUserId' => $log->actor_user_id,
            'actorName' => $log->actor?->display_name,
            'actorRole' => $log->actor_role,
            'fromStatus' => $log->from_status,
            'toStatus' => $log->to_status,
            'message' => $log->message,
            'action' => $log->metadata['action'] ?? null,
            'metadata' => $log->metadata,
            'createdAt' => $log->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInspectionTask(InspectionTask $task): array
    {
        return [
            'id' => $task->id,
            'applicationId' => $task->application_id,
            'salesAgentId' => $task->sales_agent_id,
            'salesAgentName' => $task->salesAgent?->name,
            'status' => $task->status,
            'inspectionNote' => $task->inspection_note,
            'startedAt' => $task->started_at?->toISOString(),
            'submittedAt' => $task->submitted_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReviewRecord(ReviewRecord $record): array
    {
        return [
            'id' => $record->id,
            'applicationId' => $record->application_id,
            'reviewerUserId' => $record->reviewer_user_id,
            'reviewerName' => $record->reviewer?->display_name,
            'action' => $record->action,
            'fromStatus' => $record->from_status,
            'toStatus' => $record->to_status,
            'note' => $record->note,
            'createdAt' => $record->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayoutRecord(PayoutRecord $record): array
    {
        return [
            'id' => $record->id,
            'applicationId' => $record->application_id,
            'amount' => (float) $record->amount,
            'status' => $record->status,
            'cashierUserId' => $record->cashier_user_id,
            'voucherAttachmentId' => $record->voucher_attachment_id,
            'paidAt' => $record->paid_at?->toISOString(),
            'remark' => $record->remark,
            'createdAt' => $record->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttachment(Attachment $attachment): array
    {
        return [
            'id' => $attachment->id,
            'applicationId' => $attachment->application_id,
            'module' => $attachment->module,
            'fileName' => $attachment->file_name,
            'filePath' => $attachment->file_path,
            'mimeType' => $attachment->mime_type,
            'fileSize' => $attachment->file_size,
            'remark' => $attachment->remark,
            'createdAt' => $attachment->created_at?->toISOString(),
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function createApplicationWithRetry(array $validated, User $user, string $storeId): ?Application
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return DB::transaction(function () use ($validated, $user, $storeId): Application {
                    $application = Application::query()->create([
                        'application_no' => $this->applicationNumberService->next(),
                        'source_type' => 'ADMIN_CREATE',
                        'store_id' => $storeId,
                        'created_by_user_id' => $user->id,
                        'current_owner_role' => UserRole::AUDITOR->value,
                        'current_owner_user_id' => null,
                        'status' => ApplicationStatus::PENDING_ASSIGNMENT,
                        'customer_name' => $validated['customerName'],
                        'customer_phone' => $validated['customerPhone'],
                        'id_type' => $validated['idType'],
                        'id_number' => $validated['idNumber'],
                        'customer_address' => $validated['customerAddress'],
                        'brand' => $validated['brand'],
                        'model' => $validated['model'],
                        'color' => $validated['color'] ?? null,
                        'capacity' => $validated['capacity'] ?? null,
                        'imei' => $validated['imei'] ?? null,
                        'device_condition' => $validated['deviceCondition'] ?? null,
                        'sale_price' => $validated['salePrice'],
                        'loan_amount' => $validated['loanAmount'],
                        'periods' => $validated['periods'],
                        'remark' => $validated['remark'] ?? null,
                    ]);

                    $this->statusLogService->record(
                        $application,
                        $user,
                        'SUBMIT_APPLICATION',
                        null,
                        ApplicationStatus::PENDING_ASSIGNMENT,
                        '后台创建测试申请。',
                        ['source' => 'ApplicationController'],
                    );

                    return $application->fresh(['store', 'createdBy']);
                });
            } catch (UniqueConstraintViolationException $exception) {
                if (! Str::contains($exception->getMessage(), 'application_no')) {
                    throw $exception;
                }
            }
        }

        return null;
    }

    private function roleValue(User $user): ?string
    {
        return $user->role instanceof UserRole ? $user->role->value : $user->role;
    }

    private function success(Request $request, mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'requestId' => $this->requestId($request),
        ], $status);
    }

    private function error(Request $request, string $code, string $message, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
            'requestId' => $this->requestId($request),
        ], $status);
    }

    /**
     * @param array<string, mixed> $fields
     */
    private function validationError(Request $request, string $message, array $fields): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $message,
                'fields' => $fields,
            ],
            'requestId' => $this->requestId($request),
        ], 422);
    }

    private function forbidden(Request $request): JsonResponse
    {
        return $this->error($request, 'AUTH_FORBIDDEN', '当前账号没有访问该资源的权限。', 403);
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'APPLICATION_NOT_FOUND', '申请不存在或当前账号不可见。', 404);
    }

    private function invalidState(Request $request): JsonResponse
    {
        return $this->error($request, 'INVALID_STATE_TRANSITION', '当前状态不允许执行该操作。', 409);
    }

    private function requestId(Request $request): string
    {
        return $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
    }
}
