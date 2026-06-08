<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\PayoutRecord;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewAndPayoutFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_approves_application_then_cashier_confirms_payout(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $cashier = User::query()->where('username', 'cashier001')->firstOrFail();
        $application = $this->application('A20260530004');

        $approve = $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/approve", [
                'note' => '资料齐全，审核通过。',
            ]);

        $approve
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PENDING_PAYOUT->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::CASHIER->value)
            ->assertJsonPath('data.payoutRecord.status', 'PENDING')
            ->assertJsonPath('data.reviewRecord.action', 'APPROVE');

        $payoutId = $approve->json('data.payoutRecord.id');

        $this->actingAs($cashier, 'sanctum')
            ->getJson('/api/v1/payouts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'id' => $payoutId,
                'status' => 'PENDING',
            ]);

        $confirm = $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/v1/payouts/{$payoutId}/confirm", [
                'amount' => 5500,
                'paidAt' => '2026-05-30T12:00:00Z',
                'voucher' => [
                    'fileName' => 'payout-voucher.png',
                    'filePath' => 'demo/payout/payout-voucher.png',
                    'mimeType' => 'image/png',
                    'fileSize' => 168000,
                    'remark' => '打款凭证',
                ],
                'remark' => '已打款给门店。',
            ]);

        $confirm
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PAID->value)
            ->assertJsonPath('data.payoutRecord.status', 'PAID')
            ->assertJsonPath('data.payoutRecord.voucher.fileName', 'payout-voucher.png');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::PAID->value,
        ]);
        $this->assertDatabaseHas('review_records', [
            'application_id' => $application->id,
            'action' => 'APPROVE',
        ]);
        $this->assertDatabaseHas('status_logs', [
            'application_id' => $application->id,
            'to_status' => ApplicationStatus::PAID->value,
        ]);
    }

    public function test_auditor_rejects_application(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $application = $this->application('A20260530004');

        $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/reject", [
                'note' => '资料不符合放款要求。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::REJECTED->value)
            ->assertJsonPath('data.reviewRecord.action', 'REJECT');

        $this->assertDatabaseHas('review_records', [
            'application_id' => $application->id,
            'action' => 'REJECT',
            'to_status' => ApplicationStatus::REJECTED->value,
        ]);
    }

    public function test_store_cannot_be_assigned_or_submit_application_supplement(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $store = User::query()->where('username', 'store001')->firstOrFail();
        $application = $this->application('A20260530004');

        $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/request-supplement", [
                'ownerRole' => UserRole::STORE->value,
                'note' => '请门店补充客户地址证明。',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->actingAs($store, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/supplement", [
                'note' => '已补充客户地址证明。',
                'attachments' => [
                    [
                        'fileName' => 'address-proof.png',
                        'filePath' => 'demo/supplement/address-proof.png',
                        'mimeType' => 'image/png',
                        'fileSize' => 88000,
                    ],
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_sales_supplement_can_only_be_submitted_by_current_owner_user(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $ownerSales = User::query()->where('username', 'sales001')->firstOrFail();
        $application = $this->application('A20260530004');

        $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/request-supplement", [
                'ownerRole' => UserRole::SALES->value,
                'note' => '请业务员补充验机照片。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::NEEDS_SUPPLEMENT->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::SALES->value)
            ->assertJsonPath('data.application.currentOwnerUserId', $ownerSales->id);

        $sameAgentOtherUser = User::factory()->create([
            'username' => 'sales001-shadow',
            'display_name' => '同业务员档案的其他账号',
            'role' => UserRole::SALES->value,
            'store_id' => null,
            'sales_agent_id' => $ownerSales->sales_agent_id,
            'status' => 'ACTIVE',
        ]);

        $payload = [
            'note' => '已补充验机照片。',
            'attachments' => [
                [
                    'fileName' => 'inspection-extra.png',
                    'filePath' => 'demo/supplement/inspection-extra.png',
                    'mimeType' => 'image/png',
                    'fileSize' => 99000,
                ],
            ],
        ];

        $this->actingAs($sameAgentOtherUser, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/supplement", $payload)
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->actingAs($ownerSales, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/supplement", $payload)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PENDING_REVIEW->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::AUDITOR->value)
            ->assertJsonPath('data.attachments.0.fileName', 'inspection-extra.png');
    }

    public function test_non_cashier_cannot_confirm_payout(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $payout = PayoutRecord::query()
            ->whereHas('application', fn ($query) => $query->where('application_no', 'A20260530007'))
            ->firstOrFail();

        $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/payouts/{$payout->id}/confirm", [
                'amount' => 8800,
                'voucher' => [
                    'fileName' => 'blocked.png',
                    'filePath' => 'demo/payout/blocked.png',
                ],
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_payout_cannot_be_confirmed_above_application_loan_amount(): void
    {
        $this->seed(DemoSeeder::class);

        $cashier = User::query()->where('username', 'cashier001')->firstOrFail();
        $payout = PayoutRecord::query()
            ->whereHas('application', fn ($query) => $query->where('application_no', 'A20260530007'))
            ->firstOrFail();
        $overLimitAmount = (float) $payout->application->loan_amount + 1;

        $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/v1/payouts/{$payout->id}/confirm", [
                'amount' => $overLimitAmount,
                'voucher' => [
                    'fileName' => 'over-limit.png',
                    'filePath' => 'demo/payout/over-limit.png',
                ],
                'remark' => '超过贷款金额的打款应被拒绝。',
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseHas('payout_records', [
            'id' => $payout->id,
            'status' => 'PENDING',
        ]);
    }

    public function test_payout_cannot_be_confirmed_twice(): void
    {
        $this->seed(DemoSeeder::class);

        $cashier = User::query()->where('username', 'cashier001')->firstOrFail();
        $payout = PayoutRecord::query()
            ->whereHas('application', fn ($query) => $query->where('application_no', 'A20260530007'))
            ->firstOrFail();

        $payload = [
            'amount' => 8800,
            'voucher' => [
                'fileName' => 'payout-voucher.png',
                'filePath' => 'demo/payout/payout-voucher.png',
            ],
            'remark' => '已打款。',
        ];

        $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/v1/payouts/{$payout->id}/confirm", $payload)
            ->assertOk()
            ->assertJsonPath('data.payoutRecord.status', 'PAID');

        $this->actingAs($cashier, 'sanctum')
            ->postJson("/api/v1/payouts/{$payout->id}/confirm", $payload)
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    private function application(string $applicationNo): Application
    {
        return Application::query()
            ->where('application_no', $applicationNo)
            ->firstOrFail();
    }
}
