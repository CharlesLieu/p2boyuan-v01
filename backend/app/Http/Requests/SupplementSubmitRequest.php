<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class SupplementSubmitRequest extends FormRequest
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
            'note' => ['required', 'string', 'max:4000'],
            'attachments' => ['sometimes', 'array', 'max:10'],
            'attachments.*.fileName' => ['required_with:attachments', 'string', 'max:255'],
            'attachments.*.filePath' => ['required_with:attachments', 'string', 'max:500'],
            'attachments.*.mimeType' => ['nullable', 'string', 'max:120'],
            'attachments.*.fileSize' => ['nullable', 'integer', 'min:0'],
            'attachments.*.remark' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '补充资料填写不完整或格式不正确。',
                'fields' => $validator->errors()->toArray(),
            ],
            'requestId' => $this->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 422));
    }
}
