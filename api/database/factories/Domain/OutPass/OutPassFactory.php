<?php

namespace Database\Factories\Domain\OutPass;

use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OutPass>
 */
class OutPassFactory extends Factory
{
    protected $model = OutPass::class;

    public function definition(): array
    {
        $requestedAt = $this->faker->dateTimeBetween('-1 day', 'now');

        $student = Student::factory()->create();
        $hostelId = $student->hostel_id;

        if (!$hostelId) {
            $hostel = Hostel::factory()->create([
                'tenant_id' => $student->tenant_id,
            ]);
            $hostelId = $hostel->id;
            $student->update(['hostel_id' => $hostelId]);
        }

        return [
            'tenant_id' => $student->tenant_id,
            'student_id' => $student->id,
            'hostel_id' => $hostelId,
            'reason' => OutPassType::NORMAL,
            'overnight' => $this->faker->boolean,
            'status' => OutPassStatus::PENDING,
            'requested_at' => $requestedAt,
            'requested_for' => $requestedAt->format('Y-m-d'),
            'decided_at' => null,
            'valid_until' => $this->faker->dateTimeBetween('now', '+1 day'),
            'note' => $this->faker->sentence,
            'idempotency_key' => $this->faker->uuid,
        ];
    }
}
