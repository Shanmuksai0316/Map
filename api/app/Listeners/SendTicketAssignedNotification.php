<?php

namespace App\Listeners;

use App\Events\TicketAssigned;
use App\Services\Notify\PushNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTicketAssignedNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private PushNotifier $pushNotifier
    ) {}

    public function handle(TicketAssigned $event): void
    {
        $ticket = $event->ticket;
        $assignee = $ticket->assignee;

        if (!$assignee) {
            return;
        }

        try {
            $this->pushNotifier->toUser(
                $assignee->id,
                'New Ticket Assigned',
                "Ticket #{$ticket->id}: {$ticket->title}",
                [
                    'ticket_id' => $ticket->id,
                    'type' => 'ticket_assigned',
                ]
            );

            Log::info('Ticket assigned notification sent', [
                'ticket_id' => $ticket->id,
                'assignee_id' => $assignee->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send ticket assigned notification', [
                'ticket_id' => $ticket->id,
                'assignee_id' => $assignee->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
