<?php

namespace App\Jobs;

use App\Domain\Leaves\Models\Leave;
use App\Domain\SickLeaves\Models\SickLeave;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SendLeaveDecisionNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $leaveId,
        public string $type, // 'leave' or 'sick_leave'
        public string $decision, // 'approved' or 'rejected'
        public ?string $note = null
    ) {}

    public function handle(SmsService $smsService, PushNotifier $pushNotifier): void
    {
        // Get the model instance
        $model = $this->type === 'leave'
            ? Leave::with(['student.user'])->find($this->leaveId)
            : SickLeave::with(['student.user'])->find($this->leaveId);

        if (!$model || !$model->student || !$model->student->user) {
            return;
        }

        $studentUser = $model->student->user;
        $tenantId = $model->tenant_id;

        // Build notification content
        $typeLabel = $this->type === 'leave' ? 'Leave' : 'Sick Leave';
        $decisionLabel = ucfirst($this->decision);

        $title = "{$typeLabel} {$decisionLabel}";
        $message = $this->buildMessage($model, $typeLabel, $this->decision, $this->note);

        // Send SMS to student
        $template = strtolower($this->type) . '_' . $this->decision;
        $smsService->send(
            $studentUser->phone,
            $message,
            $tenantId,
            $template,
            ['related_type' => get_class($model), 'related_id' => $model->id]
        );

        // Send push notification to student using template
        $pushNotifier->toUserTemplate(
            $studentUser->id,
            'student.leave_decision',
            [
                'decision_label' => $decisionLabel,
                'from'           => $this->type === 'leave' && $model->from_date
                    ? $model->from_date->format('M j')
                    : '',
                'to'             => $this->type === 'leave' && $model->to_date
                    ? $model->to_date->format('M j')
                    : '',
                'note'           => $this->note ?? '',
            ],
            [
                'type'       => 'leave_decision',
                'leave_type' => $this->type,
                'leave_id'   => $model->id,
                'decision'   => $this->decision,
            ]
        );

        // Send notification to Campus Manager
        $this->notifyCampusManager($model, $tenantId, $title, $message);

        // Log notification
        DB::table('notification_logs')->insert([
            'tenant_id' => $tenantId,
            'recipient' => (string) $studentUser->id,
            'channel' => 'notification_bundle', // SMS + Push
            'template' => $template,
            'payload_json' => json_encode([
                'leave_id' => $model->id,
                'leave_type' => $this->type,
                'decision' => $this->decision,
            ]),
            'status' => 'sent',
            'sent_at' => now(),
            'related_type' => get_class($model),
            'related_id' => $model->id,
            'created_at' => now(),
        ]);
    }

    private function buildMessage($model, string $typeLabel, string $decision, ?string $note): string
    {
        $uniqueId = $model->unique_id ?? "#{$model->id}";
        $studentName = $model->student->user->name ?? 'Student';

        if ($decision === 'approved') {
            $message = "Your {$typeLabel} request {$uniqueId} has been approved.";

            if ($this->type === 'leave' && $model->from_date && $model->to_date) {
                $message .= " Valid from {$model->from_date->format('M j')} to {$model->to_date->format('M j')}.";
            }

            if ($note) {
                $message .= " Note: {$note}";
            }
        } else {
            $message = "Your {$typeLabel} request {$uniqueId} has been rejected.";

            if ($note) {
                $message .= " Reason: {$note}";
            } else {
                $message .= " Please contact the rector for more details.";
            }
        }

        return $message;
    }

    private function notifyCampusManager($model, string $tenantId, string $title, string $message): void
    {
        $campusManager = User::role('Campus Manager')
            ->where('tenant_id', $tenantId)
            ->first();

        if ($campusManager) {
            app(PushNotifier::class)->toUser(
                $campusManager->id,
                $title,
                "Student: {$model->student->user->name}. {$message}",
                [
                    'type' => 'leave_decision',
                    'leave_type' => $this->type,
                    'leave_id' => $model->id,
                    'decision' => $this->decision,
                ]
            );
        }
    }
}
