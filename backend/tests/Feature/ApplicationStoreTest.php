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

    public function test_store_user_can_create_application_with_status_log(): void
    {
        $this->seed(DemoSeeder::class);
        $store = User::query()->where('username', 'store001')->firstOrFail();

        $response = $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/applications', $this->validPayload());

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PENDING_ASSIGNMENT->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::AUDITOR->value)
            ->assertJsonPath('data.application.customerName', '彩排客户')
            ->assertJsonStructure([
                'data' => [
                    'application' => [
                        'id',
                        'applicationNo',
                        'storeId',
                        'customerName',
                        'status',
                        'currentOwnerRole',
                    ],
                ],
                'message',
                'requestId',
            ]);

        $applicationId = $response->json('data.application.id');

        $this->assertDatabaseHas('applications', [
            'id' => $applicationId,
            'store_id' => $store->store_id,
            'created_by_user_id' => $store->id,
            'status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
            'current_owner_role' => UserRole::AUDITOR->value,
        ]);

        $this->assertDatabaseHas('status_logs', [
            'application_id' => $applicationId,
            'actor_user_id' => $store->id,
            'to_status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
        ]);
    }

    public function test_only_store_or_super_admin_can_create_application(): void
    {
        $this->seed(DemoSeeder::class);

        foreach (['audit001', 'sales001', 'cashier001'] as $username) {
            $user = User::query()->where('username', $username)->firstOrFail();

            $this->actingAs($user, 'sanctum')
                ->postJson('/api/v1/applications', $this->validPayload())
                ->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }
    }

    public function test_store_cannot_view_other_store_application(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $otherApplication = Application::query()
            ->where('store_id', '!=', $store->store_id)
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/applications/{$otherApplication->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'APPLICATION_NOT_FOUND');

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/applications/{$otherApplication->id}/logs")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'APPLICATION_NOT_FOUND');
    }

    public function test_application_index_filters_by_role(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $sales = User::query()->where('username', 'sales001')->firstOrFail();
        $cashier = User::query()->where('username', 'cashier001')->firstOrFail();
        $auditor = User::query()->where('username', 'audit001')->firstOrFail();

        $storeItems = $this->actingAs($store, 'sanctum')
            ->getJson('/api/v1/applications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.items');

        $this->assertNotEmpty($storeItems);
        $this->assertTrue(collect($storeItems)->every(fn ($item) => $item['storeId'] === $store->store_id));

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

    public function test_logs_return_application_status_history(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $application = Application::query()
            ->where('store_id', $store->store_id)
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
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
