<?php

namespace App\Http\Controllers\Api {
    function random_int(int $min, int $max): int
    {
        if (isset($GLOBALS['application_controller_random_int'])
            && is_array($GLOBALS['application_controller_random_int'])
            && $GLOBALS['application_controller_random_int'] !== []) {
            return array_shift($GLOBALS['application_controller_random_int']);
        }

        return \random_int($min, $max);
    }
}

namespace Tests\Feature {
    use App\Enums\ApplicationStatus;
    use App\Enums\UserRole;
    use App\Models\Application;
    use App\Models\User;
    use Carbon\Carbon;
    use Database\Seeders\DemoSeeder;
    use Illuminate\Foundation\Testing\RefreshDatabase;
    use Tests\TestCase;

    class ApplicationStoreQualityFixTest extends TestCase
    {
        use RefreshDatabase;

        protected function tearDown(): void
        {
            unset($GLOBALS['application_controller_random_int']);
            Carbon::setTestNow();

            parent::tearDown();
        }

        public function test_unauthorized_user_gets_forbidden_before_payload_validation(): void
        {
            $this->seed(DemoSeeder::class);
            $auditor = User::query()->where('username', 'audit001')->firstOrFail();

            $this->actingAs($auditor, 'sanctum')
                ->postJson('/api/v1/applications', [])
                ->assertForbidden()
                ->assertJsonPath('success', false)
                ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
        }

        public function test_application_number_collision_is_retried_without_500(): void
        {
            $this->seed(DemoSeeder::class);
            Carbon::setTestNow(Carbon::parse('2026-05-30 21:30:00'));
            $GLOBALS['application_controller_random_int'] = [123, 124];

            $admin = User::query()->where('username', 'admin001')->firstOrFail();
            $storeUser = User::query()->where('username', 'store001')->firstOrFail();
            $collisionNo = 'A20260530213000123';

            Application::query()->create([
                'application_no' => $collisionNo,
                'source_type' => 'USER_SUBMIT',
                'store_id' => $storeUser->store_id,
                'created_by_user_id' => $storeUser->id,
                'current_owner_role' => UserRole::AUDITOR->value,
                'current_owner_user_id' => null,
                'status' => ApplicationStatus::PENDING_ASSIGNMENT,
                'customer_name' => '已有客户',
                'customer_phone' => '0900-888-000',
                'id_type' => 'NATIONAL_ID',
                'id_number' => 'EXISTING-ID',
                'customer_address' => '已有地址',
                'brand' => 'Apple',
                'model' => 'iPhone 16 Pro',
                'sale_price' => 8999,
                'loan_amount' => 7200,
                'periods' => 12,
            ]);

            $response = $this->actingAs($admin, 'sanctum')
                ->postJson('/api/v1/applications', [
                    ...$this->validPayload(),
                    'storeId' => $storeUser->store_id,
                ]);

            $response
                ->assertCreated()
                ->assertJsonPath('success', true);

            $this->assertNotSame($collisionNo, $response->json('data.application.applicationNo'));
            $this->assertDatabaseHas('applications', [
                'application_no' => 'A20260530213000124',
                'customer_name' => '彩排客户',
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
                'remark' => '测试提交',
            ];
        }
    }
}
