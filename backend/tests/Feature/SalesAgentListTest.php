<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalesAgentListTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_can_list_active_sales_agents(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = $this->user('audit001');

        $response = $this->actingAs($auditor, 'sanctum')
            ->getJson('/api/v1/sales-agents');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.items.0.code', 'SALES-001')
            ->assertJsonPath('data.items.1.code', 'SALES-002')
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'code',
                            'name',
                            'phone',
                            'status',
                        ],
                    ],
                ],
                'requestId',
            ]);

        $this->assertTrue(Str::isUuid($response->json('data.items.0.id')));
        $this->assertTrue(Str::isUuid($response->json('data.items.1.id')));
    }

    public function test_super_admin_can_list_sales_agents(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = $this->user('admin001');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/sales-agents')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.items');
    }

    public function test_store_cannot_list_sales_agents(): void
    {
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');

        $this->actingAs($store, 'sanctum')
            ->getJson('/api/v1/sales-agents')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
