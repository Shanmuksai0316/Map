<?php

namespace Database\Factories;

use App\Models\ImportError;
use App\Models\ImportJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ImportError>
 */
class ImportErrorFactory extends Factory
{
    protected $model = ImportError::class;

    public function definition(): array
    {
        return [
            'import_job_id' => ImportJob::factory(),
            'row_number' => $this->faker->numberBetween(1, 100),
            'code' => 'INVALID_FIELD',
            'message' => $this->faker->sentence(6),
            'row_snapshot' => ['row' => $this->faker->words(5)],
        ];
    }
}
