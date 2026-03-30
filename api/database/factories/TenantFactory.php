<?php

namespace Database\Factories;

use App\Models\Tenant;
use App\Enums\TenantStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tenant>
 */
class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'code' => sprintf('MAP-%03d', $this->faker->unique()->numberBetween(1, 999)),
            'name' => 'Tenant '.$this->faker->unique()->numberBetween(1000, 9999),
            'status' => TenantStatus::PROVISIONING->value,
            'addon_security' => false,
            'addon_sports' => false,
            'addon_laundry' => false,
            'settings' => [],
        ];
    }

    /**
     * Configure the factory to create a domain after tenant creation
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Tenant $tenant) {
            // Generate subdomain from tenant code (lowercase, sanitized)
            $subdomain = Str::slug(strtolower($tenant->code));
            
            // Create domain for tenant
            $domainSuffix = env('APP_DOMAIN', 'yourapp.com');

            $domain = env('APP_ENV') === 'local' 
                ? $subdomain . '.localhost'
                : $subdomain . '.' . $domainSuffix;
            
            $tenant->domains()->create([
                'domain' => $domain,
            ]);
        });
    }

    public function active(): static
    {
        return $this->state(fn () => ['status' => TenantStatus::ACTIVE->value]);
    }
}
