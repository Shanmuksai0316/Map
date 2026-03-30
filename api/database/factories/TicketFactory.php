<?php

namespace Database\Factories;

use App\Domain\Tickets\Models\Ticket;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    public function definition(): array
    {
        $categories = ['housekeeping', 'maintenance', 'security', 'laundry', 'other'];
        $priorities = ['low', 'medium', 'high'];
        $statuses = ['open', 'in_progress', 'on_hold', 'resolved', 'closed'];

        $hostel = Hostel::factory()->create();
        $creator = User::factory()->create();

        return [
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
            'location' => $hostel->name,
            'category' => $this->faker->randomElement($categories),
            'priority' => $this->faker->randomElement($priorities),
            'status' => $this->faker->randomElement($statuses),
            'reporter_student_id' => null,
            'reporter_user_id' => null,
            'assignee_user_id' => null,
            'photos' => null,
            'due_date' => $this->faker->dateTimeBetween('now', '+4 hours'),
            'sla_due_at' => $this->faker->dateTimeBetween('now', '+4 hours'),
            'closed_at' => null,
            'created_by_user_id' => $creator->id,
            'created_by' => $creator->id,
            'sla_deadline' => $this->faker->dateTimeBetween('now', '+4 hours'),
            'updated_by_user_id' => null,
            'title' => $this->faker->sentence(6),
            'description' => $this->faker->paragraphs(3, true),
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'closed_at' => null,
        ]);
    }

    public function inProgress(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'in_progress',
            'closed_at' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'resolved',
            'closed_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
            'closed_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
        ]);
    }

    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 'high',
        ]);
    }

    public function housekeeping(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'housekeeping',
        ]);
    }

    public function maintenance(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'maintenance',
        ]);
    }

    public function security(): static
    {
        return $this->state(fn (array $attributes) => [
            'category' => 'security',
        ]);
    }

    public function withStudentReporter(): static
    {
        return $this->state(function (array $attributes) {
            $student = Student::factory()->create();
            return [
                'reporter_student_id' => $student->id,
                'reporter_user_id' => null,
            ];
        });
    }

    public function withStaffReporter(): static
    {
        return $this->state(function (array $attributes) {
            $user = User::factory()->create();
            return [
                'reporter_student_id' => null,
                'reporter_user_id' => $user->id,
            ];
        });
    }

    public function withAssignee(): static
    {
        return $this->state(function (array $attributes) {
            $user = User::factory()->create();
            return [
                'assignee_user_id' => $user->id,
            ];
        });
    }

    public function breached(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('-2 days', '-1 hour'),
            'sla_due_at' => $this->faker->dateTimeBetween('-2 days', '-1 hour'),
        ]);
    }

    public function withinSla(): static
    {
        return $this->state(fn (array $attributes) => [
            'due_date' => $this->faker->dateTimeBetween('now', '+4 hours'),
            'sla_due_at' => $this->faker->dateTimeBetween('now', '+4 hours'),
        ]);
    }
}
