<?php

namespace Database\Factories;

use App\Models\Campus;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Campus>
 */
class CampusFactory extends Factory
{
    protected $model = Campus::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'code' => sprintf('CMP%03d', $this->faker->unique()->numberBetween(1, 999)),
            'name' => 'Campus '.$this->faker->unique()->numberBetween(100, 999),
            'address' => [
                'line1' => $this->faker->streetAddress(),
                'city' => $this->faker->city(),
                'state' => $this->faker->stateAbbr(),
                'pincode' => $this->faker->postcode(),
            ],
        ];
    }

    public function forTenant(Tenant $tenant): static
    {
        return $this->state(fn (): array => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
