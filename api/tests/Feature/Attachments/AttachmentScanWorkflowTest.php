<?php

namespace Tests\Feature\Attachments;

use App\Jobs\ProcessAttachment;
use App\Models\Attachment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AttachmentScanWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        
        Storage::fake('s3');
    }

    public function test_can_presign_upload_url()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/attachments/presign', [
                'filename' => 'test.jpg',
                'mime_type' => 'image/jpeg',
                'size' => 1024000,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'attachment_id',
                    'upload_url',
                    'key',
                    'expires_in',
                ]
            ]);

        $this->assertDatabaseHas('attachments', [
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
            'filename' => 'test.jpg',
            'status' => 'pending',
        ]);
    }

    public function test_rejects_invalid_mime_types()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/attachments/presign', [
                'filename' => 'malicious.exe',
                'mime_type' => 'application/x-executable',
                'size' => 1024000,
            ]);

        $response->assertStatus(422)
            ->assertJson(['error' => 'File type not allowed']);
    }

    public function test_rejects_oversized_files()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/attachments/presign', [
                'filename' => 'large.pdf',
                'mime_type' => 'application/pdf',
                'size' => 20 * 1024 * 1024, // 20MB
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['size']);
    }

    public function test_confirms_upload_and_queues_processing()
    {
        Queue::fake();

        $attachment = Attachment::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
            'status' => 'pending',
        ]);

        // Mock file exists in S3
        Storage::disk('s3')->put($attachment->key, 'fake content');

        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/attachments/confirm', [
                'attachment_id' => $attachment->id,
                'key' => $attachment->key,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'attachment_id' => $attachment->id,
                    'status' => 'uploaded',
                ]
            ]);

        // Check that processing job was queued
        Queue::assertPushed(ProcessAttachment::class, function ($job) use ($attachment) {
            return $job->attachment->id === $attachment->id;
        });

        $attachment->refresh();
        $this->assertEquals('uploaded', $attachment->status);
    }

    public function test_attachment_processing_workflow()
    {
        $attachment = Attachment::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'user_id' => $this->user->id,
            'status' => 'uploaded',
            'mime_type' => 'image/jpeg',
        ]);

        // Mock file in S3
        Storage::disk('s3')->put($attachment->key, 'fake image content');

        // Process the attachment
        $job = new ProcessAttachment($attachment);
        $job->handle();

        $attachment->refresh();
        $this->assertContains($attachment->status, ['clean', 'quarantined', 'failed']);
    }
}



