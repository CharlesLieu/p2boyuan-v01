<?php

namespace App\Http\Requests;

use App\Enums\UserRole;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as ValidationValidator;

class AdminAccountStoreRequest extends FormRequest
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
            'username' => ['required', 'string', 'max:64', 'unique:users,username'],
            'displayName' => ['required', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:6', 'max:64'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'storeId' => ['nullable', 'uuid', 'exists:stores,id'],
            'salesAgentId' => ['nullable', 'uuid', 'exists:sales_agents,id'],
        ];
    }

    public function withValidator(ValidationValidator $validator): void
    {
        $validator->after(function (ValidationValidator $validator): void {
            $role = $this->input('role');

            if ($role === UserRole::STORE->value && ! $this->filled('storeId')) {
                $validator->errors()->add('storeId', '店家账号必须绑定门店。');
            }

            if ($role === UserRole::SALES->value && ! $this->filled('salesAgentId')) {
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
                'message' => '账号信息填写不完整或格式不正确。',
                'fields' => $validator->errors()->toArray(),
            ],
            'requestId' => $this->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 422));
    }
}
