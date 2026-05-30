<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ApplicationStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'storeId' => ['sometimes', 'uuid', 'exists:stores,id'],
            'customerName' => ['required', 'string', 'max:100'],
            'customerPhone' => ['required', 'string', 'max:40'],
            'idType' => ['required', 'string', 'max:30'],
            'idNumber' => ['required', 'string', 'max:80'],
            'customerAddress' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:50'],
            'model' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:50'],
            'capacity' => ['nullable', 'string', 'max:50'],
            'imei' => ['nullable', 'string', 'max:80'],
            'deviceCondition' => ['nullable', 'string', 'max:255'],
            'salePrice' => ['required', 'numeric', 'min:0'],
            'loanAmount' => ['required', 'numeric', 'min:0'],
            'periods' => ['required', 'integer', 'min:1', 'max:60'],
            'remark' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '申请资料填写不完整或格式不正确。',
                'fields' => $validator->errors()->toArray(),
            ],
            'requestId' => $this->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 422));
    }
}
