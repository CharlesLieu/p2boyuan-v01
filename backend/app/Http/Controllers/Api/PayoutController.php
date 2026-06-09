<?php

namespace App\Http\Controllers\Api;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\PayoutConfirmRequest;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\PayoutRecord;
use App\Models\User;
use App\Services\ApplicationStateService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class PayoutController extends Controller
{
    public function __construct(private readonly ApplicationStateService $stateService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = $this->visiblePayouts($request->user())
            ->with(['application.store', 'cashier', 'voucherAttachment']);

        $this->applyPayoutFilters($query, $request);

        $payouts = $query->latest()
            ->limit((int) min(max($request->integer('limit', 50), 1), 100))
            ->get()
            ->map(fn (PayoutRecord $payout) => $this->serializePayoutRecord($payout, includeApplication: true))
            ->values();

        return $this->success($request, [
            'items' => $payouts,
        ]);
    }

    public function confirm(Request $request, string $payoutId): JsonResponse
    {
        $payout = $this->visiblePayouts($request->user())
            ->with(['application.store', 'voucherAttachment'])
            ->whereKey($payoutId)
            ->first();

        if (! $payout) {
            return $this->notFound($request);
        }

        $validator = Validator::make($request->all(), PayoutConfirmRequest::rulesDefinition());

        if ($validator->fails()) {
            return $this->validationError($request, '打款确认资料填写不完整或格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        try {
            $result = $this->stateService->confirmPayout(
                $payout,
                $request->user(),
                (float) $validated['amount'],
                $validated['voucher'],
                $validated['remark'] ?? null,
                $validated['paidAt'] ?? null,
            );
        } catch (DomainException $exception) {
            if ($exception->getMessage() === '打款金额不能超过申请贷款金额。') {
                return $this->validationError($request, $exception->getMessage(), [
                    'amount' => [$exception->getMessage()],
                ]);
            }

            return $this->invalidState($request);
        }

        return $this->success($request, [
            'application' => $this->serializeApplication($result['application']),
            'payoutRecord' => [
                ...$this->serializePayoutRecord($result['payoutRecord']),
                'voucher' => $this->serializeAttachment($result['voucher']),
            ],
        ], '出纳已确认打款。');
    }

    private function visiblePayouts(User $user): Builder
    {
        $role = $this->roleValue($user);

        return PayoutRecord::query()
            ->when($role === UserRole::CASHIER->value, fn (Builder $query) => $query->whereIn('status', ['PENDING', 'PAID']));
    }

    private function applyPayoutFilters(Builder $query, Request $request): void
    {
        $statuses = $this->listParam($request, 'status');
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('storeId')) {
            $query->whereHas('application', fn (Builder $applicationQuery) => $applicationQuery
                ->where('store_id', $request->string('storeId')));
        }

        if ($request->filled('paidFrom')) {
            $query->where('paid_at', '>=', $request->string('paidFrom'));
        }

        if ($request->filled('paidTo')) {
            $query->where('paid_at', '<=', $request->string('paidTo'));
        }

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->string('keyword'));
            $query->where(function (Builder $keywordQuery) use ($keyword): void {
                $keywordQuery
                    ->whereHas('application', function (Builder $applicationQuery) use ($keyword): void {
                        $applicationQuery
                            ->where('application_no', 'like', "%{$keyword}%")
                            ->orWhere('customer_name', 'like', "%{$keyword}%")
                            ->orWhere('customer_phone', 'like', "%{$keyword}%")
                            ->orWhere('model', 'like', "%{$keyword}%")
                            ->orWhereHas('store', fn (Builder $storeQuery) => $storeQuery
                                ->where('name', 'like', "%{$keyword}%"));
                    })
                    ->orWhere('remark', 'like', "%{$keyword}%");
            });
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePayoutRecord(PayoutRecord $record, bool $includeApplication = false): array
    {
        $data = [
            'id' => $record->id,
            'applicationId' => $record->application_id,
            'amount' => (float) $record->amount,
            'status' => $record->status,
            'cashierUserId' => $record->cashier_user_id,
            'cashierName' => $record->cashier?->display_name,
            'voucherAttachmentId' => $record->voucher_attachment_id,
            'paidAt' => $record->paid_at?->toISOString(),
            'remark' => $record->remark,
            'createdAt' => $record->created_at?->toISOString(),
        ];

        if ($includeApplication && $record->application) {
            $data['application'] = $this->serializeApplication($record->application);
        }

        if ($record->voucherAttachment) {
            $data['voucher'] = $this->serializeAttachment($record->voucherAttachment);
        }

        return $data;
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
            'customerPhone' => $application->customer_phone,
            'brand' => $application->brand,
            'model' => $application->model,
            'loanAmount' => (float) $application->loan_amount,
            'updatedAt' => $application->updated_at?->toISOString(),
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

    /**
     * @return array<int, string>
     */
    private function listParam(Request $request, string $key): array
    {
        $value = $request->input($key);

        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), fn (string $item) => trim($item) !== ''));
        }

        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), fn (string $item) => $item !== ''));
        }

        return [];
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

    private function notFound(Request $request): JsonResponse
    {
        return $this->error($request, 'PAYOUT_NOT_FOUND', '打款记录不存在或当前账号不可见。', 404);
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
