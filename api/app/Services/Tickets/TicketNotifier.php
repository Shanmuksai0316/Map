<?php

namespace App\Services\Tickets;

use App\Domain\Tickets\Models\Ticket;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use Illuminate\Support\Facades\Log;

class TicketNotifier
{
    public function onCreated(Ticket $ticket): void
    {
        if (!config('notifiers.tickets.enabled', true)) {
            return;
        }

        Log::info('Ticket created notification', [
            'ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'hostel_id' => $ticket->hostel_id,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'reporter_user_id' => $ticket->reporter_user_id,
            'reporter_student_id' => $ticket->reporter_student_id,
            'title' => $ticket->title,
            'sla_due_at' => $ticket->sla_due_at?->toIso8601String(),
        ]);

        // Notify Warden / HK Supervisor about new request in their area
        try {
            $recipients = app(NotificationRecipients::class);
            $push       = app(PushNotifier::class);

            $tenantId = (string) $ticket->tenant_id;
            $hostelId = $ticket->hostel_id ? (int) $ticket->hostel_id : null;

            if ($hostelId) {
                // Warden
                $warden = $recipients->wardenForHostel($tenantId, $hostelId);
                if ($warden) {
                    $push->toUserTemplate(
                        $warden->id,
                        'warden.request_created',
                        [
                            'request_id' => $ticket->id,
                            'summary'    => $ticket->title,
                        ],
                        [
                            'type'      => 'request_created',
                            'ticket_id' => $ticket->id,
                        ]
                    );
                }

                // HK Supervisor (housekeeping only)
                if ($ticket->category === 'housekeeping') {
                    $hkSupervisor = $recipients->hkSupervisorForHostel($tenantId, $hostelId);
                    if ($hkSupervisor) {
                        $push->toUserTemplate(
                            $hkSupervisor->id,
                            'hk_supervisor.request_created',
                            [
                                'request_id' => $ticket->id,
                                'summary'    => $ticket->title,
                            ],
                            [
                                'type'      => 'request_created',
                                'ticket_id' => $ticket->id,
                            ]
                        );
                    }
                } else {
                    // RM Supervisor (all non-housekeeping categories)
                    $rmSupervisor = $recipients->rmSupervisorForHostel($tenantId, $hostelId);
                    if ($rmSupervisor) {
                        $push->toUserTemplate(
                            $rmSupervisor->id,
                            'rm_supervisor.request_created',
                            [
                                'request_id' => $ticket->id,
                                'summary'    => $ticket->title,
                            ],
                            [
                                'type'      => 'request_created',
                                'ticket_id' => $ticket->id,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TicketNotifier.onCreated: failed to send warden / hk_supervisor request_created push', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function onAssigned(Ticket $ticket, ?int $oldAssigneeId = null): void
    {
        if (!config('notifiers.tickets.enabled', true)) {
            return;
        }

        Log::info('Ticket assigned notification', [
            'ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'hostel_id' => $ticket->hostel_id,
            'old_assignee_id' => $oldAssigneeId,
            'new_assignee_id' => $ticket->assignee_user_id,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'title' => $ticket->title,
        ]);
    }

    public function onResolved(Ticket $ticket, string $oldStatus): void
    {
        if (!config('notifiers.tickets.enabled', true)) {
            return;
        }

        Log::info('Ticket resolved notification', [
            'ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'hostel_id' => $ticket->hostel_id,
            'old_status' => $oldStatus,
            'new_status' => $ticket->status,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'title' => $ticket->title,
            'resolved_at' => $ticket->closed_at?->toIso8601String(),
        ]);

        // Notify Warden / HK / RM Supervisors of status change (resolved)
        try {
            $recipients = app(NotificationRecipients::class);
            $push       = app(PushNotifier::class);

            $tenantId = (string) $ticket->tenant_id;
            $hostelId = $ticket->hostel_id ? (int) $ticket->hostel_id : null;

            if ($hostelId) {
                // Warden
                $warden = $recipients->wardenForHostel($tenantId, $hostelId);
                if ($warden) {
                    $push->toUserTemplate(
                        $warden->id,
                        'warden.request_status_changed',
                        [
                            'request_id'   => $ticket->id,
                            'status_label' => ucfirst($ticket->status),
                            'summary'      => $ticket->title,
                        ],
                        [
                            'type'      => 'request_status_changed',
                            'ticket_id' => $ticket->id,
                        ]
                    );
                }

                // HK Supervisor (housekeeping)
                if ($ticket->category === 'housekeeping') {
                    $hkSupervisor = $recipients->hkSupervisorForHostel($tenantId, $hostelId);
                    if ($hkSupervisor) {
                        $push->toUserTemplate(
                            $hkSupervisor->id,
                            'hk_supervisor.request_status_changed',
                            [
                                'request_id'   => $ticket->id,
                                'status_label' => ucfirst($ticket->status),
                                'summary'      => $ticket->title,
                            ],
                            [
                                'type'      => 'request_status_changed',
                                'ticket_id' => $ticket->id,
                            ]
                        );
                    }
                } else {
                    // RM Supervisor (non-housekeeping)
                    $rmSupervisor = $recipients->rmSupervisorForHostel($tenantId, $hostelId);
                    if ($rmSupervisor) {
                        $push->toUserTemplate(
                            $rmSupervisor->id,
                            'rm_supervisor.request_status_changed',
                            [
                                'request_id'   => $ticket->id,
                                'status_label' => ucfirst($ticket->status),
                                'summary'      => $ticket->title,
                            ],
                            [
                                'type'      => 'request_status_changed',
                                'ticket_id' => $ticket->id,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TicketNotifier.onResolved: failed to send request_status_changed push', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }

    public function onClosed(Ticket $ticket, string $oldStatus): void
    {
        if (!config('notifiers.tickets.enabled', true)) {
            return;
        }

        Log::info('Ticket closed notification', [
            'ticket_id' => $ticket->id,
            'tenant_id' => $ticket->tenant_id,
            'hostel_id' => $ticket->hostel_id,
            'old_status' => $oldStatus,
            'new_status' => $ticket->status,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'title' => $ticket->title,
            'closed_at' => $ticket->closed_at?->toIso8601String(),
        ]);

        // Notify Warden / HK / RM Supervisors of status change (closed)
        try {
            $recipients = app(NotificationRecipients::class);
            $push       = app(PushNotifier::class);

            $tenantId = (string) $ticket->tenant_id;
            $hostelId = $ticket->hostel_id ? (int) $ticket->hostel_id : null;

            if ($hostelId) {
                // Warden
                $warden = $recipients->wardenForHostel($tenantId, $hostelId);
                if ($warden) {
                    $push->toUserTemplate(
                        $warden->id,
                        'warden.request_status_changed',
                        [
                            'request_id'   => $ticket->id,
                            'status_label' => ucfirst($ticket->status),
                            'summary'      => $ticket->title,
                        ],
                        [
                            'type'      => 'request_status_changed',
                            'ticket_id' => $ticket->id,
                        ]
                    );
                }

                // HK Supervisor (housekeeping)
                if ($ticket->category === 'housekeeping') {
                    $hkSupervisor = $recipients->hkSupervisorForHostel($tenantId, $hostelId);
                    if ($hkSupervisor) {
                        $push->toUserTemplate(
                            $hkSupervisor->id,
                            'hk_supervisor.request_status_changed',
                            [
                                'request_id'   => $ticket->id,
                                'status_label' => ucfirst($ticket->status),
                                'summary'      => $ticket->title,
                            ],
                            [
                                'type'      => 'request_status_changed',
                                'ticket_id' => $ticket->id,
                            ]
                        );
                    }
                } else {
                    // RM Supervisor (non-housekeeping)
                    $rmSupervisor = $recipients->rmSupervisorForHostel($tenantId, $hostelId);
                    if ($rmSupervisor) {
                        $push->toUserTemplate(
                            $rmSupervisor->id,
                            'rm_supervisor.request_status_changed',
                            [
                                'request_id'   => $ticket->id,
                                'status_label' => ucfirst($ticket->status),
                                'summary'      => $ticket->title,
                            ],
                            [
                                'type'      => 'request_status_changed',
                                'ticket_id' => $ticket->id,
                            ]
                        );
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('TicketNotifier.onClosed: failed to send request_status_changed push', [
                'ticket_id' => $ticket->id,
                'error'     => $e->getMessage(),
            ]);
        }
    }
}







