<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\StatusLog;
use App\Models\User;
use App\Services\DemoDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidatorInstance;

class AdminController extends Controller
{
    public function __construct(private readonly DemoDataService $demoDataService)
    {
    }

    public function accounts(Request $request): JsonResponse
    {
        $accounts = User::query()
            ->with(['store', 'salesAgent'])
            ->orderByRaw("case role when 'SUPER_ADMIN' then 1 when 'AUDITOR' then 2 when 'CASHIER' then 3 when 'SALES' then 4 when 'STORE' then 5 else 9 end")
            ->orderBy('username')
            ->get()
            ->map(fn (User $user) => $this->serializeAccount($user))
            ->values();

        return $this->success($request, [
            'items' => $accounts,
        ]);
    }

    public function resetDemoData(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'confirm' => ['accepted'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($request, '请确认后再重置测试数据。', $validator->errors()->toArray());
        }

        $token = $request->user()->currentAccessToken();
        $result = $this->demoDataService->reset();

        if ($token) {
            $newAdmin = User::query()->where('username', 'admin001')->first();

            if ($newAdmin) {
                $token->forceFill([
                    'tokenable_id' => $newAdmin->id,
                ])->save();
            }
        }

        return $this->success($request, $result, '测试数据已重置。');
    }

    public function updateApplicationStatus(Request $request, Application $application): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => ['required', Rule::enum(ApplicationStatus::class)],
            'currentOwnerRole' => ['nullable', Rule::enum(UserRole::class)],
            'currentOwnerUserId' => ['nullable', 'integer', 'exists:users,id'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function (ValidatorInstance $validator) use ($application, $request): void {
            $this->validateOwnerConsistency($validator, $application, $request->all());
        });

        if ($validator->fails()) {
            return $this->validationError($request, '状态调整参数不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $fromStatus = $application->status?->value ?? $application->status;
        $toStatus = $validated['status'];
        $message = $validated['remark'] ?? '超级管理员手动调整申请状态。';

        DB::transaction(function () use ($application, $validated, $message, $request, $fromStatus, $toStatus): void {
            $application->forceFill([
                'status' => $toStatus,
                'current_owner_role' => $validated['currentOwnerRole'] ?? null,
                'current_owner_user_id' => $validated['currentOwnerUserId'] ?? null,
                'remark' => $message,
            ])->save();

            StatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $request->user()->id,
                'actor_role' => $this->roleValue($request->user()),
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'message' => $message,
                'metadata' => [
                    'action' => 'SUPER_ADMIN_STATUS_OVERRIDE',
                    'source' => 'AdminController',
                    'currentOwnerRole' => $application->current_owner_role,
                    'currentOwnerUserId' => $application->current_owner_user_id,
                ],
            ]);
        });

        return $this->success($request, [
            'application' => $this->serializeApplication($application->fresh(['store', 'currentOwner'])),
        ], '申请状态已手动调整。');
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAccount(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'role' => $this->roleValue($user),
            'name' => $user->display_name,
            'status' => $user->status,
            'store' => $user->store ? [
                'id' => $user->store->id,
                'storeCode' => $user->store->store_code,
                'name' => $user->store->name,
                'status' => $user->store->status,
            ] : null,
            'salesAgent' => $user->salesAgent ? [
                'id' => $user->salesAgent->id,
                'agentCode' => $user->salesAgent->agent_code,
                'name' => $user->salesAgent->name,
                'region' => $user->salesAgent->region,
                'taskStatus' => $user->salesAgent->task_status,
                'status' => $user->salesAgent->status,
            ] : null,
            'lastLoginAt' => $user->last_login_at?->toISOString(),
        ];
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
            'currentOwnerName' => $application->currentOwner?->display_name,
            'status' => $application->status?->value ?? $application->status,
            'customerName' => $application->customer_name,
            'brand' => $application->brand,
            'model' => $application->model,
            'loanAmount' => (float) $application->loan_amount,
            'remark' => $application->remark,
            'updatedAt' => $application->updated_at?->toISOString(),
        ];
    }

    private function roleValue(User $user): ?string
    {
        return $user->role instanceof UserRole ? $user->role->value : $user->role;
    }

    /**
     * @param array<string, mixed> $input
     */
    private function validateOwnerConsistency(ValidatorInstance $validator, Application $application, array $input): void
    {
        $status = $this->inputValue($input['status'] ?? null);
        $ownerRole = $this->inputValue($input['currentOwnerRole'] ?? null);
        $ownerUserId = $input['currentOwnerUserId'] ?? null;

        if ($status === null) {
            return;
        }

        if (in_array($status, [
            ApplicationStatus::REJECTED->value,
            ApplicationStatus::PAID->value,
            ApplicationStatus::COMPLETED->value,
        ], true)) {
            if ($ownerRole !== null || $ownerUserId !== null) {
                $validator->errors()->add('currentOwnerRole', '终态申请不能继续归属到某个处理人。');
            }

            return;
        }

        $allowedOwnerRoles = match ($status) {
            ApplicationStatus::DRAFT->value,
            ApplicationStatus::PENDING_ASSIGNMENT->value,
            ApplicationStatus::PENDING_REVIEW->value => [UserRole::AUDITOR->value],
            ApplicationStatus::ASSIGNED->value,
            ApplicationStatus::INSPECTION_IN_PROGRESS->value => [UserRole::SALES->value],
            ApplicationStatus::NEEDS_SUPPLEMENT->value => [UserRole::SALES->value],
            ApplicationStatus::PENDING_PAYOUT->value => [UserRole::CASHIER->value],
            default => [],
        };

        if ($ownerRole === null) {
            $validator->errors()->add('currentOwnerRole', '非终态申请必须指定当前处理角色。');

            return;
        }

        if (! in_array($ownerRole, $allowedOwnerRoles, true)) {
            $validator->errors()->add('currentOwnerRole', '当前处理角色与申请状态不匹配。');
        }

        if ($ownerUserId === null) {
            return;
        }

        $owner = User::query()->find($ownerUserId);

        if (! $owner) {
            return;
        }

        if ($this->roleValue($owner) !== $ownerRole) {
            $validator->errors()->add('currentOwnerUserId', '当前处理人角色与 currentOwnerRole 不匹配。');
        }

        if ($ownerRole === UserRole::SALES->value) {
            $belongsToApplication = $owner->sales_agent_id !== null
                && $application->inspectionTasks()
                    ->where('sales_agent_id', $owner->sales_agent_id)
                    ->exists();

            if (! $belongsToApplication) {
                $validator->errors()->add('currentOwnerUserId', '业务处理人必须属于该申请已指派业务员。');
            }
        }
    }

    private function inputValue(mixed $value): ?string
    {
        return $value instanceof \BackedEnum ? (string) $value->value : $value;
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

    private function requestId(Request $request): string
    {
        return $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
    }
}
