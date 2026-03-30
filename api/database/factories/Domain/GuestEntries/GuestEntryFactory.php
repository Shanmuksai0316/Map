<?php

namespace Database\Factories\Domain\GuestEntries;

use App\Domain\GuestEntries\Models\GuestEntry;
use App\Models\Hostel;
use App\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GuestEntry>
 */
class GuestEntryFactory extends Factory
{
    protected $model = GuestEntry::class;

    public function definition(): array
    {
        $visitDate = $this->faker->dateTimeBetween('now', '+1 month');
        $checkInTime = $this->faker->time('H:i');
        $checkOutTime = (clone $visitDate)->modify('+6 hours')->format('H:i');

        $guests = [];
        $guestCount = $this->faker->numberBetween(1, 4);
        $relationships = ['Father', 'Mother', 'Brother', 'Sister', 'Uncle', 'Aunt', 'Grandfather', 'Grandmother'];
        $idTypes = ['aadhar_card', 'driving_license', 'passport', 'voter_id'];

        for ($i = 0; $i < $guestCount; $i++) {
            $guests[] = [
                'name' => $this->faker->name(),
                'phone' => $this->faker->phoneNumber(),
                'relationship' => $this->faker->randomElement($relationships),
                'id_type' => $this->faker->randomElement($idTypes),
                'id_number' => $this->faker->numerify('##############'),
            ];
        }

        return [
            'student_id' => Student::factory(),
            'hostel_id' => Hostel::factory(),
            'unique_id' => 'GST-' . strtoupper(Str::random(8)),
            'title' => 'Parents Visit',
            'description' => $this->faker->sentence(),
            'guests' => $guests,
            'primary_contact_mobile' => $guests[0]['phone'] ?? $this->faker->phoneNumber(),
            'visit_date' => $visitDate,
            'check_in_time' => $checkInTime,
            'check_out_time' => $checkOutTime,
            'purpose_to_visit' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['pending', 'approved', 'rejected']),
            'rejection_reason' => null,
            'approved_by' => null,
            'approved_at' => null,
            'submitted_at' => now(),
            'idempotency_key' => $this->faker->optional()->uuid(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'approved_by' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
            'approved_by' => $this->faker->numberBetween(1, 100),
            'approved_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => null,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
            'approved_by' => $this->faker->numberBetween(1, 100),
            'approved_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'rejection_reason' => $this->faker->sentence(),
        ]);
    }
}

