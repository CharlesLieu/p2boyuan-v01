<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Attachment;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_list_application_attachments(): void
    {
        $this->seed(DemoSeeder::class);
        $application = $this->application('A20260530001');

        $this->actingAs($this->user('audit001'), 'sanctum')
            ->getJson("/api/v1/applications/{$application->id}/attachments")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'applicationId',
                            'module',
                            'fileName',
                            'filePath',
                            'mimeType',
                            'fileSize',
                            'uploaderId',
                            'uploaderName',
                            'uploaderRole',
                            'createdAt',
                        ],
                    ],
                ],
            ]);
    }

    public function test_authorized_user_can_get_attachment_detail(): void
    {
        $this->seed(DemoSeeder::class);
        $attachment = Attachment::query()->firstOrFail();

        $this->actingAs($this->user('audit001'), 'sanctum')
            ->getJson("/api/v1/attachments/{$attachment->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.attachment.id', $attachment->id)
            ->assertJsonPath('data.attachment.uploaderName', '东区旗舰店');
    }

    public function test_authorized_user_can_get_attachment_download_url(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $attachment = Attachment::query()->firstOrFail();
        Storage::disk('public')->put($attachment->file_path, 'demo-file-content');

        $this->actingAs($this->user('audit001'), 'sanctum')
            ->getJson("/api/v1/attachments/{$attachment->id}/download")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.fileName', $attachment->file_name)
            ->assertJsonPath('data.mimeType', $attachment->mime_type)
            ->assertJsonStructure([
                'data' => [
                    'url',
                    'downloadUrl',
                    'exists',
                ],
            ]);
    }

    public function test_store_cannot_list_application_attachments(): void
    {
        $this->seed(DemoSeeder::class);
        $application = $this->application('A20260530001');

        $this->actingAs($this->user('store001'), 'sanctum')
            ->getJson("/api/v1/applications/{$application->id}/attachments")
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'AUTH_FORBIDDEN');
    }

    private function user(string $username): User
    {
        return User::query()->where('username', $username)->firstOrFail();
    }

    private function application(string $applicationNo): Application
    {
        return Application::query()->where('application_no', $applicationNo)->firstOrFail();
    }
}
