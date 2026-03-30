<?php

namespace Database\Factories\Domain\Checklists;

use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ChecklistTemplateFactory extends Factory
{
    protected $model = ChecklistTemplate::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $creator = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $tasks = collect(range(1, 3))->map(function (int $index) {
            $label = $this->faker->sentence(3);

            return [
                'code' => Str::slug("task-{$index}-{$label}"),
                'label' => $label,
                'required' => $index === 1,
            ];
        })->toArray();

        return [
            'tenant_id' => $tenant->id,
            'role' => $this->faker->randomElement([
                'Warden',
                'HK Supervisor',
                'RM Supervisor',
                'Guard',
            ]),
            'title' => $this->faker->sentence(3),
            'tasks' => $tasks,
            'active' => true,
            'created_by_user_id' => $creator->id,
        ];
    }
}

