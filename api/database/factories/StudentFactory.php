<?php

namespace Database\Factories;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            // tenant_id/user_id/hostel_id resolved in configure()
            'map_student_id' => 'STD-'.$this->faker->unique()->numerify('########'),
            'student_uid' => $this->faker->unique()->uuid(),
            'roll_no' => $this->faker->optional()->numerify('RN###'),
            'program' => $this->faker->optional()->randomElement(['B.Tech', 'MBA', 'BSc']),
            'gender' => $this->faker->randomElement(['Male', 'Female']),
            'year_of_study' => $this->faker->optional()->numberBetween(1, 4),
            'admission_year' => $this->faker->optional()->numberBetween(2018, 2025),
            'guardian' => [
                'name' => $this->faker->name(),
                'phone' => $this->faker->e164PhoneNumber(),
            ],
            'medical_notes' => [],
            'correspondence_address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
            ],
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Student $student): void {
            $tenantId = $student->tenant_id;

            if (! $tenantId) {
                $tenantId = $this->resolveTenantId();
                $student->tenant_id = $tenantId;
            }

            if (! $student->user_id) {
                $student->user_id = User::factory()->create([
                    'tenant_id' => $tenantId,
                    'kind' => 'student',
                ])->id;
            }

            if (! $student->hostel_id) {
                $hostel = Hostel::factory()->create([
                    'tenant_id' => $tenantId,
                ]);

                $student->hostel_id = $hostel->id;
            }
        });
    }

    // forTenant method removed - automatic isolation by database

    public function makeUser(string $name, ?string $phone = null): User
    {
        // Get current tenant from tenancy context
        $tenant = tenancy()->tenant;
        
        // If no tenant context, create a new tenant
        if (!$tenant) {
            $tenant = Tenant::factory()->create();
        }
        
        return User::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => $name,
            'phone' => $phone ?? '+91987654' . rand(1000, 9999), // Generate valid phone number
            'kind' => 'student',
        ]);
    }

    protected function resolveTenantId(): string
    {
        if (function_exists('tenant') && tenant()) {
            return tenant()->id;
        }

        if (app()->bound('testing.default_tenant_id')) {
            return app('testing.default_tenant_id');
        }

        return Tenant::query()->value('id') ?? Tenant::factory()->create()->id;
    }
}
