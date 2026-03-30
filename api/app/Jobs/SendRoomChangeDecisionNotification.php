<?php

namespace App\Jobs;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendRoomChangeDecisionNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $roomChangeId,
        public string $decision,
        public ?string $reason = null,
    ) {}

    public function handle(SmsService $smsService, PushNotifier $pushNotifier): void
    {
        $roomChange = RoomChange::with(['student.user'])->find($this->roomChangeId);

        if (! $roomChange || ! $roomChange->student || ! $roomChange->student->user) {
            return;
        }

        $studentUser = $roomChange->student->user;
        $tenantId = $roomChange->tenant_id;

        $title = match ($this->decision) {
            'approved' => 'Room change approved',
            'rejected' => 'Room change rejected',
            default => 'Room change update',
        };

        $body = $this->decision === 'approved'
            ? 'Your room change has been approved. Please check your new room assignment.'
            : 'Your room change was rejected. Reason: ' . ($this->reason ?? 'Not specified');

        $smsService->send(
            $studentUser->phone,
            $body,
            $tenantId,
            'room_change_' . $this->decision,
            [
                'related_type' => RoomChange::class,
                'related_id' => $roomChange->id,
            ]
        );

        $pushNotifier->toUser(
            $studentUser->id,
            $title,
            $body,
            [
                'type' => 'room_change_decision',
                'room_change_id' => $roomChange->id,
                'decision' => $this->decision,
            ]
        );

        DB::table('notification_logs')->insert([
            'tenant_id' => $tenantId,
            'recipient' => (string) $studentUser->id,
            'channel' => 'push',
            'template' => 'room_change_' . $this->decision,
            'payload_json' => json_encode([
                'room_change_id' => $roomChange->id,
                'decision' => $this->decision,
            ]),
            'status' => 'sent',
            'sent_at' => now(),
            'related_type' => RoomChange::class,
            'related_id' => $roomChange->id,
            'created_at' => now(),
        ]);
    }
}
