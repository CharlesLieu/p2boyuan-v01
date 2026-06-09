<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_application_list_can_filter_by_status_and_keyword(): void
    {
        $this->seed(DemoSeeder::class);

        $items = $this->actingAs($this->user('audit001'), 'sanctum')
            ->getJson('/api/v1/applications?status='.ApplicationStatus::PENDING_PAYOUT->value.'&keyword=A20260530007')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame('A20260530007', $items[0]['applicationNo']);
        $this->assertSame(ApplicationStatus::PENDING_PAYOUT->value, $items[0]['status']);
    }

    public function test_sales_application_filters_stay_inside_assigned_scope(): void
    {
        $this->seed(DemoSeeder::class);

        $items = $this->actingAs($this->user('sales001'), 'sanctum')
            ->getJson('/api/v1/applications?keyword=A20260530003')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(0, $items);
    }

    public function test_payout_list_can_filter_by_status_and_keyword(): void
    {
        $this->seed(DemoSeeder::class);

        $items = $this->actingAs($this->user('cashier001'), 'sanctum')
            ->getJson('/api/v1/payouts?status=PENDING&keyword=A20260530007')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame('PENDING', $items[0]['status']);
        $this->assertSame('A20260530007', $items[0]['application']['applicationNo']);
    }

    public function test_admin_merchant_lists_can_filter_onboarding_and_vouchers(): void
    {
        $this->seed(DemoSeeder::class);
        $admin = $this->user('admin001');
        $store = $this->user('store001');

        $onboardings = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/merchants?status=APPROVED&keyword=东区')
            ->assertOk()
            ->json('data.items');

        $this->assertNotEmpty($onboardings);
        $this->assertTrue(collect($onboardings)->every(fn (array $item) => $item['status'] === 'APPROVED'));

        $vouchers = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/merchant-vouchers?status=PAID&storeId='.$store->store_id)
            ->assertOk()
            ->json('data.items');

        $this->assertNotEmpty($vouchers);
        $this->assertTrue(collect($vouchers)->every(fn (array $item) => $item['status'] === 'PAID'));
        $this->assertTrue(collect($vouchers)->every(fn (array $item) => $item['storeId'] === $store->store_id));
    }

    public function test_store_voucher_filters_stay_inside_own_store(): void
    {
        $this->seed(DemoSeeder::class);

        $items = $this->actingAs($this->user('store001'), 'sanctum')
            ->getJson('/api/v1/merchant/vouchers?keyword=南区')
            ->assertOk()
            ->json('data.items');

        $this->assertCount(0, $items);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
