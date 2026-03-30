<?php

namespace App\Events;

use App\Models\Student;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StudentActivated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Student $student
    ) {}

    /**
     * Get the channels the event should be broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            // No broadcasting for now - this is for internal event handling
        ];
    }
}