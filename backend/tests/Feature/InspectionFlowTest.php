<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\InspectionTask;
use App\Models\SalesAgent;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InspectionFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_auditor_assigns_sales_agent_then_sales_starts_and_submits_inspection(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $sales = User::query()->where('username', 'sales001')->firstOrFail();
        $salesAgent = SalesAgent::query()->where('agent_code', 'SALES-001')->firstOrFail();
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $assign = $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/assign", [
                'salesAgentId' => $salesAgent->id,
                'remark' => '安排业务员 A 到店验机',
            ]);

        $assign
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::ASSIGNED->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::SALES->value)
            ->assertJsonPath('data.inspectionTask.salesAgentId', $salesAgent->id)
            ->assertJsonPath('data.inspectionTask.status', 'ASSIGNED');

        $taskId = $assign->json('data.inspectionTask.id');

        $this->assertDatabaseHas('inspection_tasks', [
            'id' => $taskId,
            'application_id' => $application->id,
            'sales_agent_id' => $salesAgent->id,
            'status' => 'ASSIGNED',
        ]);

        $this->actingAs($sales, 'sanctum')
            ->postJson("/api/v1/inspection-tasks/{$taskId}/start")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::INSPECTION_IN_PROGRESS->value)
            ->assertJsonPath('data.inspectionTask.status', 'IN_PROGRESS');

        $submit = $this->actingAs($sales, 'sanctum')
            ->postJson("/api/v1/inspection-tasks/{$taskId}/submit", [
                'inspectionResult' => 'PASS',
                'inspectionNote' => 'IMEI 与门店资料一致，外观轻微使用痕迹。',
                'attachments' => [
                    [
                        'fileName' => 'inspection-front.png',
                        'filePath' => 'demo/inspection/inspection-front.png',
                        'mimeType' => 'image/png',
                        'fileSize' => 256000,
                        'remark' => '机身正面照片',
                    ],
                ],
            ]);

        $submit
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PENDING_REVIEW->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::AUDITOR->value)
            ->assertJsonPath('data.inspectionTask.status', 'SUBMITTED')
            ->assertJsonPath('data.inspectionTask.inspectionNote', 'IMEI 与门店资料一致，外观轻微使用痕迹。')
            ->assertJsonPath('data.attachments.0.fileName', 'inspection-front.png');

        $this->assertDatabaseHas('status_logs', [
            'application_id' => $application->id,
            'to_status' => ApplicationStatus::PENDING_REVIEW->value,
        ]);
        $this->assertDatabaseHas('attachments', [
            'application_id' => $application->id,
            'module' => 'INSPECTION',
            'file_name' => 'inspection-front.png',
        ]);
    }

    public function test_sales_user_cannot_operate_another_sales_agent_task(): void
    {
        $this->seed(DemoSeeder::class);

        $sales = User::query()->where('username', 'sales001')->firstOrFail();
        $otherTask = InspectionTask::query()
            ->whereHas('salesAgent', fn ($query) => $query->where('agent_code', 'SALES-002'))
            ->where('status', 'IN_PROGRESS')
            ->firstOrFail();

        $this->actingAs($sales, 'sanctum')
            ->postJson("/api/v1/inspection-tasks/{$otherTask->id}/submit", [
                'inspectionResult' => 'PASS',
                'inspectionNote' => '越权提交',
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->actingAs($sales, 'sanctum')
            ->postJson("/api/v1/inspection-tasks/{$otherTask->id}/submit", [])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_non_auditor_cannot_assign_application(): void
    {
        $this->seed(DemoSeeder::class);

        $store = User::query()->where('username', 'store001')->firstOrFail();
        $salesAgent = SalesAgent::query()->where('agent_code', 'SALES-001')->firstOrFail();
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/assign", [
                'salesAgentId' => $salesAgent->id,
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_application_cannot_be_assigned_twice(): void
    {
        $this->seed(DemoSeeder::class);

        $auditor = User::query()->where('username', 'audit001')->firstOrFail();
        $salesAgent = SalesAgent::query()->where('agent_code', 'SALES-001')->firstOrFail();
        $application = Application::query()
            ->where('application_no', 'A20260530002')
            ->firstOrFail();

        $this->actingAs($auditor, 'sanctum')
            ->postJson("/api/v1/applications/{$application->id}/assign", [
                'salesAgentId' => $salesAgent->id,
            ])
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_task_cannot_be_submitted_twice(): void
    {
        $this->seed(DemoSeeder::class);

        $sales = User::query()->where('username', 'sales001')->firstOrFail();
        $submittedTask = InspectionTask::query()
            ->whereHas('application', fn ($query) => $query->where('application_no', 'A20260530004'))
            ->firstOrFail();

        $this->actingAs($sales, 'sanctum')
            ->postJson("/api/v1/inspection-tasks/{$submittedTask->id}/submit", [
                'inspectionResult' => 'PASS',
                'inspectionNote' => '重复提交',
            ])
            ->assertConflict()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
    }

    public function test_sales_can_reject_inspection_to_request_supplement(): void
    {
        $this->seed(DemoSeeder::class);

        $sales = User::query()->where('username', 'sales002')->firstOrFail();
        $task = InspectionTask::query()
            ->whereHas('application', fn ($query) => $query->where('application_no', 'A20260530003'))
            ->firstOrFail();

        $this->actingAs($sales, 'sanctum')
            ->postJson("/api/v1/inspection-tasks/{$task->id}/reject", [
                'reason' => '客户身份证照片不清晰，需要业务员补充资料。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::NEEDS_SUPPLEMENT->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::SALES->value)
            ->assertJsonPath('data.inspectionTask.status', 'REJECTED');
    }
}
