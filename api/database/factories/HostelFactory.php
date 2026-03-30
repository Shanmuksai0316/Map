<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hostel>
 */
class HostelFactory extends Factory
{
    protected $model = Hostel::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'campus_id' => null,
            'code' => sprintf('HST%03d', $this->faker->unique()->numberBetween(1, 999)),
            'name' => 'Hostel '.$this->faker->unique()->numberBetween(100, 999),
            'gender_mode' => $this->faker->randomElement(['Male', 'Female', 'Coed']),
            'curfew_time' => '22:00:00',
            'overnight_enabled' => false,
            'visiting_start' => '16:00:00',
            'visiting_end' => '19:00:00',
            'settings' => [],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Hostel $hostel) {
            if (!$hostel->tenant_id) {
                $hostel->tenant_id = app()->bound('testing.default_tenant_id')
                    ? app('testing.default_tenant_id')
                    : Tenant::factory()->create()->id;
            }

            if (!$hostel->campus_id) {
                $campus = Campus::where('tenant_id', $hostel->tenant_id)->first();
                if (!$campus) {
                    $campus = Campus::factory()->create([
                        'tenant_id' => $hostel->tenant_id,
                    ]);
                }

                $hostel->campus_id = $campus->id;
            }
        });
    }
}
