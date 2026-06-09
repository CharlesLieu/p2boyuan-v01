<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\MerchantOnboardingApplication;
use App\Models\MerchantPaymentVoucher;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class MerchantController extends Controller
{
    public function submitOnboarding(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->store_id) {
            return $this->validationError($request, '当前商家账号未绑定门店。', []);
        }

        $validator = Validator::make($request->all(), $this->onboardingRules());

        if ($validator->fails()) {
            return $this->validationError($request, '商家入驻资料填写不完整或格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $onboarding = DB::transaction(function () use ($validated, $user): MerchantOnboardingApplication {
            $store = Store::query()->findOrFail($user->store_id);

            $store->update([
                'onboarding_status' => 'PENDING_REVIEW',
                'name' => $validated['merchantName'],
                'address' => $validated['merchantAddress'],
                'contact_name' => $validated['contactName'],
                'contact_phone' => $validated['contactPhone'],
                'payment_method' => $validated['paymentMethod'],
                'payment_account' => $validated['paymentAccount'],
                'payment_account_name' => $validated['paymentAccountName'],
                'payment_bank_or_channel' => $validated['paymentBankOrChannel'] ?? null,
            ]);

            return MerchantOnboardingApplication::query()->create([
                'store_id' => $store->id,
                'applicant_name' => $validated['applicantName'],
                'applicant_phone' => $validated['applicantPhone'],
                'applicant_id_number' => $validated['applicantIdNumber'],
                'merchant_name' => $validated['merchantName'],
                'merchant_address' => $validated['merchantAddress'],
                'contact_name' => $validated['contactName'],
                'contact_phone' => $validated['contactPhone'],
                'payment_method' => $validated['paymentMethod'],
                'payment_account' => $validated['paymentAccount'],
                'payment_account_name' => $validated['paymentAccountName'],
                'payment_bank_or_channel' => $validated['paymentBankOrChannel'] ?? null,
                'id_card_front_file' => $validated['idCardFrontFile'],
                'id_card_back_file' => $validated['idCardBackFile'],
                'qualification_file' => $validated['qualificationFile'],
                'status' => 'PENDING_REVIEW',
            ])->fresh('store');
        });

        return $this->success($request, [
            'onboarding' => $this->serializeOnboarding($onboarding),
        ], '商家入驻申请已提交，等待平台审核。', 201);
    }

    public function me(Request $request): JsonResponse
    {
        $store = Store::query()
            ->with(['onboardingApplications' => fn ($query) => $query->latest()])
            ->find($request->user()->store_id);

        if (! $store) {
            return $this->notFound($request, 'MERCHANT_NOT_FOUND', '商家不存在或当前账号不可见。');
        }

        return $this->success($request, [
            'profile' => $this->serializeStoreProfile($store),
            'latestOnboarding' => $store->onboardingApplications->first()
                ? $this->serializeOnboarding($store->onboardingApplications->first())
                : null,
        ]);
    }

    public function vouchers(Request $request): JsonResponse
    {
        $query = MerchantPaymentVoucher::query()
            ->where('store_id', $request->user()->store_id);

        $this->applyVoucherFilters($query, $request);

        $items = $query->latest('paid_at')
            ->latest()
            ->limit((int) min(max($request->integer('limit', 50), 1), 100))
            ->get()
            ->map(fn (MerchantPaymentVoucher $voucher) => $this->serializeVoucher($voucher))
            ->values();

        return $this->success($request, ['items' => $items]);
    }

    public function voucher(Request $request, string $voucherId): JsonResponse
    {
        $voucher = MerchantPaymentVoucher::query()
            ->where('store_id', $request->user()->store_id)
            ->whereKey($voucherId)
            ->first();

        if (! $voucher) {
            return $this->notFound($request, 'MERCHANT_VOUCHER_NOT_FOUND', '凭证不存在或当前商家不可见。');
        }

        return $this->success($request, [
            'voucher' => $this->serializeVoucher($voucher, includeDetail: true),
        ]);
    }

    public function adminMerchants(Request $request): JsonResponse
    {
        $query = MerchantOnboardingApplication::query()
            ->with('store');

        $this->applyOnboardingFilters($query, $request);

        $items = $query->latest()
            ->limit((int) min(max($request->integer('limit', 50), 1), 100))
            ->get()
            ->map(fn (MerchantOnboardingApplication $onboarding) => $this->serializeOnboarding($onboarding))
            ->values();

        return $this->success($request, ['items' => $items]);
    }

    public function adminMerchant(Request $request, string $onboardingId): JsonResponse
    {
        $onboarding = MerchantOnboardingApplication::query()->with(['store', 'reviewer'])->find($onboardingId);

        if (! $onboarding) {
            return $this->notFound($request, 'MERCHANT_ONBOARDING_NOT_FOUND', '商家入驻申请不存在。');
        }

        return $this->success($request, [
            'onboarding' => $this->serializeOnboarding($onboarding, includeDetail: true),
        ]);
    }

    public function approveMerchant(Request $request, string $onboardingId): JsonResponse
    {
        $onboarding = MerchantOnboardingApplication::query()->with('store')->find($onboardingId);

        if (! $onboarding) {
            return $this->notFound($request, 'MERCHANT_ONBOARDING_NOT_FOUND', '商家入驻申请不存在。');
        }

        $validator = Validator::make($request->all(), [
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($request, '审核意见格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($onboarding, $request, $validated): void {
            $onboarding->update([
                'status' => 'APPROVED',
                'reviewer_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => $validated['note'] ?? null,
                'reject_reason' => null,
            ]);

            $onboarding->store?->update([
                'name' => $onboarding->merchant_name,
                'address' => $onboarding->merchant_address,
                'contact_name' => $onboarding->contact_name,
                'contact_phone' => $onboarding->contact_phone,
                'onboarding_status' => 'APPROVED',
                'payment_method' => $onboarding->payment_method,
                'payment_account' => $onboarding->payment_account,
                'payment_account_name' => $onboarding->payment_account_name,
                'payment_bank_or_channel' => $onboarding->payment_bank_or_channel,
            ]);
        });

        return $this->success($request, [
            'onboarding' => $this->serializeOnboarding($onboarding->fresh(['store', 'reviewer'])),
        ], '商家入驻审核已通过。');
    }

    public function rejectMerchant(Request $request, string $onboardingId): JsonResponse
    {
        $onboarding = MerchantOnboardingApplication::query()->with('store')->find($onboardingId);

        if (! $onboarding) {
            return $this->notFound($request, 'MERCHANT_ONBOARDING_NOT_FOUND', '商家入驻申请不存在。');
        }

        $validator = Validator::make($request->all(), [
            'rejectReason' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($request, '驳回原因不能为空或格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($onboarding, $request, $validated): void {
            $onboarding->update([
                'status' => 'REJECTED',
                'reviewer_user_id' => $request->user()->id,
                'reviewed_at' => now(),
                'review_note' => null,
                'reject_reason' => $validated['rejectReason'],
            ]);

            $onboarding->store?->update([
                'onboarding_status' => 'REJECTED',
            ]);
        });

        return $this->success($request, [
            'onboarding' => $this->serializeOnboarding($onboarding->fresh(['store', 'reviewer'])),
        ], '商家入驻申请已驳回。');
    }

    public function adminVouchers(Request $request): JsonResponse
    {
        $query = MerchantPaymentVoucher::query()
            ->with('store');

        $this->applyVoucherFilters($query, $request, allowStoreFilter: true);

        $items = $query->latest('paid_at')
            ->latest()
            ->limit((int) min(max($request->integer('limit', 50), 1), 100))
            ->get()
            ->map(fn (MerchantPaymentVoucher $voucher) => $this->serializeVoucher($voucher, includeDetail: true))
            ->values();

        return $this->success($request, ['items' => $items]);
    }

    public function createVoucher(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), $this->voucherRules());

        if ($validator->fails()) {
            return $this->validationError($request, '打款凭证资料填写不完整或格式不正确。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();

        $voucher = null;
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                $voucher = MerchantPaymentVoucher::query()->create([
                    'voucher_no' => $this->nextVoucherNo(),
                    'store_id' => $validated['storeId'],
                    'payout_record_id' => $validated['payoutRecordId'] ?? null,
                    'related_business_no' => $validated['relatedBusinessNo'] ?? null,
                    'amount' => $validated['amount'],
                    'status' => $validated['status'],
                    'paid_at' => $validated['paidAt'] ?? null,
                    'payee_name' => $validated['payeeName'],
                    'payee_account_masked' => $validated['payeeAccountMasked'],
                    'payer_name' => $validated['payerName'] ?? null,
                    'voucher_file' => $validated['voucherFile'],
                    'remark' => $validated['remark'] ?? null,
                    'created_by_user_id' => $request->user()->id,
                ]);
                break;
            } catch (UniqueConstraintViolationException $exception) {
                if (! Str::contains($exception->getMessage(), 'voucher_no')) {
                    throw $exception;
                }
            }
        }

        if (! $voucher) {
            return $this->error($request, 'VOUCHER_NO_GENERATION_FAILED', '凭证编号生成失败，请稍后重试。', 409);
        }

        return $this->success($request, [
            'voucher' => $this->serializeVoucher($voucher->fresh('store'), includeDetail: true),
        ], '打款凭证已创建。', 201);
    }

    public function voidVoucher(Request $request, string $voucherId): JsonResponse
    {
        $voucher = MerchantPaymentVoucher::query()->find($voucherId);

        if (! $voucher) {
            return $this->notFound($request, 'MERCHANT_VOUCHER_NOT_FOUND', '打款凭证不存在。');
        }

        $validator = Validator::make($request->all(), [
            'voidReason' => ['required', 'string', 'max:2000'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($request, '作废原因不能为空或格式不正确。', $validator->errors()->toArray());
        }

        $voucher->update([
            'status' => 'VOIDED',
            'void_reason' => $validator->validated()['voidReason'],
        ]);

        return $this->success($request, [
            'voucher' => $this->serializeVoucher($voucher->fresh('store'), includeDetail: true),
        ], '打款凭证已作废。');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function onboardingRules(): array
    {
        return [
            'applicantName' => ['required', 'string', 'max:100'],
            'applicantPhone' => ['required', 'string', 'max:40'],
            'applicantIdNumber' => ['required', 'string', 'max:80'],
            'merchantName' => ['required', 'string', 'max:100'],
            'merchantAddress' => ['required', 'string', 'max:255'],
            'contactName' => ['required', 'string', 'max:100'],
            'contactPhone' => ['required', 'string', 'max:40'],
            'paymentMethod' => ['required', 'string', 'max:40'],
            'paymentAccount' => ['required', 'string', 'max:120'],
            'paymentAccountName' => ['required', 'string', 'max:120'],
            'paymentBankOrChannel' => ['nullable', 'string', 'max:120'],
            'idCardFrontFile' => ['required', 'array'],
            'idCardFrontFile.fileName' => ['required', 'string', 'max:255'],
            'idCardFrontFile.filePath' => ['required', 'string', 'max:500'],
            'idCardFrontFile.mimeType' => ['required', 'string', 'max:120'],
            'idCardFrontFile.fileSize' => ['nullable', 'integer', 'min:0'],
            'idCardBackFile' => ['required', 'array'],
            'idCardBackFile.fileName' => ['required', 'string', 'max:255'],
            'idCardBackFile.filePath' => ['required', 'string', 'max:500'],
            'idCardBackFile.mimeType' => ['required', 'string', 'max:120'],
            'idCardBackFile.fileSize' => ['nullable', 'integer', 'min:0'],
            'qualificationFile' => ['required', 'array'],
            'qualificationFile.fileName' => ['required', 'string', 'max:255'],
            'qualificationFile.filePath' => ['required', 'string', 'max:500'],
            'qualificationFile.mimeType' => ['required', 'string', 'max:120'],
            'qualificationFile.fileSize' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private function voucherRules(): array
    {
        return [
            'storeId' => ['required', 'uuid', 'exists:stores,id'],
            'payoutRecordId' => ['nullable', 'uuid', 'exists:payout_records,id'],
            'relatedBusinessNo' => ['nullable', 'string', 'max:80'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'status' => ['required', 'string', Rule::in(['PENDING_CONFIRMATION', 'PAID', 'VOIDED'])],
            'paidAt' => ['nullable', 'date'],
            'payeeName' => ['required', 'string', 'max:120'],
            'payeeAccountMasked' => ['required', 'string', 'max:120'],
            'payerName' => ['nullable', 'string', 'max:120'],
            'voucherFile' => ['required', 'array'],
            'voucherFile.fileName' => ['required', 'string', 'max:255'],
            'voucherFile.filePath' => ['required', 'string', 'max:500'],
            'voucherFile.mimeType' => ['required', 'string', 'max:120'],
            'voucherFile.fileSize' => ['nullable', 'integer', 'min:0'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function applyOnboardingFilters($query, Request $request): void
    {
        $statuses = $this->listParam($request, 'status');
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($request->filled('storeId')) {
            $query->where('store_id', $request->string('storeId'));
        }

        if ($request->filled('createdFrom')) {
            $query->where('created_at', '>=', $request->string('createdFrom'));
        }

        if ($request->filled('createdTo')) {
            $query->where('created_at', '<=', $request->string('createdTo'));
        }

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->string('keyword'));
            $query->where(function ($keywordQuery) use ($keyword): void {
                $keywordQuery
                    ->where('applicant_name', 'like', "%{$keyword}%")
                    ->orWhere('applicant_phone', 'like', "%{$keyword}%")
                    ->orWhere('merchant_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_name', 'like', "%{$keyword}%")
                    ->orWhere('contact_phone', 'like', "%{$keyword}%")
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$keyword}%"));
            });
        }
    }

    private function applyVoucherFilters($query, Request $request, bool $allowStoreFilter = false): void
    {
        $statuses = $this->listParam($request, 'status');
        if ($statuses !== []) {
            $query->whereIn('status', $statuses);
        }

        if ($allowStoreFilter && $request->filled('storeId')) {
            $query->where('store_id', $request->string('storeId'));
        }

        if ($request->filled('paidFrom')) {
            $query->where('paid_at', '>=', $request->string('paidFrom'));
        }

        if ($request->filled('paidTo')) {
            $query->where('paid_at', '<=', $request->string('paidTo'));
        }

        if ($request->filled('keyword')) {
            $keyword = trim((string) $request->string('keyword'));
            $query->where(function ($keywordQuery) use ($keyword): void {
                $keywordQuery
                    ->where('voucher_no', 'like', "%{$keyword}%")
                    ->orWhere('related_business_no', 'like', "%{$keyword}%")
                    ->orWhere('payee_name', 'like', "%{$keyword}%")
                    ->orWhere('payer_name', 'like', "%{$keyword}%")
                    ->orWhere('remark', 'like', "%{$keyword}%")
                    ->orWhereHas('store', fn ($storeQuery) => $storeQuery->where('name', 'like', "%{$keyword}%"));
            });
        }
    }

    private function serializeStoreProfile(Store $store): array
    {
        return [
            'id' => $store->id,
            'storeCode' => $store->store_code,
            'name' => $store->name,
            'contactName' => $store->contact_name,
            'contactPhone' => $store->contact_phone,
            'address' => $store->address,
            'status' => $store->status,
            'onboardingStatus' => $store->onboarding_status,
            'paymentMethod' => $store->payment_method,
            'paymentAccountMasked' => $this->maskAccount($store->payment_account),
            'paymentAccountName' => $store->payment_account_name,
            'paymentBankOrChannel' => $store->payment_bank_or_channel,
            'createdAt' => $store->created_at?->toISOString(),
            'updatedAt' => $store->updated_at?->toISOString(),
        ];
    }

    private function serializeOnboarding(MerchantOnboardingApplication $onboarding, bool $includeDetail = false): array
    {
        $data = [
            'id' => $onboarding->id,
            'storeId' => $onboarding->store_id,
            'storeName' => $onboarding->store?->name,
            'applicantName' => $onboarding->applicant_name,
            'applicantPhone' => $onboarding->applicant_phone,
            'merchantName' => $onboarding->merchant_name,
            'merchantAddress' => $onboarding->merchant_address,
            'contactName' => $onboarding->contact_name,
            'contactPhone' => $onboarding->contact_phone,
            'paymentMethod' => $onboarding->payment_method,
            'paymentAccountMasked' => $this->maskAccount($onboarding->payment_account),
            'paymentAccountName' => $onboarding->payment_account_name,
            'paymentBankOrChannel' => $onboarding->payment_bank_or_channel,
            'status' => $onboarding->status,
            'reviewerUserId' => $onboarding->reviewer_user_id,
            'reviewerName' => $onboarding->reviewer?->display_name,
            'reviewedAt' => $onboarding->reviewed_at?->toISOString(),
            'reviewNote' => $onboarding->review_note,
            'rejectReason' => $onboarding->reject_reason,
            'createdAt' => $onboarding->created_at?->toISOString(),
            'updatedAt' => $onboarding->updated_at?->toISOString(),
        ];

        if ($includeDetail) {
            $data['idCardFrontFile'] = $onboarding->id_card_front_file;
            $data['idCardBackFile'] = $onboarding->id_card_back_file;
            $data['qualificationFile'] = $onboarding->qualification_file;
        }

        return $data;
    }

    private function serializeVoucher(MerchantPaymentVoucher $voucher, bool $includeDetail = false): array
    {
        $data = [
            'id' => $voucher->id,
            'voucherNo' => $voucher->voucher_no,
            'storeId' => $voucher->store_id,
            'storeName' => $voucher->store?->name,
            'payoutRecordId' => $voucher->payout_record_id,
            'relatedBusinessNo' => $voucher->related_business_no,
            'amount' => (float) $voucher->amount,
            'status' => $voucher->status,
            'paidAt' => $voucher->paid_at?->toISOString(),
            'payeeName' => $voucher->payee_name,
            'payeeAccountMasked' => $voucher->payee_account_masked,
            'payerName' => $voucher->payer_name,
            'remark' => $voucher->remark,
            'voidReason' => $voucher->void_reason,
            'createdAt' => $voucher->created_at?->toISOString(),
            'updatedAt' => $voucher->updated_at?->toISOString(),
        ];

        if ($includeDetail) {
            $data['voucherFile'] = $voucher->voucher_file;
        }

        return $data;
    }

    private function maskAccount(?string $account): ?string
    {
        if (! $account) {
            return null;
        }

        if (mb_strlen($account) <= 8) {
            return str_repeat('*', mb_strlen($account));
        }

        return mb_substr($account, 0, 4).str_repeat('*', max(mb_strlen($account) - 8, 0)).mb_substr($account, -4);
    }

    private function nextVoucherNo(): string
    {
        return 'PV'.now()->format('YmdHis').str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
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

    private function notFound(Request $request, string $code, string $message): JsonResponse
    {
        return $this->error($request, $code, $message, 404);
    }

    private function requestId(Request $request): string
    {
        return $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
    }
}
