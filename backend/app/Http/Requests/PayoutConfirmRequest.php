<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class PayoutConfirmRequest extends FormRequest
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
        return self::rulesDefinition();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function rulesDefinition(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paidAt' => ['nullable', 'date'],
            'voucher' => ['required', 'array'],
            'voucher.fileName' => ['required', 'string', 'max:255'],
            'voucher.filePath' => ['required', 'string', 'max:500'],
            'voucher.mimeType' => ['nullable', 'string', 'max:120'],
            'voucher.fileSize' => ['nullable', 'integer', 'min:0'],
            'voucher.remark' => ['nullable', 'string', 'max:1000'],
            'remark' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '打款确认资料填写不完整或格式不正确。',
                'fields' => $validator->errors()->toArray(),
            ],
            'requestId' => $this->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 422));
    }
}
