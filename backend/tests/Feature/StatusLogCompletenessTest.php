<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\StatusLog;
use App\Models\Store;
use App\Models\User;
use App\Services\StatusLogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusLogCompletenessTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_log_service_records_operator_action_and_before_after_status(): void
    {
        $operator = User::factory()->create([
            'role' => UserRole::AUDITOR,
            'status' => 'ACTIVE',
        ]);
        $application = $this->application($operator);

        $log = app(StatusLogService::class)->record(
            application: $application,
            actor: $operator,
            action: 'TEST_ACTION',
            from: ApplicationStatus::PENDING_ASSIGNMENT,
            to: ApplicationStatus::ASSIGNED,
            message: '测试日志完整性。',
            metadata: ['remark' => '完整字段测试'],
        );

        $this->assertInstanceOf(StatusLog::class, $log);
        $this->assertDatabaseHas('status_logs', [
            'id' => $log->id,
            'application_id' => $application->id,
            'actor_user_id' => $operator->id,
            'actor_role' => UserRole::AUDITOR->value,
            'from_status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
            'to_status' => ApplicationStatus::ASSIGNED->value,
            'message' => '测试日志完整性。',
        ]);
        $this->assertSame('TEST_ACTION', $log->metadata['action']);
        $this->assertSame('完整字段测试', $log->metadata['remark']);
    }

    private function application(User $operator): Application
    {
        $store = Store::query()->create([
            'store_code' => 'STORE-LOG',
            'name' => '日志测试门店',
            'contact_name' => '日志测试联系人',
            'contact_phone' => '0900-LOG',
            'address' => '日志测试地址',
            'status' => 'ACTIVE',
        ]);

        return Application::query()->create([
            'application_no' => 'A202606080099',
            'source_type' => 'ADMIN_CREATE',
            'store_id' => $store->id,
            'created_by_user_id' => $operator->id,
            'current_owner_role' => UserRole::AUDITOR->value,
            'current_owner_user_id' => $operator->id,
            'status' => ApplicationStatus::PENDING_ASSIGNMENT,
            'customer_name' => '日志测试客户',
            'customer_phone' => '0900-LOG-CUSTOMER',
            'id_type' => 'NATIONAL_ID',
            'id_number' => 'LOG-ID',
            'customer_address' => '日志客户地址',
            'brand' => 'Apple',
            'model' => 'iPhone',
            'sale_price' => 1000,
            'loan_amount' => 800,
            'periods' => 12,
        ]);
    }
}
