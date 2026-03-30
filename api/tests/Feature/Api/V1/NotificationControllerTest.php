<?php

namespace Tests\Feature\Api\V1;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NotificationControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_user_can_get_unread_count(): void
    {
        Sanctum::actingAs($this->user);

        // Create some unread notifications
        DatabaseNotification::factory()->count(5)->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'read_at' => null,
        ]);

        // Create some read notifications
        DatabaseNotification::factory()->count(3)->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'read_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/notifications/unread-count');

        $response->assertOk()
            ->assertJsonPath('data.count', 5);
    }

    public function test_user_can_list_notifications(): void
    {
        Sanctum::actingAs($this->user);

        DatabaseNotification::factory()->count(10)->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/notifications');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'type', 'data', 'read_at', 'created_at'],
                ],
            ]);
    }

    public function test_user_can_mark_notification_as_read(): void
    {
        Sanctum::actingAs($this->user);

        $notification = DatabaseNotification::factory()->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'read_at' => null,
        ]);

        $response = $this->postJson("/api/v1/notifications/{$notification->id}/mark-as-read");

        $response->assertOk();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_notifications_as_read(): void
    {
        Sanctum::actingAs($this->user);

        DatabaseNotification::factory()->count(5)->create([
            'notifiable_type' => User::class,
            'notifiable_id' => $this->user->id,
            'read_at' => null,
        ]);

        $response = $this->postJson('/api/v1/notifications/mark-all-as-read');

        $response->assertOk();

        $unreadCount = $this->user->notifications()->whereNull('read_at')->count();
        $this->assertEquals(0, $unreadCount);
    }

    public function test_unauthenticated_user_cannot_access_notifications(): void
    {
        $response = $this->getJson('/api/v1/notifications');

        $response->assertUnauthorized();
    }
}

