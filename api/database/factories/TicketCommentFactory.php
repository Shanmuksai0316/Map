<?php

namespace Database\Factories;

use App\Domain\Tickets\Models\Ticket;
use App\Domain\Tickets\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCommentFactory extends Factory
{
    protected $model = TicketComment::class;

    public function definition(): array
    {
        return [
            'ticket_id' => Ticket::factory(),
            'user_id' => User::factory(),
            'body' => $this->faker->paragraphs(2, true),
            'attachments' => null,
            'is_internal' => false,
        ];
    }

    public function withAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'attachments' => [
                [
                    'filename' => $this->faker->word() . '.jpg',
                    'url' => $this->faker->imageUrl(),
                    'size' => $this->faker->numberBetween(1000, 5000000),
                ],
            ],
        ]);
    }
}
