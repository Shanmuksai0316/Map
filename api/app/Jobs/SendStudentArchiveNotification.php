<?php

namespace App\Jobs;

use App\Models\Student;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendStudentArchiveNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public int $studentId,
        public string $archivedAt,
        public ?string $reason = null,
    ) {}

    public function handle(SmsService $smsService, PushNotifier $pushNotifier): void
    {
        $student = Student::with(['user', 'hostel'])->find($this->studentId);

        if (!$student || !$student->user) {
            Log::warning('student.archive_notification.missing_student', ['student_id' => $this->studentId]);
            return;
        }

        $archiveAt = Carbon::parse($this->archivedAt)->timezone('Asia/Kolkata');
        $hostelName = $student->hostel?->name ?? 'your hostel';
        $studentName = $student->user->name ?? 'Student';

        $smsMessage = sprintf(
            '%s, your room at %s has been released effective %s. %s',
            $studentName,
            $hostelName,
            $archiveAt->format('d M Y'),
            $this->reason ? 'Reason: ' . $this->reason : ''
        );

        $smsService->send(
            $student->user->phone,
            trim($smsMessage),
            $student->tenant_id,
            'student_archived',
            [
                'related_type' => Student::class,
                'related_id' => $student->id,
                'reason' => $this->reason,
            ]
        );

        if ($student->user_id) {
            $pushNotifier->toUser(
                $student->user_id,
                'Checkout completed',
                'Your hostel record was archived. Contact campus manager for assistance.',
                [
                    'type' => 'student_archived',
                    'student_id' => $student->id,
                ]
            );

            DB::table('notification_logs')->insert([
                'tenant_id' => $student->tenant_id,
                'recipient' => (string) $student->user_id,
                'channel' => 'push',
                'template' => 'student_archived',
                'payload_json' => json_encode([
                    'student_id' => $student->id,
                ]),
                'status' => 'sent',
                'sent_at' => now(),
                'related_type' => Student::class,
                'related_id' => $student->id,
                'created_at' => now(),
            ]);
        }
    }
}
