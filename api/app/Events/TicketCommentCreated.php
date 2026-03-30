<?php

namespace App\Events;

use App\Domain\Tickets\Models\TicketComment;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketCommentCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public TicketComment $comment
    ) {}
}
