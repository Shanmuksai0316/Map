<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(), // Users are in central database - need tenant_id
            'phone' => $this->faker->unique()->e164PhoneNumber(),
            'name' => $this->faker->name(),
            'email' => null, // Email is optional - phone/OTP authentication is primary
            'kind' => 'student',
            'archived' => false,
            'archived_at' => null,
        ];
    }

    public function student(): static
    {
        return $this->state(fn (): array => ['kind' => 'student'])
            ->afterCreating(function (User $user): void {
                Student::factory()->create([
                                        'user_id' => $user->id,
                ]);
            });
    }

    public function rector(): static
    {
        return $this->state(fn (): array => ['kind' => 'rector']);
    }

    public function campusManager(): static
    {
        return $this->state(fn (): array => ['kind' => 'campusmanager']);
    }

    public function guard(): static
    {
        return $this->state(fn (): array => ['kind' => 'guard']);
    }

    public function warden(): static
    {
        return $this->state(fn (): array => ['kind' => 'warden']);
    }

    public function superAdmin(): static
    {
        return $this->state(fn (): array => ['kind' => 'superadmin']);
    }
}
