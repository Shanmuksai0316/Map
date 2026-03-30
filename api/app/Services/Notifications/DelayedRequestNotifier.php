<?php

namespace App\Services\Notifications;

use App\Models\LaundryRequest;
use App\Models\User;
use App\Services\Notify\PushNotifier;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Support\Facades\Log;

class DelayedRequestNotifier
{
    public function __construct(
        private readonly PushNotifier $push,
        private readonly NotificationRecipients $recipients
    ) {}

    /**
     * Notify campus managers when a request is delayed (in-app only, no SMS to avoid overload).
     */
    public function notifyCampusManagersForDelayed(Ticket|LaundryRequest $request): void
    {
        $tenantId = $this->getTenantId($request);
        if (! $tenantId) {
            Log::warning('delayed_request.no_tenant', [
                'type' => $request instanceof Ticket ? 'ticket' : 'laundry',
                'id' => $request->id,
            ]);
            return;
        }

        $campusManagers = User::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Campus Manager'))
            ->get();

        if ($campusManagers->isEmpty()) {
            Log::info('delayed_request.no_campus_managers', [
                'tenant_id' => $tenantId,
                'request_type' => $this->requestType($request),
                'request_id' => $request->id,
            ]);
            return;
        }

        [$title, $body, $meta] = $this->buildNotification($request);

        foreach ($campusManagers as $manager) {
            $this->push->toUser($manager->id, $title, $body, $meta);
        }

        // Additionally notify HK / RM Supervisors for delayed tickets
        if ($request instanceof Ticket && $request->hostel_id) {
            if ($request->category === 'housekeeping') {
                $hkSupervisor = $this->recipients->hkSupervisorForHostel($tenantId, (int) $request->hostel_id);
                if ($hkSupervisor) {
                    $this->push->toUserTemplate(
                        $hkSupervisor->id,
                        'hk_supervisor.request_reminder',
                        [
                            'request_id' => $request->id,
                            'summary'    => $request->title,
                        ],
                        $meta
                    );
                }
            } else {
                $rmSupervisor = $this->recipients->rmSupervisorForHostel($tenantId, (int) $request->hostel_id);
                if ($rmSupervisor) {
                    $this->push->toUserTemplate(
                        $rmSupervisor->id,
                        'rm_supervisor.request_reminder',
                        [
                            'request_id' => $request->id,
                            'summary'    => $request->title,
                        ],
                        $meta
                    );
                }
            }
        }

        Log::info('delayed_request.campus_managers_notified', [
            'request_type' => $this->requestType($request),
            'request_id' => $request->id,
            'tenant_id' => $tenantId,
            'manager_count' => $campusManagers->count(),
        ]);
    }

    private function getTenantId(Ticket|LaundryRequest $request): ?string
    {
        if ($request instanceof Ticket) {
            return $request->tenant_id;
        }
        if ($request instanceof LaundryRequest) {
            return $request->getAttribute('tenant_id')
                ?? (function_exists('tenancy') && tenancy()->tenant ? tenancy()->tenant->id : null);
        }
        return null;
    }

    private function requestType(Ticket|LaundryRequest $request): string
    {
        if ($request instanceof Ticket) {
            return match ($request->category) {
                'housekeeping' => 'Housekeeping',
                'maintenance', 'repair_maintenance' => 'Repair & Maintenance',
                default => 'Request',
            };
        }
        return 'Laundry';
    }

    private function buildNotification(Ticket|LaundryRequest $request): array
    {
        $type = $this->requestType($request);
        $ref = $request instanceof Ticket
            ? ($request->category === 'housekeeping' ? 'HK' : 'RM') . '-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT)
            : 'LR-' . str_pad((string) $request->id, 4, '0', STR_PAD_LEFT);

        $title = 'Delayed request';
        $body = sprintf(
            'Request %s (%s) has exceeded the 72-hour completion target. Please review in Delayed Requests.',
            $ref,
            $type
        );

        $meta = [
            'type' => 'delayed_request',
            'request_type' => $request instanceof Ticket ? 'ticket' : 'laundry',
            'request_id' => $request->id,
        ];

        return [$title, $body, $meta];
    }
}
