<?php

namespace Tests\Feature;

use App\Enums\ApplicationStatus;
use App\Enums\UserRole;
use App\Models\Application;
use App\Models\Attachment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_view_demo_accounts(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = $this->user('admin001');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/admin/accounts')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonFragment([
                'username' => 'store001',
                'role' => UserRole::STORE->value,
                'name' => '东区旗舰店',
                'status' => 'ACTIVE',
            ])
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'username',
                            'role',
                            'name',
                            'status',
                            'store',
                            'salesAgent',
                        ],
                    ],
                ],
                'requestId',
            ]);
    }

    public function test_non_super_admin_cannot_view_accounts_or_reset_demo_data(): void
    {
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');

        $this->actingAs($store, 'sanctum')
            ->getJson('/api/v1/admin/accounts')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/admin/reset-demo-data', ['confirm' => true])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    public function test_super_admin_can_reset_demo_data_repeatedly(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = $this->user('admin001');
        Application::query()->where('application_no', 'A20260530001')->delete();

        $firstReset = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/reset-demo-data', ['confirm' => true]);

        $firstReset
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reset', true)
            ->assertJsonPath('data.accountsRestored', 7)
            ->assertJsonPath('data.applicationsRestored', 8);

        $this->assertDatabaseHas('applications', [
            'application_no' => 'A20260530001',
            'status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
        ]);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/v1/admin/reset-demo-data', ['confirm' => true])
            ->assertOk()
            ->assertJsonPath('data.reset', true)
            ->assertJsonPath('data.accountsRestored', 7)
            ->assertJsonPath('data.applicationsRestored', 8);
    }

    public function test_super_admin_manual_status_change_updates_application_and_writes_log(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = $this->user('admin001');
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/applications/{$application->id}/status", [
                'status' => ApplicationStatus::PENDING_REVIEW->value,
                'currentOwnerRole' => UserRole::AUDITOR->value,
                'currentOwnerUserId' => $this->user('audit001')->id,
                'remark' => '路演手动调整到待审核。',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.application.status', ApplicationStatus::PENDING_REVIEW->value)
            ->assertJsonPath('data.application.currentOwnerRole', UserRole::AUDITOR->value);

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::PENDING_REVIEW->value,
            'current_owner_role' => UserRole::AUDITOR->value,
            'current_owner_user_id' => $this->user('audit001')->id,
        ]);

        $this->assertDatabaseHas('status_logs', [
            'application_id' => $application->id,
            'actor_user_id' => $admin->id,
            'actor_role' => UserRole::SUPER_ADMIN->value,
            'from_status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
            'to_status' => ApplicationStatus::PENDING_REVIEW->value,
            'message' => '路演手动调整到待审核。',
        ]);
    }

    public function test_authenticated_user_can_upload_attachment(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $response = $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/attachments', [
                'applicationId' => $application->id,
                'module' => 'APPLICATION',
                'remark' => '客户证件照',
                'file' => UploadedFile::fake()->create('customer-id.png', 128, 'image/png'),
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attachment.fileName', 'customer-id.png')
            ->assertJsonPath('data.attachment.mimeType', 'image/png')
            ->assertJsonPath('data.attachment.module', 'APPLICATION')
            ->assertJsonPath('data.attachment.remark', '客户证件照');

        $attachment = Attachment::query()->where('file_name', 'customer-id.png')->firstOrFail();

        Storage::disk('public')->assertExists($attachment->file_path);
    }

    public function test_attachment_upload_rejects_illegal_mime_type(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/attachments', [
                'applicationId' => $application->id,
                'module' => 'APPLICATION',
                'file' => UploadedFile::fake()->create('script.exe', 8, 'application/x-msdownload'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseMissing('attachments', [
            'file_name' => 'script.exe',
        ]);
    }

    public function test_store_cannot_upload_attachment_to_other_store_application(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');
        $otherStoreApplication = Application::query()
            ->where('application_no', 'A20260530002')
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/attachments', [
                'applicationId' => $otherStoreApplication->id,
                'module' => 'APPLICATION',
                'file' => UploadedFile::fake()->create('other-store.png', 128, 'image/png'),
            ])
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');

        $this->assertDatabaseMissing('attachments', [
            'file_name' => 'other-store.png',
        ]);
    }

    public function test_attachment_upload_rejects_invalid_module(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);

        $store = $this->user('store001');
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $this->actingAs($store, 'sanctum')
            ->postJson('/api/v1/attachments', [
                'applicationId' => $application->id,
                'module' => 'RANDOM_MODULE',
                'file' => UploadedFile::fake()->create('customer-id.png', 128, 'image/png'),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseMissing('attachments', [
            'module' => 'RANDOM_MODULE',
        ]);
    }

    public function test_super_admin_status_change_rejects_mismatched_owner_user(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = $this->user('admin001');
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/applications/{$application->id}/status", [
                'status' => ApplicationStatus::PENDING_REVIEW->value,
                'currentOwnerRole' => UserRole::AUDITOR->value,
                'currentOwnerUserId' => $this->user('store001')->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
        ]);
    }

    public function test_super_admin_terminal_status_requires_empty_owner(): void
    {
        $this->seed(DemoSeeder::class);

        $admin = $this->user('admin001');
        $application = Application::query()
            ->where('application_no', 'A20260530001')
            ->firstOrFail();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/v1/admin/applications/{$application->id}/status", [
                'status' => ApplicationStatus::PAID->value,
                'currentOwnerRole' => UserRole::AUDITOR->value,
                'currentOwnerUserId' => $this->user('audit001')->id,
            ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'VALIDATION_ERROR');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => ApplicationStatus::PENDING_ASSIGNMENT->value,
        ]);
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }
}
