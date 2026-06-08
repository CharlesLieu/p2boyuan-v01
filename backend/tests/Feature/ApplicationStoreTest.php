<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_user_cannot_create_customer_application(): void
    {
        $this->seed(DemoSeeder::class);
        $store = User::query()->where('username', 'store001')->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/applications', $this->validPayload())
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_only_super_admin_can_create_customer_application_for_demo_support(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['store001', 'audit001', 'sales001', 'cashier001'] as $username) {
            $user = User::query()->where('username', $username)->firstOrFail();

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/applications', $this->validPayload())
                ->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        $admin = User::query()->where('username', 'admin001')->firstOrFail();
        $store = User::query()->where('username', 'store001')->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/applications', [
                ...$this->validPayload(),
                'storeId' => $store->store_id,
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PENDING_ASSIGNMENT->value);
    }

    public function test_store_cannot_view_customer_application_detail_or_logs(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $application = Application::query()->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/applications/{$application->id}")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/applications/{$application->id}/logs")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_application_index_filters_by_role(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $sales = User::query()->where('username', 'sales001')->firstOrFail();
        $cashier = User::query()->where('username', 'cashier001')->firstOrFail();
        $auditor = User::query()->where('username', 'audit001')->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->getJson('/api/v1/applications')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $salesItems = $this->actingAs($sales, 'sanctum')
            ->getJson('/api/v1/applications')
            ->assertOk()
            ->json('data.items');

        $this->assertNotEmpty($salesItems);
        $this->assertTrue(collect($salesItems)->contains('applicationNo', 'A20260530002'));
        $this->assertFalse(collect($salesItems)->contains('applicationNo', 'A20260530003'));

        $cashierItems = $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/v1/applications')
            ->assertOk()
            ->json('data.items');

        $this->assertEqualsCanonicalizing(
            [ApplicationStatus::PENDING_PAYOUT->value, ApplicationStatus::PAID->value],
            collect($cashierItems)->pluck('status')->unique()->values()->all(),
        );

        $auditorItems = $this->actingAs($auditor, 'sanctum')
            ->getJson('/api/v1/applications')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(8, $auditorItems);
    }

    public function test_authorized_back_office_user_can_read_application_status_history(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $application = Application::query()->firstOrFail();

        $this->actingAs($auditor, 'sanctum')
            ->getJson("/api/v1/applications/{$application->id}/logs")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        [
                            'id',
                            'applicationId',
                            'actorUserId',
                            'actorRole',
                            'fromStatus',
                            'toStatus',
                            'message',
                            'action',
                            'createdAt',
                        ],
                    ],
                ],
                'requestId',
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'customerName' => '彩排客户',
            'customerPhone' => '0900-888-001',
            'idType' => 'NATIONAL_ID',
            'idNumber' => 'DEMO-ID-NEW',
            'customerAddress' => '彩排客户地址',
            'brand' => 'Apple',
            'model' => 'iPhone 16 Pro',
            'color' => '原色',
            'capacity' => '256GB',
            'imei' => 'DEMO-IMEI-NEW',
            'deviceCondition' => '外观良好',
            'salePrice' => 8999,
            'loanAmount' => 7200,
            'periods' => 12,
            'remark' => '门店现场彩排提交',
        ];
    }
}
