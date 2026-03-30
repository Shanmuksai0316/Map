<?php

namespace Database\Factories;

use App\Models\SportsEnrollment;
use App\Models\SportsEvent;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SportsEnrollment>
 */
class SportsEnrollmentFactory extends Factory
{
    protected $model = SportsEnrollment::class;

    public function definition(): array
    {
        return [
                        'sports_event_id' => SportsEvent::factory(),
            'student_id' => Student::factory(),
            'status' => 'registered',
            'enrolled_at' => now(),
        ];
    }
}
