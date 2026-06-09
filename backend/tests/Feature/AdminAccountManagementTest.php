<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
