<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Hostel;
use App\Services\Notifications\SmsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendActivationNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $tenantId,
        public ?int $userId,
        public string $role,
        public string $scope, // 'tenant' or 'hostel' or 'contact'
        public ?int $hostelId = null,
        public ?string $phone = null,
        public ?string $email = null,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            return;
        }

        $user = $this->userId ? User::find($this->userId) : null;
        $hostel = $this->hostelId ? Hostel::find($this->hostelId) : null;

        // Determine recipient
        $recipientPhone = $this->phone ?? $user?->phone;
        $recipientEmail = $this->email ?? $user?->email;

        if (!$recipientPhone) {
            Log::warning('No phone number for activation notification', [
                'tenant_id' => $this->tenantId,
                'user_id' => $this->userId,
                'role' => $this->role,
            ]);
            return;
        }

        // Build message with template variables
        $roleLabel = ucwords(str_replace('_', ' ', $this->role));
        $scopeLabel = $this->scope === 'tenant' 
            ? "all hostels under {$tenant->name}"
            : ($hostel ? "{$hostel->name}" : "your assigned hostel");

        $message = "You've been assigned as {#role#} for {#tenant#} ({#scope#}). Login with your mobile number to receive OTP.";
        
        // Replace template variables
        $message = str_replace('{#role#}', $roleLabel, $message);
        $message = str_replace('{#tenant#}', $tenant->name, $message);
        $message = str_replace('{#scope#}', $scopeLabel, $message);

        // Send SMS using SmsService
        $smsService = app(SmsService::class);
        $smsSent = $smsService->send(
            $recipientPhone,
            $message,
            $this->tenantId,
            'activation_assignment',
            [
                'role' => $this->role,
                'scope' => $this->scope,
                'hostel_id' => $this->hostelId,
                'user_id' => $this->userId,
            ]
        );

        // Send Email (if available and for College Mgmt)
        if ($recipientEmail && ($this->role === 'college_mgmt' || $this->role === 'rector_contact')) {
            $this->sendEmail($recipientEmail, $roleLabel, $tenant, $message);
        }

        // Notification logging is handled by SmsService

        if ($recipientEmail) {
            DB::table('notification_logs')->insert([
                'tenant_id' => $this->tenantId,
                'recipient' => $recipientEmail,
                'channel' => 'email',
                'template' => 'activation_assignment',
                'payload_json' => json_encode([
                    'role' => $this->role,
                    'scope' => $this->scope,
                    'user_id' => $this->userId,
                ]),
                'status' => 'sent',
                'sent_at' => now(),
                'created_at' => now(),
            ]);
        }
    }


    protected function sendEmail(string $email, string $roleLabel, Tenant $tenant, string $message): bool
    {
        // TODO: Implement SendGrid email sending
        Log::info('Activation Email (SendGrid not configured)', [
            'to' => $email,
            'role' => $roleLabel,
            'tenant' => $tenant->name,
        ]);
        return true;
    }
}

