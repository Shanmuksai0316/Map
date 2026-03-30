<?php

namespace Database\Factories;

use App\Models\ImportJob;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportJob>
 */
class ImportJobFactory extends Factory
{
    protected $model = ImportJob::class;

    public function definition(): array
    {
        return [
                        'kind' => $this->faker->randomElement(['students', 'room_allotments']),
            'status' => 'DryRun',
            'filename' => 'import-'.$this->faker->unique()->numerify('######').'.csv',
            'total_rows' => 0,
            'error_rows' => 0,
            'processed_rows' => 0,
            'inserted_rows' => 0,
            'updated_rows' => 0,
            'meta' => [],
        ];
    }
}
