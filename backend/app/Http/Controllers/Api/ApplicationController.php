<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\ApplicationStoreRequest;
use App\Models\Application;
use App\Models\StatusLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ApplicationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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

        if (! in_array($role, [UserRole::STORE->value, UserRole::SUPER_ADMIN->value], true)) {
            return $this->forbidden($request);
        }

        $storeId = $role === UserRole::STORE->value ? $user->store_id : $request->validated('storeId');

        if (! $storeId) {
            return $this->error($request, 'VALIDATION_ERROR', '请选择申请所属门店。', 422);
        }

        $application = DB::transaction(function () use ($request, $user, $storeId): Application {
            $application = Application::query()->create([
                'application_no' => $this->nextApplicationNo(),
                'source_type' => 'STORE_SUBMIT',
                'store_id' => $storeId,
                'created_by_user_id' => $user->id,
                'current_owner_role' => UserRole::AUDITOR->value,
                'current_owner_user_id' => null,
                'status' => ApplicationStatus::PENDING_ASSIGNMENT,
                'customer_name' => $request->validated('customerName'),
                'customer_phone' => $request->validated('customerPhone'),
                'id_type' => $request->validated('idType'),
                'id_number' => $request->validated('idNumber'),
                'customer_address' => $request->validated('customerAddress'),
                'brand' => $request->validated('brand'),
                'model' => $request->validated('model'),
                'color' => $request->validated('color'),
                'capacity' => $request->validated('capacity'),
                'imei' => $request->validated('imei'),
                'device_condition' => $request->validated('deviceCondition'),
                'sale_price' => $request->validated('salePrice'),
                'loan_amount' => $request->validated('loanAmount'),
                'periods' => $request->validated('periods'),
                'remark' => $request->validated('remark'),
            ]);

            StatusLog::query()->create([
                'application_id' => $application->id,
                'actor_user_id' => $user->id,
                'actor_role' => $this->roleValue($user),
                'from_status' => null,
                'to_status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
                'message' => '店家提交验机申请。',
                'metadata' => [
                    'action' => 'SUBMIT_APPLICATION',
                    'source' => 'ApplicationController',
                ],
            ]);

            return $application->fresh(['store', 'createdBy']);
        });

        return $this->success($request, [
            'application' => $this->serializeApplication($application),
        ], '申请已提交，等待后台审核员处理。', 201);
    }

    public function logs(Request $request, string $applicationId): JsonResponse
    {
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

    private function nextApplicationNo(): string
    {
        return 'A'.now()->format('YmdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
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

    private function forbidden(Request $request): JsonResponse
    {
        return $this->error($request, 'AUTH_FORBIDDEN', '当前账号没有访问该资源的权限。', 403);
    }

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'APPLICATION_NOT_FOUND', '申请不存在或当前账号不可见。', 404);
    }

    private function requestId(Request $request): string
    {
        return $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
    }
}
