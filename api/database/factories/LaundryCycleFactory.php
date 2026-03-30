<?php

namespace Database\Factories;

use App\Models\Hostel;
use App\Models\LaundryCycle;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LaundryCycle>
 */
class LaundryCycleFactory extends Factory
{
    protected $model = LaundryCycle::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $hostel = Hostel::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        return [
            'tenant_id' => $tenant->id,
            'hostel_id' => $hostel->id,
            'machine_label' => 'Machine-'.$this->faker->randomNumber(2),
            'status' => 'scheduled',
            'started_at' => now(),
        ];
    }
}
