<?php

namespace App\Services\Approvals;

use App\Domain\Leaves\Models\Leave;
use App\Domain\SickLeaves\Models\SickLeave;
use App\Models\Domain\OutPass\OutPass;
use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Support\Facades\Log;

class ApprovalSLAService
{
    // SLA durations in hours
    const OUTPASS_SLA_HOURS = 2;
    const LEAVE_SLA_HOURS = 4;

    // Warning threshold (send warning when 75% of SLA time has passed)
    const WARNING_THRESHOLD = 0.75;

    public function __construct(
        private SmsService $smsService,
        private PushNotifier $pushNotifier
    ) {}

    /**
     * Main entry point - check all pending approvals for SLA breaches and warnings
     */
    public function checkAndNotify(): void
    {
        $this->checkOutPasses();
        $this->checkLeaves();
        $this->checkSickLeaves();
    }

    /**
     * Check OutPass SLAs (2 hours)
     * Scoped to tenant_id to ensure proper isolation
     */
    private function checkOutPasses(): void
    {
        $pendingOutPasses = OutPass::where('status', 'pending')
            ->whereNull('sla_breached_at')
            ->with(['tenant', 'student.user', 'hostel'])
            ->get();

        foreach ($pendingOutPasses as $outPass) {
            // Ensure tenant_id is set before processing
            if ($outPass->tenant_id) {
                $this->checkSingleApproval($outPass, self::OUTPASS_SLA_HOURS, 'outpass');
            }
        }
    }

    /**
     * Check Leave SLAs (4 hours)
     * Scoped to tenant_id to ensure proper isolation
     */
    private function checkLeaves(): void
    {
        $pendingLeaves = Leave::where('status', 'pending')
            ->whereNull('sla_breached_at')
            ->with(['tenant', 'student.user', 'hostel'])
            ->get();

        foreach ($pendingLeaves as $leave) {
            // Ensure tenant_id is set before processing
            if ($leave->tenant_id) {
                $this->checkSingleApproval($leave, self::LEAVE_SLA_HOURS, 'leave');
            }
        }
    }

    /**
     * Check SickLeave SLAs (4 hours)
     * Scoped to tenant_id to ensure proper isolation
     */
    private function checkSickLeaves(): void
    {
        $pendingSickLeaves = SickLeave::where('status', 'pending')
            ->whereNull('sla_breached_at')
            ->with(['tenant', 'student.user', 'hostel'])
            ->get();

        foreach ($pendingSickLeaves as $sickLeave) {
            // Ensure tenant_id is set before processing
            if ($sickLeave->tenant_id) {
                $this->checkSingleApproval($sickLeave, self::LEAVE_SLA_HOURS, 'sick_leave');
            }
        }
    }

    /**
     * Check a single approval request for SLA status
     */
    private function checkSingleApproval($record, int $slaHours, string $type): void
    {
        $submittedAt = $record->submitted_at ?? $record->requested_at;
        if (!$submittedAt) return;

        $hoursElapsed = now()->diffInHours($submittedAt);
        $isBreached = $hoursElapsed >= $slaHours;
        $warningThreshold = $slaHours * self::WARNING_THRESHOLD;
        $shouldWarn = $hoursElapsed >= $warningThreshold && !$record->sla_warning_sent_at;

        // Handle breach
        if ($isBreached && !$record->sla_breached_at) {
            $record->update(['sla_breached_at' => now()]);
            $this->sendBreachNotification($record, $type);
            Log::info("SLA breached for {$type}", [
                'id' => $record->id,
                'hours_elapsed' => $hoursElapsed,
                'sla_hours' => $slaHours
            ]);
        }
        // Handle warning
        elseif ($shouldWarn) {
            $record->update(['sla_warning_sent_at' => now()]);
            $this->sendWarningNotification($record, $type);
            Log::info("SLA warning sent for {$type}", [
                'id' => $record->id,
                'hours_elapsed' => $hoursElapsed,
                'warning_threshold' => $warningThreshold
            ]);
        }
    }

