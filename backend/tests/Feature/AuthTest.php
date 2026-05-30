<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_can_login_and_fetch_current_profile(): void
    {
        $this->seed(DemoSeeder::class);

        $login = $this->postJson('/api/v1/auth/login', [
            'username' => 'store001',
            'password' => '123456',
        ]);

        $login
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'store001')
            ->assertJsonPath('data.user.role', UserRole::STORE->value)
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'user' => [
                        'id',
                        'username',
                        'display_name',
                        'role',
                        'status',
                    ],
                ],
                'requestId',
            ]);

        $token = $login->json('data.token');

        $this->withToken($token)
            ->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'store001');
    }

    public function test_login_rejects_wrong_password_with_standard_error(): void
    {
        $this->seed(DemoSeeder::class);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'store001',
            'password' => 'wrong-password',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS')
            ->assertJsonStructure(['error' => ['message'], 'requestId']);
    }

    public function test_login_rejects_missing_or_disabled_users(): void
    {
        $this->seed(DemoSeeder::class);

        DB::table('users')->insert([
            'username' => 'disabled001',
            'display_name' => 'Disabled Demo User',
            'role' => UserRole::STORE->value,
            'status' => 'DISABLED',
            'password' => Hash::make('123456'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'disabled001',
            'password' => '123456',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');

        $this->postJson('/api/v1/auth/login', [
            'username' => 'missing001',
            'password' => '123456',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_logout_deletes_current_access_token(): void
    {
        $this->seed(DemoSeeder::class);

        $token = $this->postJson('/api/v1/auth/login', [
            'username' => 'store001',
            'password' => '123456',
        ])->json('data.token');

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->withToken($token)
            ->postJson('/api/v1/auth/logout')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_role_middleware_allows_any_configured_role_and_rejects_others(): void
    {
        Route::middleware(['auth:sanctum', 'role:SUPER_ADMIN,AUDITOR'])
            ->get('/api/test-role-gate', fn () => response()->json([
                'success' => true,
                'data' => ['allowed' => true],
                'message' => 'OK',
                'requestId' => request()->headers->get('X-Request-Id') ?: 'test-request-id',
            ]));

        $auditor = User::factory()->create([
            'username' => 'audit-test',
            'display_name' => 'Audit Test',
            'role' => UserRole::AUDITOR,
            'status' => 'ACTIVE',
        ]);
        $store = User::factory()->create([
            'username' => 'store-test',
            'display_name' => 'Store Test',
            'role' => UserRole::STORE,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($auditor, 'sanctum')
            ->getJson('/api/test-role-gate')
            ->assertOk()
            ->assertJsonPath('data.allowed', true);

        $this->actingAs($store, 'sanctum')
            ->getJson('/api/test-role-gate')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }
}
