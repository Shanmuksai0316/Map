<?php

namespace Database\Factories;

use App\Models\Notice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notice>
 */
class NoticeFactory extends Factory
{
    protected $model = Notice::class;

    public function definition(): array
    {
        $tenant = Tenant::factory()->create();
        $creator = User::factory()->create(['tenant_id' => $tenant->id]);

        return [
            'tenant_id' => $tenant->id,
            'created_by_user_id' => $creator->id,
            'title' => $this->faker->sentence(),
            'body' => $this->faker->paragraph(),
            'status' => 'draft',
            'audience' => 'all_students',
            'attachment_url' => null,
        ];
    }
}
