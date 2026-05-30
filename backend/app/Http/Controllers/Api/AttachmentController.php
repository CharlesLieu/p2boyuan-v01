<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AttachmentController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'applicationId' => ['required', 'uuid', 'exists:applications,id'],
            'module' => ['required', 'string', Rule::in(['APPLICATION', 'INSPECTION', 'SUPPLEMENT', 'PAYOUT', 'VOUCHER', 'OTHER'])],
            'remark' => ['nullable', 'string', 'max:1000'],
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp,pdf', 'max:10240'],
        ]);

        if ($validator->fails()) {
            return $this->validationError($request, '附件格式不正确或大小超过限制。', $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $application = Application::query()
            ->with('inspectionTasks')
            ->findOrFail($validated['applicationId']);

        if (! $this->canAccessApplication($request->user(), $application)) {
            return $this->forbidden($request);
        }

        $file = $validated['file'];
        $path = $file->store('demo-attachments', 'public');

        $attachment = Attachment::query()->create([
            'application_id' => $validated['applicationId'],
            'uploaded_by_user_id' => $request->user()->id,
            'module' => $validated['module'],
            'file_name' => $file->getClientOriginalName(),
            'file_path' => $path,
            'mime_type' => $file->getMimeType(),
            'file_size' => $file->getSize(),
            'remark' => $validated['remark'] ?? null,
        ]);

        return $this->success($request, [
            'attachment' => $this->serializeAttachment($attachment),
        ], '附件已上传。', 201);
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

    private function canAccessApplication(User $user, Application $application): bool
    {
        $role = $this->roleValue($user);

        if (in_array($role, [UserRole::SUPER_ADMIN->value, UserRole::AUDITOR->value], true)) {
            return true;
        }

        if ($role === UserRole::STORE->value) {
            return $user->store_id !== null && $user->store_id === $application->store_id;
        }

        if ($role === UserRole::SALES->value) {
            return $user->sales_agent_id !== null
                && $application->inspectionTasks
                    ->contains('sales_agent_id', $user->sales_agent_id);
        }

        if ($role === UserRole::CASHIER->value) {
            return in_array($application->status?->value ?? $application->status, [
                ApplicationStatus::PENDING_PAYOUT->value,
                ApplicationStatus::PAID->value,
                ApplicationStatus::COMPLETED->value,
            ], true);
        }

        return false;
    }

    private function forbidden(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => '当前账号没有访问该资源的权限。',
            ],
            'requestId' => $this->requestId($request),
        ], 403);
    }

    private function roleValue(User $user): ?string
    {
        return $user->role instanceof UserRole ? $user->role->value : $user->role;
    }

    private function requestId(Request $request): string
    {
        return $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
    }
}
