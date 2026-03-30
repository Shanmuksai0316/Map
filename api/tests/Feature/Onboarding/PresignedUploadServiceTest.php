<?php

use App\Services\Uploads\PresignedUploadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresignedUploadServiceTest extends TestCase
{
    use RefreshDatabase;

    private PresignedUploadService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PresignedUploadService();
    }

    public function test_generates_presigned_url_successfully(): void
    {
        // Mock S3 configuration
        config(['filesystems.default' => 's3']);

        $result = $this->service->generatePresignedUrl(
            'test-directory',
            'test-image.jpg',
            'image/jpeg',
            2048000
        );

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('expires', $result);
        $this->assertStringContains('test-directory', $result['key']);
        $this->assertStringContains('test-image.jpg', $result['key']);
    }

    public function test_rejects_invalid_mime_type(): void
    {
        config(['filesystems.default' => 's3']);

        $result = $this->service->generatePresignedUrl(
            'test-directory',
            'test-file.exe',
            'application/octet-stream'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid file type', $result['message']);
    }

    public function test_rejects_oversized_files(): void
    {
        config(['filesystems.default' => 's3']);

        $result = $this->service->generatePresignedUrl(
            'test-directory',
            'test-image.jpg',
            'image/jpeg',
            60000000 // 60MB
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('File size too large', $result['message']);
    }

    public function test_requires_s3_configuration(): void
    {
        config(['filesystems.default' => 'local']);

        $result = $this->service->generatePresignedUrl(
            'test-directory',
            'test-image.jpg',
            'image/jpeg'
        );

        $this->assertFalse($result['success']);
        $this->assertEquals('File uploads not configured', $result['message']);
    }
}
