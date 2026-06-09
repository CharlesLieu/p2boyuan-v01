<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\MerchantPaymentVoucher;
use App\Models\PayoutRecord;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RoleBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_cannot_access_application_list(): void
    {
        $this->seed(DemoSeeder::class);

        $this->actingAs($this->user('store001'), 'sanctum')
            ->getJson('/api/v1/applications')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_sales_cannot_view_unassigned_application(): void
    {
        $this->seed(DemoSeeder::class);

        $application = Application::query()
            ->where('application_no', 'A20260530003')
            ->firstOrFail();

        $this->actingAs($this->user('sales001'), 'sanctum')
            ->getJson("/api/v1/applications/{$application->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'APPLICATION_NOT_FOUND');
    }

    public function test_cashier_cannot_approve_application(): void
    {
        $this->seed(DemoSeeder::class);

        $application = Application::query()
            ->where('application_no', 'A20260530004')
            ->firstOrFail();

        $this->actingAs($this->user('cashier001'), 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/approve", [
                'note' => '越权审核。',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_auditor_cannot_confirm_payout(): void
    {
        $this->seed(DemoSeeder::class);

        $payout = PayoutRecord::query()
            ->where('status', 'PENDING')
            ->firstOrFail();

        $this->actingAs($this->user('audit001'), 'sanctum')
            ->postJson("/api/v1/payouts/{$payout->id}/confirm", [
                'amount' => $payout->amount,
                'voucher' => [
                    'fileName' => 'blocked.png',
                    'filePath' => 'demo/blocked.png',
                    'mimeType' => 'image/png',
                    'fileSize' => 1000,
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_store_can_only_view_own_merchant_vouchers(): void
    {
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');
        $ownVoucher = MerchantPaymentVoucher::query()
            ->where('store_id', $store->store_id)
            ->firstOrFail();
        $otherVoucher = MerchantPaymentVoucher::query()
            ->where('store_id', '!=', $store->store_id)
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/merchant/vouchers/{$ownVoucher->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.voucher.id', $ownVoucher->id);

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/merchant/vouchers/{$otherVoucher->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'MERCHANT_VOUCHER_NOT_FOUND');
    }

    public function test_attachment_upload_module_is_limited_by_role(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);

        $cashierVisibleApplication = Application::query()
            ->where('application_no', 'A20260530007')
            ->firstOrFail();
        $salesVisibleApplication = Application::query()
            ->where('application_no', 'A20260530002')
            ->firstOrFail();

        $this->actingAs($this->user('cashier001'), 'sanctum')
            ->postJson('/api/v1/attachments', [
                'applicationId' => $cashierVisibleApplication->id,
                'module' => 'APPLICATION',
                'file' => UploadedFile::fake()->create('cashier-application.png', 128, 'image/png'),
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->actingAs($this->user('sales001'), 'sanctum')
            ->postJson('/api/v1/attachments', [
                'applicationId' => $salesVisibleApplication->id,
                'module' => 'PAYOUT',
                'file' => UploadedFile::fake()->create('sales-payout.png', 128, 'image/png'),
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
