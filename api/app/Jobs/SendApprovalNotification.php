<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Notifications\SmsService;
use App\Services\Notify\PushNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendApprovalNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private string $approvalType,
        private int $recordId,
        private string $decision,
        private ?string $note,
        private int $studentId,
        private int $rectorId,
        private string $tenantId
    ) {}

    public function handle(
        SmsService $smsService,
        PushNotifier $pushNotifier
    ): void {
        try {
            // Get student and rector
            $student = User::find($this->studentId);
            $rector = User::find($this->rectorId);
            
            if (!$student) {
                Log::warning('SendApprovalNotification: Student not found', [
                    'student_id' => $this->studentId
                ]);
                return;
            }

            // Get Campus Manager for this tenant
            $campusManager = User::role('Campus Manager')
                ->where('tenant_id', $this->tenantId)
                ->first();

            // Prepare messages with STPL-approved template format
            $template = "approval_{$this->decision}_{$this->approvalType}";
            
            // Build message with STPL-approved format (uses {#var#} generic variables)
            $studentMessage = match($template) {
                'approval_approved_outpass' => "Update: Out-Pass Approved for {#var#} valid until {#var#}.Team OMAP Services",
                'approval_rejected_outpass' => "OMAPMS: Update: Out-Pass Denied for {#var#}. Reason: {#var#}.",
                'approval_approved_leave' => "OMAPMS: Leave request {#var#} has been approved by Rector. Note: {#var#}",
                'approval_rejected_leave' => "OMAPMS: Leave request {#var#} has been rejected by Rector. Note: {#var#}",
                'approval_rejected_sick_leave' => "OMAPMS: Sick Leave request {#var#} has been rejected by Rector. Note: {#var#}",
                default => "OMAPMS: {$this->getRequestTypeLabel()} request {#var#} has been {$this->decision} by Rector. Note: {#var#}"
            };
            
            // Replace template variables - STPL uses generic {#var#} format
            if ($template === 'approval_approved_outpass') {
                // First {#var#} is student name, second is expiry date/time
                // Try to get expiry from the record if available
                $expiryDate = 'N/A';
                try {
                    if ($this->approvalType === 'outpass') {
                        $outpass = \App\Models\Domain\OutPass\OutPass::find($this->recordId);
                        if ($outpass && ($outpass->valid_until || $outpass->expires_at)) {
                            $expiryDate = ($outpass->valid_until ?? $outpass->expires_at);
                            if ($expiryDate instanceof \DateTime || $expiryDate instanceof \Carbon\Carbon) {
                                $expiryDate = $expiryDate->format('d-M-Y, g:i A');
                            }
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback if record not found
                }
                $studentMessage = preg_replace('/\{#var#\}/', $student->name, $studentMessage, 1);
                $studentMessage = preg_replace('/\{#var#\}/', $expiryDate, $studentMessage, 1);
            } elseif ($template === 'approval_rejected_outpass') {
                // First {#var#} is student name, second is reason
                $studentMessage = preg_replace('/\{#var#\}/', $student->name, $studentMessage, 1);
                $studentMessage = preg_replace('/\{#var#\}/', $this->note ?: 'No reason provided', $studentMessage, 1);
            } else {
                // For leave/sick leave: first {#var#} is request ID, second is note
                // Format request ID (e.g., L-101, SL-501)
                $requestId = match($this->approvalType) {
                    'leave' => "L-{$this->recordId}",
                    'sick_leave' => "SL-{$this->recordId}",
                    default => "#{$this->recordId}"
                };
                $studentMessage = preg_replace('/\{#var#\}/', $requestId, $studentMessage, 1);
                $studentMessage = preg_replace('/\{#var#\}/', $this->note ?: 'None', $studentMessage, 1);
            }

            // Message for Campus Manager
            $decisionText = $this->decision === 'approved' ? 'approved' : 'rejected';
            $requestType = $this->getRequestTypeLabel();
            $cmMessage = "Rector {$rector?->name} has {$decisionText} {$requestType} request #{$this->recordId} for {$student->name}.";

            // Send to Student
            $this->sendToStudent($student, $studentMessage, $smsService, $pushNotifier);

            // Send to Campus Manager
            if ($campusManager) {
                $this->sendToCampusManager($campusManager, $cmMessage, $smsService, $pushNotifier);
            }

            Log::info('Approval notification sent', [
                'type' => $this->approvalType,
                'record_id' => $this->recordId,
                'decision' => $this->decision,
                'student_id' => $this->studentId,
            ]);

        } catch (\Exception $e) {
            Log::error('SendApprovalNotification failed', [
                'error' => $e->getMessage(),
                'type' => $this->approvalType,
                'record_id' => $this->recordId,
            ]);
            
            // Don't fail the job - log and continue
        }
    }

    private function sendToStudent(
        User $student,
        string $message,
        SmsService $smsService,
        PushNotifier $pushNotifier
    ): void {
        // Determine template name
        $template = "approval_{$this->decision}_{$this->approvalType}";
        
        // Send SMS
        $smsService->send(
            $student->phone,
            $message,
            $this->tenantId,
            $template,
            [
                'related_type' => $this->approvalType,
                'related_id' => $this->recordId,
            ]
        );

        // Send Push Notification
        $pushNotifier->toUser(
            $student->id,
            $this->decision === 'approved' ? 'Request Approved' : 'Request Rejected',
            $message,
            [
                'type' => 'approval_decision',
                'approval_type' => $this->approvalType,
                'record_id' => $this->recordId,
                'decision' => $this->decision,
            ]
        );
    }

    private function sendToCampusManager(
        User $campusManager,
        string $message,
        SmsService $smsService,
        PushNotifier $pushNotifier
    ): void {
        // Send Push Notification to Campus Manager
        $pushNotifier->toUser(
            $campusManager->id,
            'Approval Decision',
            $message,
            [
                'type' => 'approval_notification',
                'approval_type' => $this->approvalType,
                'record_id' => $this->recordId,
                'decision' => $this->decision,
            ]
        );
    }

    private function getRequestTypeLabel(): string
    {
        return match($this->approvalType) {
            'outpass' => 'Out-Pass',
            'leave' => 'Leave',
            'sick_leave' => 'Sick Leave',
            default => ucfirst($this->approvalType),
        };
    }
}
