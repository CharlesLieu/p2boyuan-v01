<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\InspectionSubmitRequest;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\InspectionTask;
use App\Models\User;
use App\Services\ApplicationStateService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class InspectionTaskController extends Controller
{
    public function __construct(private readonly ApplicationStateService $stateService)
    {
    }

    public function start(Request $request, string $inspectionTaskId): JsonResponse
    {
        $task = $this->findTaskForOperation($request->user(), $inspectionTaskId);

        if (! $task) {
            return $this->notFound($request);
        }

        if (! $this->canOperateTask($request->user(), $task)) {
            return $this->forbidden($request);
        }

        try {
            $result = $this->stateService->startInspection($task, $request->user());
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'inspectionTask' => $this->serializeInspectionTask($result['task']),
        ], '业务员已开始验机。');
    }

    public function submit(Request $request, string $inspectionTaskId): JsonResponse
    {
        $task = $this->findTaskForOperation($request->user(), $inspectionTaskId);

        if (! $task) {
            return $this->notFound($request);
        }

        if (! $this->canOperateTask($request->user(), $task)) {
            return $this->forbidden($request);
        }

        $validator = Validator::make($request->all(), InspectionSubmitRequest::rulesDefinition());

        if ($validator->fails()) {
            return $this->error($request, 'VALIDATION_ERROR', '验机提交资料填写不完整或格式不正确。', 422, [
                'fields' => $validator->errors()->toArray(),
            ]);
        }

        $validated = $validator->validated();

        try {
            $result = $this->stateService->submitInspection(
                $task,
                $request->user(),
                $validated['inspectionResult'],
                $validated['inspectionNote'],
                $validated['attachments'] ?? [],
            );
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'inspectionTask' => $this->serializeInspectionTask($result['task']),
            'attachments' => collect($result['attachments'])
                ->map(fn (Attachment $attachment) => $this->serializeAttachment($attachment))
                ->values(),
        ], '验机结果已提交，等待后台审核。');
    }

    public function reject(Request $request, string $inspectionTaskId): JsonResponse
    {
        $task = $this->findTaskForOperation($request->user(), $inspectionTaskId);

        if (! $task) {
            return $this->notFound($request);
        }

        if (! $this->canOperateTask($request->user(), $task)) {
            return $this->forbidden($request);
        }

        $validator = Validator::make($request->all(), [
            'reason' => ['required', 'string', 'max:4000'],
        ]);

        if ($validator->fails()) {
            return $this->error($request, 'VALIDATION_ERROR', '退回原因不能为空或格式不正确。', 422, [
                'fields' => $validator->errors()->toArray(),
            ]);
        }

        try {
            $result = $this->stateService->rejectInspection($task, $request->user(), $validator->validated()['reason']);
        } catch (DomainException) {
            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'inspectionTask' => $this->serializeInspectionTask($result['task']),
        ], '验机任务已退回补充资料。');
    }

    private function findTaskForOperation(User $user, string $inspectionTaskId): ?InspectionTask
    {
        $query = InspectionTask::query()
            ->with(['application.store', 'salesAgent'])
            ->whereKey($inspectionTaskId);

        return $query->first();
    }

    private function canOperateTask(User $user, InspectionTask $task): bool
    {
        $role = $this->roleValue($user);

        if ($role === UserRole::SUPER_ADMIN->value) {
            return true;
        }

        return $role === UserRole::SALES->value
            && $user->sales_agent_id !== null
            && $task->sales_agent_id === $user->sales_agent_id;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeApplication(Application $application): array
    {
        return [
            'id' => $application->id,
            'applicationNo' => $application->application_no,
            'storeId' => $application->store_id,
            'storeName' => $application->store?->name,
            'currentOwnerRole' => $application->current_owner_role,
            'currentOwnerUserId' => $application->current_owner_user_id,
            'status' => $application->status instanceof ApplicationStatus ? $application->status->value : $application->status,
            'customerName' => $application->customer_name,
            'brand' => $application->brand,
            'model' => $application->model,
            'loanAmount' => (float) $application->loan_amount,
            'updatedAt' => $application->updated_at?->toISOString(),
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

    /**
     * @param array<string, mixed> $extraError
     */
    private function error(Request $request, string $code, string $message, int $status, array $extraError = []): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                ...$extraError,
            ],
            'requestId' => $this->requestId($request),
        ], $status);
    }

    private function forbidden(Request $request): JsonResponse
    {
        return $this->error($request, 'AUTH_FORBIDDEN', '当前账号没有访问该资源的权限。', 403);
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'INSPECTION_TASK_NOT_FOUND', '验机任务不存在或当前账号不可见。', 404);
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
