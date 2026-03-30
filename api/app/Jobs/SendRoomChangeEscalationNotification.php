<?php

namespace App\Jobs;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class SendRoomChangeEscalationNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public int $roomChangeId) {}

    public function handle(SmsService $smsService, PushNotifier $pushNotifier): void
    {
        $roomChange = RoomChange::with('student.user')->find($this->roomChangeId);

        if (! $roomChange) {
            return;
        }

        $managers = User::role('Campus Manager')
            ->where('tenant_id', $roomChange->tenant_id)
            ->get();

        if ($managers->isEmpty()) {
            return;
        }

        $title = 'Room change SLA breach';
        $body = sprintf(
            'Room change %s for %s is overdue for approval.',
            $roomChange->unique_id ?? ('#'.$roomChange->id),
            Str::of($roomChange->student?->user?->name ?? 'student')->limit(30)
        );

        foreach ($managers as $manager) {
            if ($manager->phone) {
                $smsService->send(
                    $manager->phone,
                    $body,
                    (string) $roomChange->tenant_id,
                    'room_change_sla_breach',
                    [
                        'related_type' => RoomChange::class,
                        'related_id' => $roomChange->id,
                    ]
                );
            }

            $pushNotifier->toUser(
                $manager->id,
                $title,
                $body,
                [
                    'type' => 'room_change_escalation',
                    'room_change_id' => $roomChange->id,
                ]
            );
        }
    }
}

