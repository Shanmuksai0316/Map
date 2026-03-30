<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomAllocation>
 */
class RoomAllocationFactory extends Factory
{
    protected $model = RoomAllocation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => null,
            'room_bed_id' => null,
            'hostel_id' => null,
            'effective_from' => now(),
            'expected_checkout_at' => now()->addMonths(6),
            'is_active' => true,
            'note' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (RoomAllocation $allocation) {
            if (!$allocation->tenant_id) {
                $allocation->tenant_id = app()->bound('testing.default_tenant_id')
                    ? app('testing.default_tenant_id')
                    : Tenant::factory()->create()->id;
            }

            if (!$allocation->room_bed_id) {
                $bed = RoomBed::factory()->create([
                    'tenant_id' => $allocation->tenant_id,
                ]);

                $allocation->room_bed_id = $bed->id;
                $allocation->hostel_id = $bed->hostel_id;
            } elseif (!$allocation->hostel_id) {
                $bed = RoomBed::find($allocation->room_bed_id);
                if ($bed) {
                    $allocation->hostel_id = $bed->hostel_id;
                }
            }

            if (!$allocation->student_id) {
                $student = Student::factory()->create([
                    'tenant_id' => $allocation->tenant_id,
                ]);

                $allocation->student_id = $student->id;
            }
        });
    }
}
