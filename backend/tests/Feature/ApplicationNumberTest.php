<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Store;
use App\Models\User;
use App\Services\ApplicationNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApplicationNumberTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_application_number_uses_date_and_four_digit_sequence(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 10:00:00'));

        $number = app(ApplicationNumberService::class)->next();

        $this->assertSame('A202606080001', $number);
    }

    public function test_application_number_increments_within_same_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-08 10:00:00'));
        $this->createNumberedApplication('A202606080001');
        $this->createNumberedApplication('A202606080002');

        $number = app(ApplicationNumberService::class)->next();

        $this->assertSame('A202606080003', $number);
    }

    private function createNumberedApplication(string $applicationNo): void
    {
        $store = Store::query()->create([
            'store_code' => 'STORE-'.$applicationNo,
            'name' => '测试门店',
            'contact_name' => '测试联系人',
            'contact_phone' => '0900-000-001',
            'address' => '测试地址',
            'status' => 'ACTIVE',
        ]);
        $user = User::factory()->create([
            'role' => UserRole::SUPER_ADMIN,
            'status' => 'ACTIVE',
        ]);

        Application::query()->create([
            'application_no' => $applicationNo,
            'source_type' => 'ADMIN_CREATE',
            'store_id' => $store->id,
            'created_by_user_id' => $user->id,
            'current_owner_role' => UserRole::AUDITOR->value,
            'current_owner_user_id' => null,
            'status' => ApplicationStatus::PENDING_ASSIGNMENT,
            'customer_name' => '编号测试客户',
            'customer_phone' => '0900-000-002',
            'id_type' => 'NATIONAL_ID',
            'id_number' => 'TEST-ID',
            'customer_address' => '编号测试地址',
            'brand' => 'Apple',
            'model' => 'iPhone',
            'sale_price' => 1000,
            'loan_amount' => 800,
            'periods' => 12,
        ]);
    }
}
