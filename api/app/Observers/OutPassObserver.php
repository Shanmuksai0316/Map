<?php

namespace App\Observers;

use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Hostel;
use App\Services\Notifications\SmsService;
use Illuminate\Support\Facades\Log;

class OutPassObserver
{
    public function __construct(
        private readonly SmsService $smsService
    ) {}

    /**
     * Handle the OutPass "updated" event.
     *
     * When an outpass is approved, generate a backup code and send it to the student.
     */
    public function updated(OutPass $outPass): void
    {
        // Check if status changed to approved
        if (!$outPass->wasChanged('status')) {
            return;
        }

        $newStatus = $outPass->status;
        $oldStatus = $outPass->getOriginal('status');

        // Only process when transitioning TO approved status
        if ($newStatus !== OutPassStatus::APPROVED) {
            return;
        }

        // Check if hostel has backup codes enabled
        $hostel = $outPass->hostel;
        if (!$hostel || !$hostel->areBackupCodesEnabled()) {
            Log::info('outpass.backup_code.disabled', [
                'outpass_id' => $outPass->id,
                'hostel_id' => $outPass->hostel_id,
            ]);
            return;
        }

        // If a backup code already exists (e.g., generated during approval flow), don't regenerate.
        if (!empty($outPass->backup_code)) {
            return;
        }

        // Generate backup code
        $backupCode = $outPass->generateBackupCode();

        Log::info('outpass.backup_code.generated', [
            'outpass_id' => $outPass->id,
            'student_id' => $outPass->student_id,
        ]);

        // Send SMS with backup code to student
        $this->sendBackupCodeSms($outPass, $backupCode);
    }

    /**
     * Send SMS with backup code to student.
     */
    private function sendBackupCodeSms(OutPass $outPass, string $backupCode): void
    {
        $student = $outPass->student;
        if (!$student) {
            Log::warning('outpass.backup_code.no_student', [
                'outpass_id' => $outPass->id,
            ]);
            return;
        }

        $user = $student->user;
        $phone = $user?->phone ?? $student->phone;

        if (!$phone) {
            Log::warning('outpass.backup_code.no_phone', [
                'outpass_id' => $outPass->id,
                'student_id' => $student->id,
            ]);
            return;
        }

        $validUntil = $outPass->valid_until?->format('h:i A') ?? 'not specified';

        // Message with backup code
        $message = sprintf(
            'OMAPMS: Your outpass is approved. Backup code for gate entry: %s. Valid until %s. Show this if QR scan fails.',
            $backupCode,
            $validUntil
        );

        $sent = $this->smsService->send(
            $phone,
            $message,
            (string) $outPass->tenant_id,
            'outpass_backup_code',
            [
                'outpass_id' => $outPass->id,
                'student_id' => $student->id,
                'related_type' => OutPass::class,
                'related_id' => $outPass->id,
            ]
        );

        if ($sent) {
            Log::info('outpass.backup_code.sms_sent', [
                'outpass_id' => $outPass->id,
                'phone_last4' => substr($phone, -4),
            ]);
        } else {
            Log::warning('outpass.backup_code.sms_failed', [
                'outpass_id' => $outPass->id,
                'phone_last4' => substr($phone, -4),
            ]);
        }
    }
}
