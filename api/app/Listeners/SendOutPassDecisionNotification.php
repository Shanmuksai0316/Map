<?php

namespace App\Listeners;

use App\Events\OutPassDecided;
use App\Services\Notify\PushNotifier;
use App\Services\Notifications\NotificationRecipients;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOutPassDecisionNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        private PushNotifier $pushNotifier,
        private NotificationRecipients $recipients
    ) {}

    public function handle(OutPassDecided $event): void
    {
        $outPass = $event->outPass;
        $student = $outPass->student;

        if (!$student || !$student->user) {
            return;
        }

        $status = $outPass->status->value;
        $decisionLabel = $status === 'approved' ? 'approved' : 'rejected';
        $message = $status === 'approved' 
            ? "Your out-pass request has been approved. Valid until: {$outPass->valid_until->format('M j, Y g:i A')}"
            : "Your out-pass request has been denied.";

        try {
            $this->pushNotifier->toUserTemplate(
                $student->user->id,
                'student.outpass_decision',
                [
                    'decision_label' => ucfirst($decisionLabel),
                    'message'        => $message,
                ],
                [
                    'outpass_id' => $outPass->id,
                    'status'     => $status,
                    'type'       => 'outpass_decision',
                ]
            );

            // Optionally notify guards when approved so they are aware
            if ($status === 'approved') {
                $tenantId = (string) $outPass->tenant_id;
                $hostelId = (int) $outPass->hostel_id;
                $guards   = $this->recipients->guardsForHostel($tenantId, $hostelId);

                $timeRange = $outPass->requested_at?->format('d M H:i') . '–' . $outPass->valid_until?->format('H:i');

                foreach ($guards as $guard) {
                    $this->pushNotifier->toUserTemplate(
                        $guard->id,
                        'guard.outpass_gate_action',
                        [
                            'outpass_id'   => $outPass->id,
                            'student_name' => $student->user->name,
                            'time_range'   => $timeRange,
                        ],
                        [
                            'type'       => 'outpass_approved',
                            'outpass_id' => (string) $outPass->id,
                        ]
                    );
                }
            }

            Log::info('Out-pass decision notification sent', [
                'outpass_id' => $outPass->id,
                'student_id' => $student->id,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send out-pass decision notification', [
                'outpass_id' => $outPass->id,
                'student_id' => $student->id,
                'status' => $status,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
