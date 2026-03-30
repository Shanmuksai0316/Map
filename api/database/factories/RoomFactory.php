<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Room>
 */
class RoomFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'campus_id' => null,
            'hostel_id' => null,
            'block_code' => $this->faker->optional()->randomElement([
                'BL'.strtoupper($this->faker->randomLetter()),
                null,
            ]),
            'floor_code' => $this->faker->optional()->numerify('F#'),
            'number' => $this->faker->unique()->numerify('10#'),
            'capacity' => 4,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (\App\Models\Room $room) {
            if (!$room->tenant_id) {
                $room->tenant_id = app()->bound('testing.default_tenant_id')
                    ? app('testing.default_tenant_id')
                    : Tenant::factory()->create()->id;
            }

            if (!$room->hostel_id) {
                $hostel = Hostel::factory()->create([
                    'tenant_id' => $room->tenant_id,
                ]);

                $room->hostel_id = $hostel->id;
                $room->campus_id = $hostel->campus_id;
            } elseif (!$room->campus_id) {
                $hostel = Hostel::find($room->hostel_id);
                if ($hostel) {
                    $room->campus_id = $hostel->campus_id;
                }
            }

            if (!$room->campus_id) {
                $campus = Campus::factory()->create([
                    'tenant_id' => $room->tenant_id,
                ]);

                $room->campus_id = $campus->id;
            }
        });
    }
}
