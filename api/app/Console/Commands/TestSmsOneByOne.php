<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\Notifications\SmsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Stancl\Tenancy\Facades\Tenancy;

class TestSmsOneByOne extends Command
{
    protected $signature = 'sms:test-one-by-one {phone} {--template=}';
    protected $description = 'Test SMS templates one by one with confirmation after each';

    private array $templates = [
        'otp_login' => [
            'description' => 'Web panel OTP login',
            'message' => 'Your OMAPMS login code is {#var#}. It expires in {#var#} minutes.',
            'vars' => ['123456', '5'],
        ],
        'student_welcome_otp' => [
            'description' => 'Student activation with OTP',
            'message' => 'OMAPMS: Welcome to MAP HMS. Your login code is {#var#}. It is valid for {#var#} minutes.',
            'vars' => ['123456', '5'],
        ],
        'approval_approved_outpass' => [
            'description' => 'Out-pass approval notification',
            'message' => 'OMAPMS: Out-Pass request {#var#} has been approved by Rector. Note: {#var#}.',
            'vars' => ['OP-101', 'Valid till 8PM'],
        ],
        'approval_rejected_outpass' => [
            'description' => 'Out-pass rejection notification',
            'message' => 'OMAPMS: Out-Pass request {#var#} has been rejected by Rector. Note: {#var#}.',
            'vars' => ['OP-101', 'Curfew violation'],
        ],
        'approval_approved_leave' => [
            'description' => 'Leave approval notification',
            'message' => 'OMAPMS: Leave request {#var#} has been approved by Rector. Note: {#var#}.',
            'vars' => ['L-101', 'Return by evening'],
        ],
        'approval_rejected_leave' => [
            'description' => 'Leave rejection notification',
            'message' => 'OMAPMS: Leave request {#var#} has been rejected by Rector. Note: {#var#}.',
            'vars' => ['L-101', 'Documentation missing'],
        ],
        'leave_approved' => [
            'description' => 'Detailed leave approval',
            'message' => 'OMAPMS: Leave request {#var#} approved by Rector. Valid from {#var#} to {#var#}.',
            'vars' => ['L-101', '26/12/25', '28/12/25'],
        ],
        'leave_rejected' => [
            'description' => 'Detailed leave rejection',
            'message' => 'OMAPMS: Leave request {#var#} rejected by Rector. Reason: {#var#}.',
            'vars' => ['L-101', 'not proper reason'],
        ],
        'sick_leave_approved' => [
            'description' => 'Detailed sick leave approval',
            'message' => 'OMAPMS: Your Sick Leave request {#var#} has been approved. Note: {#var#}.',
            'vars' => ['SL-101', 'Get well soon'],
        ],
        'sick_leave_rejected' => [
            'description' => 'Detailed sick leave rejection',
            'message' => 'OMAPMS: Your Sick Leave request {#var#} has been rejected. Reason: {#var#}.',
            'vars' => ['SL-101', 'Medical certificate required'],
        ],
        'room_change_approved' => [
            'description' => 'Room change approval',
            'message' => 'OMAPMS: Your room change request has been approved. Please check details.',
            'vars' => [],
        ],
        'room_change_rejected' => [
            'description' => 'Room change rejection',
            'message' => 'OMAPMS: Your room change was rejected. Reason: {#var#}.',
            'vars' => ['Room not available'],
        ],
        'checklist_morning' => [
            'description' => 'Morning checklist reminder',
            'message' => 'OMAPMS: Reminder to complete your assigned checklist.',
            'vars' => [],
        ],
        'checklist_afternoon' => [
            'description' => 'Afternoon checklist reminder',
            'message' => 'OMAPMS: Friendly reminder: please finish and submit your assigned checklist.',
            'vars' => [],
        ],
        'checklist_overdue' => [
            'description' => 'Overdue checklist alert',
            'message' => 'OMAPMS: Checklist is overdue. Complete it immediately or escalate to your manager.',
            'vars' => [],
        ],
        'late_return_alert' => [
            'description' => 'Late return alert',
            'message' => 'OMAPMS: Alert: {#var#} returned late at {#var#} (Curfew: {#var#}). Hostel: {#var#}.',
            'vars' => ['John Doe', '22:30', '21:00', 'Block A'],
        ],
        'emergency_alert' => [
            'description' => 'Emergency alert',
            'message' => 'Emergency Alert: {#var#}. Contact: {#var#}.Team OMAP Services',
            'vars' => ['Fire alarm activated', '1234567890'],
        ],
        'attendance_alert' => [
            'description' => 'Attendance alert',
            'message' => 'Attendance Alert: {#var#} marked {#var#} on {#var#}. Hostel: {#var#}.Team OMAP Services',
            'vars' => ['John Doe', 'Absent', '2025-10-24', 'Alpha'],
        ],
    ];

