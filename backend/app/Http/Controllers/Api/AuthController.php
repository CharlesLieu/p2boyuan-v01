<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return $this->error(
                $request,
                'VALIDATION_ERROR',
                '请填写账号和密码。',
                422,
            );
        }

        $credentials = $validator->validated();

        $user = User::query()
            ->where('username', $credentials['username'])
            ->where('status', 'ACTIVE')
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return $this->error(
                $request,
                'AUTH_INVALID_CREDENTIALS',
                '账号或密码错误，或账号已被禁用。',
                401,
            );
        }

        $user->forceFill(['last_login_at' => now()])->save();

        return $this->success($request, [
            'token' => $user->createToken('v0.1-demo')->plainTextToken,
            'user' => $this->serializeUser($user->fresh()),
        ], '登录成功。');
    }

    public function me(Request $request): JsonResponse
    {
        return $this->success($request, [
            'user' => $this->serializeUser($request->user()),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return $this->success($request, null, '已退出登录。');
    }

    private function success(Request $request, mixed $data = null, ?string $message = null): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'requestId' => $this->requestId($request),
        ]);
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

    private function requestId(Request $request): string
    {
        return $request->headers->get('X-Request-Id') ?: (string) Str::uuid();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'role' => $user->role?->value ?? $user->role,
            'status' => $user->status,
            'store_id' => $user->store_id,
            'sales_agent_id' => $user->sales_agent_id,
            'last_login_at' => $user->last_login_at?->toISOString(),
        ];
    }
}
