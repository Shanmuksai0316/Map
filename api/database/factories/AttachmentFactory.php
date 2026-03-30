<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
                        'user_id' => \App\Models\User::factory(),
            'filename' => $this->faker->fileExtension(),
            'mime_type' => $this->faker->mimeType(),
            'size' => $this->faker->numberBetween(1000, 1000000),
            'key' => 'attachments/' . $this->faker->uuid() . '/' . $this->faker->fileExtension(),
            'status' => 'clean',
        ];
    }
}