    /**
     * Send SLA breach notification
     */
    private function sendBreachNotification($record, string $type): void
    {
        $rector = $this->getRectorForHostel($record->hostel_id, $record->tenant_id);
        $campusManager = $this->getCampusManager($record->tenant_id);

        $uniqueId = $record->unique_id ?? (string)$record->id;
        $studentName = $record->student?->user?->name ?? 'Unknown Student';
        $hours = $type === 'outpass' ? self::OUTPASS_SLA_HOURS : self::LEAVE_SLA_HOURS;

        // Build message with template variables
        $message = match($type) {
            'outpass' => "SLA BREACHED: outpass request {#ID#} from {#student#} has been pending for over 2 hours.",
            'leave' => "SLA BREACHED: leave request {#ID#} from {#student#} has been pending for over 4 hours.",
            default => "SLA BREACHED: {$type} request {#ID#} from {#student#} has been pending for over {$hours} hours."
        };
        
        // Replace template variables
        $message = str_replace('{#ID#}', $uniqueId, $message);
        $message = str_replace('{#student#}', $studentName, $message);

        // Notify Rector
        if ($rector) {
            $this->sendNotification($rector, $message, 'sla_breach', $type, $record);
        }

        // Notify Campus Manager
        if ($campusManager) {
            $this->sendNotification($campusManager, $message, 'sla_breach', $type, $record);
        }
    }

    /**
     * Send SLA warning notification
     */
    private function sendWarningNotification($record, string $type): void
    {
        $rector = $this->getRectorForHostel($record->hostel_id, $record->tenant_id);

        if (!$rector) return;

        $uniqueId = $record->unique_id ?? "#{$record->id}";
        $studentName = $record->student?->user?->name ?? 'Unknown Student';
        $remainingHours = ($type === 'outpass' ? self::OUTPASS_SLA_HOURS : self::LEAVE_SLA_HOURS) -
                         now()->diffInHours($record->submitted_at ?? $record->requested_at);

        // Build message with template variables (SLA warnings use same format but different template)
        $message = "EXPIRING SOON: {$type} request {$uniqueId} from {$studentName} will breach SLA in {$remainingHours} hours. Please review.";

        $this->sendNotification($rector, $message, 'sla_warning', $type, $record);
    }

    /**
     * Send notification via SMS and Push
     */
    private function sendNotification(User $user, string $message, string $templatePrefix, string $type, $record): void
    {
        // Map template name for SLA breach notifications
        $template = match($templatePrefix) {
            'sla_breach' => match($type) {
                'outpass' => 'sla_breach_outpass',
                'leave' => 'sla_breach_leave',
                default => "{$templatePrefix}_{$type}"
            },
            default => "{$templatePrefix}_{$type}"
        };

        // Send SMS
        $this->smsService->send(
            $user->phone,
            $message,
            $record->tenant_id,
            $template,
            ['related_type' => get_class($record), 'related_id' => $record->id]
        );

        // Send Push Notification
        $this->pushNotifier->toUser(
            $user->id,
            $templatePrefix === 'sla_breach' ? 'SLA Breach Alert' : 'Approval Due Soon',
            $message,
            [
                'type' => $templatePrefix,
                'approval_type' => $type,
                'record_id' => $record->id,
            ]
        );
    }

    /**
     * Get Rector for a specific hostel
     */
    private function getRectorForHostel($hostelId, $tenantId): ?User
    {
        return User::role('Rector')
            ->where('tenant_id', $tenantId)
            ->whereHas('hostel_assignments', function ($q) use ($hostelId) {
                $q->where('hostel_id', $hostelId);
            })
            ->first();
    }

    /**
     * Get Campus Manager for tenant
     */
    private function getCampusManager($tenantId): ?User
    {
        return User::role('Campus Manager')
            ->where('tenant_id', $tenantId)
            ->first();
    }
}
