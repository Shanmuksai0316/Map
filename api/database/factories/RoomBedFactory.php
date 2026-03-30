<?php

namespace Database\Factories;

use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomBed>
 */
class RoomBedFactory extends Factory
{
    protected $model = RoomBed::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'room_id' => null,
            'hostel_id' => null,
            'code' => 'B'.strtoupper($this->faker->randomLetter()).$this->faker->numberBetween(1, 9),
            'status' => 'available',
            'occupied_at' => null,
            'released_at' => null,
            'meta' => [],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RoomBed $bed) {
            if (!$bed->tenant_id) {
                $bed->tenant_id = app()->bound('testing.default_tenant_id')
                    ? app('testing.default_tenant_id')
                    : Tenant::factory()->create()->id;
            }

            if (!$bed->room_id) {
                $room = Room::factory()->create([
                    'tenant_id' => $bed->tenant_id,
                ]);

                $bed->room_id = $room->id;
                $bed->hostel_id = $room->hostel_id;
            } elseif (!$bed->hostel_id) {
                $room = Room::find($bed->room_id);
                if ($room) {
                    $bed->hostel_id = $room->hostel_id;
                }
            }

            if (!$bed->hostel_id) {
                $hostel = Hostel::factory()->create([
                    'tenant_id' => $bed->tenant_id,
                ]);

                $room = Room::factory()->create([
                    'tenant_id' => $bed->tenant_id,
                    'hostel_id' => $hostel->id,
                    'campus_id' => $hostel->campus_id,
                ]);

                $bed->room_id = $room->id;
                $bed->hostel_id = $hostel->id;
            }
        });
    }
}
