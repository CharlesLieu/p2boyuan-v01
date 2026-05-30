<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

class ReviewDecisionRequest extends FormRequest
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
            'note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => '审核意见格式不正确。',
                'fields' => $validator->errors()->toArray(),
            ],
            'requestId' => $this->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 422));
    }
}
