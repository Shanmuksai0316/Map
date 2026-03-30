<?php

namespace Tests\Feature\Laundry;

use App\Models\LaundryCycle;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Feature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LaundryLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->tenant = Tenant::factory()->create();
        
        // Create Laundry Manager user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'kind' => 'LaundryManager'
        ]);
        
        // Assign Laundry Manager role
        $laundryManagerRole = \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'Laundry Manager']);
        $this->user->assignRole($laundryManagerRole);
        
        Config::set('features.laundry_module', true);
    }

    public function test_can_create_laundry_cycle()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/v1/laundry/cycles', [
                'machine_label' => 'WASHER-001',
                'hostel_id' => 1,
                'metadata' => ['capacity' => 10],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'machine_label',
                    'status',
                    'tenant_id',
                ]
            ]);

        $this->assertDatabaseHas('laundry_cycles', [
            'tenant_id' => $this->user->tenant_id,
            'machine_label' => 'WASHER-001',
            'status' => 'scheduled',
        ]);
    }

    public function test_can_update_cycle_status()
    {
        $cycle = LaundryCycle::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => 'scheduled',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/laundry/cycles/{$cycle->id}/status", [
                'status' => 'ready',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'data' => [
                    'id' => $cycle->id,
                    'status' => 'ready',
                ]
            ]);

        $cycle->refresh();
        $this->assertEquals('ready', $cycle->status);
    }

    public function test_requires_note_for_manual_verify_status()
    {
        $cycle = LaundryCycle::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/laundry/cycles/{$cycle->id}/status", [
                'status' => 'manual_verify',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    public function test_can_set_manual_verify_with_note()
    {
        $cycle = LaundryCycle::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => 'ready',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/v1/laundry/cycles/{$cycle->id}/status", [
                'status' => 'manual_verify',
                'note' => 'Items need special attention',
            ]);

        $response->assertStatus(200);

        $cycle->refresh();
        $this->assertEquals('manual_verify', $cycle->status);
        $this->assertEquals('Items need special attention', $cycle->metadata['status_note']);
    }

    public function test_can_list_cycles_with_status_filter()
    {
        LaundryCycle::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => 'scheduled',
        ]);

        LaundryCycle::factory()->create([
            'tenant_id' => $this->user->tenant_id,
            'status' => 'completed',
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/laundry/cycles?status=scheduled');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'status', 'machine_label']
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('scheduled', $data[0]['status']);
    }

    public function test_returns_404_when_laundry_module_disabled()
    {
        Config::set('features.laundry_module', false);

        $response = $this->actingAs($this->user)
            ->getJson('/api/v1/laundry/cycles');

        $response->assertStatus(404);
    }

    public function test_can_delete_cycle()
    {
        $cycle = LaundryCycle::factory()->create([
            'tenant_id' => $this->user->tenant_id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/v1/laundry/cycles/{$cycle->id}");

        $response->assertStatus(200)
            ->assertJson(['message' => 'Laundry cycle deleted successfully']);

        $this->assertDatabaseMissing('laundry_cycles', ['id' => $cycle->id]);
    }
}