<?php

namespace App\Listeners;

use App\Events\TicketCommentCreated;
use App\Services\Notify\PushNotifier;
use App\Services\Notifications\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendTicketCommentNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private PushNotifier $pushNotifier,
        private NotificationRecipients $recipients
    ) {}

    public function handle(TicketCommentCreated $event): void
    {
        $comment = $event->comment;
        $ticket = $comment->ticket;

        // Get participants (reporter + assignee, excluding comment author)
        $participants = collect([
            $ticket->createdBy,
            $ticket->assignee,
        ])->filter()
         ->reject(fn($user) => $user->id === $comment->user_id)
         ->unique('id');

        foreach ($participants as $participant) {
            try {
                $this->pushNotifier->toUser(
                    $participant->id,
                    'New Comment on Ticket',
                    "Ticket #{$ticket->id}: New comment from {$comment->user->name}",
                    [
                        'ticket_id' => $ticket->id,
                        'comment_id' => $comment->id,
                        'type' => 'ticket_comment',
                    ]
                );

                Log::info('Ticket comment notification sent', [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'recipient_id' => $participant->id,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send ticket comment notification', [
                    'ticket_id' => $ticket->id,
                    'comment_id' => $comment->id,
                    'recipient_id' => $participant->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify HK / RM Supervisors about comment updates on tickets in their area
        try {
            $tenantId = (string) $ticket->tenant_id;
            $hostelId = $ticket->hostel_id ? (int) $ticket->hostel_id : null;

            if ($hostelId) {
                // HK / RM Supervisors (no longer notifying Warden for comments)
                if ($ticket->category === 'housekeeping') {
                    $hkSupervisor = $this->recipients->hkSupervisorForHostel($tenantId, $hostelId);
                    if ($hkSupervisor && $hkSupervisor->id !== $comment->user_id) {
                        $this->pushNotifier->toUserTemplate(
                            $hkSupervisor->id,
                            'hk_supervisor.request_comment_added',
                            [
                                'request_id'      => $ticket->id,
                                'comment_author'  => $comment->user->name,
                                'comment_snippet' => mb_substr($comment->body ?? '', 0, 120),
                            ],
                            [
                                'type'       => 'request_comment',
                                'ticket_id'  => $ticket->id,
                                'comment_id' => $comment->id,
                            ]
                        );
                    }
                } else {
                    $rmSupervisor = $this->recipients->rmSupervisorForHostel($tenantId, $hostelId);
                    if ($rmSupervisor && $rmSupervisor->id !== $comment->user_id) {
                        $this->pushNotifier->toUserTemplate(
                            $rmSupervisor->id,
                            'rm_supervisor.request_comment_added',
                            [
                                'request_id'      => $ticket->id,
                                'comment_author'  => $comment->user->name,
                                'comment_snippet' => mb_substr($comment->body ?? '', 0, 120),
                            ],
                            [
                                'type'       => 'request_comment',
                                'ticket_id'  => $ticket->id,
                                'comment_id' => $comment->id,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('SendTicketCommentNotification: failed to send warden / hk_supervisor request_comment_added push', [
                'ticket_id' => $ticket->id,
                'comment_id' => $comment->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
