<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class AdminAccountUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'displayName' => ['sometimes', 'string', 'max:100'],
            'role' => ['sometimes', Rule::enum(UserRole::class)],
            'status' => ['sometimes', Rule::in(['ACTIVE', 'DISABLED'])],
            'storeId' => ['nullable', 'uuid', 'exists:stores,id'],
            'salesAgentId' => ['nullable', 'uuid', 'exists:sales_agents,id'],
            'disabledReason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $user = $this->route('user');
            $finalRole = $this->input('role') ?? ($user?->role?->value ?? $user?->role);
            $finalStoreId = $this->has('storeId') ? $this->input('storeId') : $user?->store_id;
            $finalSalesAgentId = $this->has('salesAgentId') ? $this->input('salesAgentId') : $user?->sales_agent_id;

            if ($finalRole === UserRole::STORE->value && ! $finalStoreId) {
                $validator->errors()->add('storeId', '店家账号必须绑定门店。');
            }

            if ($finalRole === UserRole::SALES->value && ! $finalSalesAgentId) {
                $validator->errors()->add('salesAgentId', '业务员账号必须绑定业务员档案。');
            }
        });
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '账号更新信息填写不完整或格式不正确。',
                'fields' => $validator->errors()->toArray(),
            ],
            'requestId' => $this->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 422));
    }
}
