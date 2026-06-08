<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MerchantFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_merchant_can_read_only_own_vouchers(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $otherStore = User::query()->where('username', 'store002')->firstOrFail();

        $ownVoucher = DB::table('merchant_payment_vouchers')
            ->where('store_id', $store->store_id)
            ->first();
        $otherVoucher = DB::table('merchant_payment_vouchers')
            ->where('store_id', $otherStore->store_id)
            ->first();

        $this->assertNotNull($ownVoucher);
        $this->assertNotNull($otherVoucher);

        $items = $this->actingAs($store, 'sanctum')
            ->getJson('/api/v1/merchant/vouchers')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->json('data.items');

        $this->assertNotEmpty($items);
        $this->assertTrue(collect($items)->every(fn ($item) => $item['storeId'] === $store->store_id));

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/merchant/vouchers/{$ownVoucher->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.voucher.id', $ownVoucher->id);

        $this->actingAs($store, 'sanctum')
            ->getJson("/api/v1/merchant/vouchers/{$otherVoucher->id}")
            ->assertNotFound()
            ->assertJsonPath('success', false);
    }

    public function test_merchant_can_submit_onboarding_and_admin_can_review_it(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store002')->firstOrFail();
        $admin = User::query()->where('username', 'admin001')->firstOrFail();

        $submit = $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/merchant/onboarding', [
                'applicantName' => '李店长',
                'applicantPhone' => '0900-100-200',
                'applicantIdNumber' => 'ID-MERCHANT-001',
                'merchantName' => '南区测试门店',
                'merchantAddress' => '测试路 18 号',
                'contactName' => '李店长',
                'contactPhone' => '0900-100-200',
                'paymentMethod' => 'BANK',
                'paymentAccount' => '6222000011112222',
                'paymentAccountName' => '南区测试门店',
                'paymentBankOrChannel' => '测试银行',
                'idCardFrontFile' => [
                    'fileName' => 'id-front.png',
                    'filePath' => 'demo/merchant/id-front.png',
                    'mimeType' => 'image/png',
                    'fileSize' => 120000,
                ],
                'idCardBackFile' => [
                    'fileName' => 'id-back.png',
                    'filePath' => 'demo/merchant/id-back.png',
                    'mimeType' => 'image/png',
                    'fileSize' => 121000,
                ],
                'qualificationFile' => [
                    'fileName' => 'license.pdf',
                    'filePath' => 'demo/merchant/license.pdf',
                    'mimeType' => 'application/pdf',
                    'fileSize' => 240000,
                ],
            ]);

        $submit
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.onboarding.status', 'PENDING_REVIEW');

        $onboardingId = $submit->json('data.onboarding.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/merchants/{$onboardingId}/approve", [
                'note' => '资料齐全，通过入驻。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.onboarding.status', 'APPROVED');

        $this->actingAs($store, 'sanctum')
            ->getJson('/api/v1/merchant/me')
            ->assertOk()
            ->assertJsonPath('data.profile.onboardingStatus', 'APPROVED')
            ->assertJsonPath('data.profile.paymentAccountMasked', '6222********2222');
    }

    public function test_admin_can_create_and_void_merchant_voucher(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('username', 'admin001')->firstOrFail();
        $store = User::query()->where('username', 'store001')->firstOrFail();

        $create = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/merchant-vouchers', [
                'storeId' => $store->store_id,
                'relatedBusinessNo' => 'A202606080001',
                'amount' => 3215,
                'status' => 'PAID',
                'paidAt' => '2026-06-08T12:00:00Z',
                'payeeName' => '东区旗舰店',
                'payeeAccountMasked' => '6222********8888',
                'payerName' => '博远财务',
                'voucherFile' => [
                    'fileName' => 'merchant-voucher.png',
                    'filePath' => 'demo/merchant-voucher.png',
                    'mimeType' => 'image/png',
                    'fileSize' => 180000,
                ],
                'remark' => '公司已打款。',
            ]);

        $create
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.voucher.storeId', $store->store_id)
            ->assertJsonPath('data.voucher.status', 'PAID');

        $voucherId = $create->json('data.voucher.id');

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/merchant-vouchers/{$voucherId}/void", [
                'voidReason' => '测试作废。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.voucher.status', 'VOIDED')
            ->assertJsonPath('data.voucher.voidReason', '测试作废。');
    }
}
