<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        $userRole = $user?->role instanceof UserRole ? $user->role->value : $user?->role;

        if (! $user || ! in_array($userRole, $roles, true)) {
            return $this->forbidden($request);
        }

        return $next($request);
    }

    private function forbidden(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'AUTH_FORBIDDEN',
                'message' => '当前账号没有访问该资源的权限。',
            ],
            'requestId' => $request->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ], 403);
    }
}