    public function handle(): int
    {
        $phone = $this->argument('phone');
        $specificTemplate = $this->option('template');

        // Normalize phone
        if (!str_starts_with($phone, '+')) {
            $phone = '+91' . $phone;
        }

        $this->info("===========================================");
        $this->info("SMS Testing - One by One");
        $this->info("===========================================");
        $this->info("Phone: {$phone}");
        $this->newLine();

        if (!Config::get('services.msg91.enabled')) {
            $this->error('MSG91 is not enabled. Set MSG91_ENABLED=true in .env');
            return Command::FAILURE;
        }

        // Get tenant for context
        $tenant = Tenant::first();
        if ($tenant) {
            Tenancy::initialize($tenant);
        }

        $smsService = app(SmsService::class);
        $templatesToTest = $specificTemplate ? [$specificTemplate => $this->templates[$specificTemplate] ?? null] : $this->templates;

        foreach ($templatesToTest as $templateName => $templateData) {
            if (!$templateData) {
                $this->warn("Template '{$templateName}' not found, skipping...");
                continue;
            }

            $this->newLine();
            $this->info("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->info("📤 Sending: {$templateName}");
            $this->info("   Description: {$templateData['description']}");
            
            // Build message with variables
            $message = $templateData['message'];
            foreach ($templateData['vars'] as $var) {
                $message = preg_replace('/\{#var#\}/', $var, $message, 1);
            }
            
            $this->line("   Message: {$message}");
            $this->line("   Template ID: " . Config::get("services.msg91.templates.{$templateName}", 'NOT SET'));
            $this->newLine();

            try {
                $success = $smsService->send(
                    $phone,
                    $message,
                    $tenant?->id,
                    $templateName,
                    ['purpose' => 'test_one_by_one', 'template' => $templateName]
                );

                if ($success) {
                    $this->info("   ✅ SMS sent to MSG91 API");
                } else {
                    $this->error("   ❌ SMS failed to send");
                }
            } catch (\Exception $e) {
                $this->error("   ❌ Exception: {$e->getMessage()}");
            }

            $this->newLine();
            $this->line("⏳ Waiting for your confirmation...");
            $this->line("   Did you receive the SMS? (yes/no/skip)");
            
            // In interactive mode, we'll just prompt and wait
            // For non-interactive, we'll continue after a delay
            if ($this->getOutput()->isInteractive()) {
                $response = $this->ask('   Your response (yes/no/skip): ', 'skip');
                if (strtolower($response) === 'no') {
                    $this->warn("   ⚠️  SMS not received - check MSG91 dashboard");
                } elseif (strtolower($response) === 'yes') {
                    $this->info("   ✅ SMS received successfully!");
                } else {
                    $this->line("   ⏭️  Skipping confirmation");
                }
            } else {
                $this->line("   (Non-interactive mode - continuing in 3 seconds...)");
                sleep(3);
            }
        }

        if ($tenant) {
            Tenancy::end();
        }

        $this->newLine();
        $this->info("✅ Testing complete!");
        return Command::SUCCESS;
    }
}

