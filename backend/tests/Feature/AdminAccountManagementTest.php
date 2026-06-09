<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\SalesAgent;
use App\Models\Store;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_has_v02_account_governance_fields(): void
    {
        $passwordUpdatedAt = now()->subDay();
        $disabledAt = now();

        $user = User::factory()->create([
            'status' => 'DISABLED',
            'password_updated_at' => $passwordUpdatedAt,
            'disabled_at' => $disabledAt,
            'disabled_reason' => '内部试运行停用测试',
        ]);

        $this->assertSame('DISABLED', $user->status);
        $this->assertSame($passwordUpdatedAt->toDateTimeString(), $user->password_updated_at->toDateTimeString());
        $this->assertSame($disabledAt->toDateTimeString(), $user->disabled_at->toDateTimeString());
        $this->assertSame('内部试运行停用测试', $user->disabled_reason);
    }

    public function test_super_admin_can_create_sales_account(): void
    {
        $admin = $this->superAdmin();
        $salesAgent = SalesAgent::query()->create([
            'agent_code' => 'SALES-TEST',
            'name' => '测试业务员',
            'phone' => '0900-000-100',
            'region' => '测试区域',
            'task_status' => 'AVAILABLE',
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/accounts', [
                'username' => 'sales-new',
                'displayName' => '新业务员',
                'password' => '123456',
                'role' => UserRole::SALES->value,
                'salesAgentId' => $salesAgent->id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.username', 'sales-new')
            ->assertJsonPath('data.account.role', UserRole::SALES->value)
            ->assertJsonPath('data.account.salesAgent.id', $salesAgent->id);

        $this->assertDatabaseHas('users', [
            'username' => 'sales-new',
            'display_name' => '新业务员',
            'role' => UserRole::SALES->value,
            'sales_agent_id' => $salesAgent->id,
            'status' => 'ACTIVE',
        ]);
    }

    public function test_disabled_account_cannot_login(): void
    {
        $admin = $this->superAdmin();
        $store = Store::query()->create([
            'store_code' => 'STORE-TEST',
            'name' => '测试门店',
            'contact_name' => '测试联系人',
            'contact_phone' => '0900-000-200',
            'address' => '测试地址',
            'status' => 'ACTIVE',
        ]);
        $user = User::factory()->create([
            'username' => 'store-disabled',
            'display_name' => '待停用店家',
            'password' => Hash::make('123456'),
            'role' => UserRole::STORE,
            'store_id' => $store->id,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/accounts/{$user->id}/disable", [
                'disabledReason' => '内部试运行停用。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.status', 'DISABLED');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'status' => 'DISABLED',
            'disabled_reason' => '内部试运行停用。',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'store-disabled',
            'password' => '123456',
        ])
            ->assertUnauthorized()
            ->assertJsonPath('error.code', 'AUTH_INVALID_CREDENTIALS');
    }

    public function test_super_admin_can_reset_password_and_old_password_fails(): void
    {
        $admin = $this->superAdmin();
        $cashier = User::factory()->create([
            'username' => 'cashier-reset',
            'display_name' => '待重置出纳',
            'password' => Hash::make('oldpass'),
            'role' => UserRole::CASHIER,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/accounts/{$cashier->id}/reset-password", [
                'password' => 'newpass123',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.account.username', 'cashier-reset');

        $this->assertNotNull($cashier->fresh()->password_updated_at);

        $this->postJson('/api/v1/auth/login', [
            'username' => 'cashier-reset',
            'password' => 'oldpass',
        ])->assertUnauthorized();

        $this->postJson('/api/v1/auth/login', [
            'username' => 'cashier-reset',
            'password' => 'newpass123',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user.username', 'cashier-reset');
    }

    public function test_super_admin_cannot_update_store_or_sales_role_without_required_binding(): void
    {
        $admin = $this->superAdmin();
        $user = User::factory()->create([
            'username' => 'binding-test',
            'display_name' => '待绑定账号',
            'role' => UserRole::CASHIER,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/accounts/{$user->id}", [
                'role' => UserRole::STORE->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['storeId'], 'error.fields');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/v1/admin/accounts/{$user->id}", [
                'role' => UserRole::SALES->value,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['salesAgentId'], 'error.fields');
    }

    public function test_non_super_admin_cannot_manage_accounts(): void
    {
        $storeUser = User::factory()->create([
            'username' => 'store-operator',
            'role' => UserRole::STORE,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($storeUser, 'sanctum')
            ->postJson('/api/v1/admin/accounts', [
                'username' => 'blocked-sales',
                'displayName' => '越权账号',
                'password' => '123456',
                'role' => UserRole::SALES->value,
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    private function superAdmin(): User
    {
        return User::factory()->create([
            'username' => 'admin-test',
            'display_name' => '测试超管',
            'role' => UserRole::SUPER_ADMIN,
            'status' => 'ACTIVE',
        ]);
    }
}
