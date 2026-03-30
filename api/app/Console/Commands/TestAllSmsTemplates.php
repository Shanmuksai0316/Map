<?php

namespace App\Console\Commands;

use App\Services\Notifications\SmsService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestAllSmsTemplates extends Command
{
    protected $signature = 'sms:test-all {phone=+917975452363} {--template= : Specific template to test}';
    protected $description = 'List and test all MSG91 SMS templates with sample data';

    protected $templates = [
        'otp_login' => [
            'description' => 'Web panel OTP login',
            'sample_data' => ['message' => 'Your OMAPMS login code is 123456. It expires in 5 minutes.'],
            'requires_tenant' => true,
        ],
        'student_welcome_otp' => [
            'description' => 'Student activation with OTP',
            'sample_data' => ['message' => 'OMAPMS: Welcome to MAP HMS. Your login code is 123456. It is valid for 5 minutes.'],
            'requires_tenant' => true,
        ],
        'activation_assignment' => [
            'description' => 'Staff activation assignment',
            'sample_data' => ['message' => 'OMAPMS: Your account has been activated. Please log in using your registered mobile number.'],
            'requires_tenant' => true,
        ],
        'approval_approved_outpass' => [
            'description' => 'Out-pass approval notification',
            'sample_data' => [
                'template' => 'OMAPMS: Out-Pass request {#var#} has been approved by Rector. Note: {#var#}',
                'vars' => ['OP-101', 'Return by 9 PM'],
            ],
            'requires_tenant' => true,
        ],
        'approval_rejected_outpass' => [
            'description' => 'Out-pass rejection notification',
            'sample_data' => [
                'template' => 'OMAPMS: Out-Pass request {#var#} has been rejected by Rector. Note: {#var#}',
                'vars' => ['OP-101', 'Return by 9 PM'],
            ],
            'requires_tenant' => true,
        ],
        'approval_approved_leave' => [
            'description' => 'Leave approval notification',
            'sample_data' => [
                'template' => 'OMAPMS: Leave request {#var#} has been approved by Rector. Note: {#var#}',
                'vars' => ['L-101', 'Return by Sunday evening'],
            ],
            'requires_tenant' => true,
        ],
        'approval_rejected_leave' => [
            'description' => 'Leave rejection notification',
            'sample_data' => [
                'template' => 'OMAPMS: Leave request {#var#} has been rejected by Rector. Note: {#var#}',
                'vars' => ['L-101', 'Insufficient leave balance'],
            ],
            'requires_tenant' => true,
        ],
        'approval_approved_sick_leave' => [
            'description' => 'Sick leave approval notification',
            'sample_data' => [
                'template' => 'OMAPMS: Sick Leave request {#var#} has been approved by Rector. Note: {#var#}',
                'vars' => ['SL-501', 'Get well soon'],
            ],
            'requires_tenant' => true,
        ],
        'approval_rejected_sick_leave' => [
            'description' => 'Sick leave rejection notification',
            'sample_data' => [
                'template' => 'OMAPMS: Sick Leave request {#var#} has been rejected by Rector. Note: {#var#}',
                'vars' => ['SL-501', 'Medical certificate required'],
            ],
            'requires_tenant' => true,
        ],
        'leave_approved' => [
            'description' => 'Detailed leave approval',
            'sample_data' => [
                'template' => 'OMAPMS: Your Leave request {#var#} has been approved. Valid from {#var#} to {#var#}. Note: {#var#}',
                'vars' => ['ID12345', '26/12/25', '28/12/25', 'leave reduced'],
            ],
            'requires_tenant' => true,
        ],
        'leave_rejected' => [
            'description' => 'Detailed leave rejection',
            'sample_data' => [
                'template' => 'OMAPMS: Your Leave request {#var#} has been rejected. Reason:{#var#}',
                'vars' => ['ID12345', 'not proper reason'],
            ],
            'requires_tenant' => true,
        ],
        'sick_leave_approved' => [
            'description' => 'Detailed sick leave approval',
            'sample_data' => [
                'template' => 'OMAPMS: Your Sick Leave request {#var#} has been approved. Note: {#var#}',
                'vars' => ['ID12345', 'Reports required'],
            ],
            'requires_tenant' => true,
        ],
        'sick_leave_rejected' => [
            'description' => 'Detailed sick leave rejection',
            'sample_data' => [
                'template' => 'OMAPMS: Your Sick Leave request {#var#} has been rejected. Reason: {#var#}',
                'vars' => ['ID12345', 'no proper reason'],
            ],
            'requires_tenant' => true,
        ],
        'room_change_approved' => [
            'description' => 'Room change approval',
            'sample_data' => [
                'template' => 'OMAPMS: Your room change request has been {#var#}. Please check details.',
                'vars' => ['approved'],
            ],
            'requires_tenant' => true,
        ],
        'room_change_rejected' => [
            'description' => 'Room change rejection',
            'sample_data' => [
                'template' => 'OMAPMS: Your room change was rejected. Reason: {#var#}',
                'vars' => ['no rooms available'],
            ],
            'requires_tenant' => true,
        ],
        'checklist_morning' => [
            'description' => 'Morning checklist reminder',
            'sample_data' => ['message' => 'OMAPMS: New day, new checklist. Please start and complete your assignment.'],
            'requires_tenant' => true,
        ],
        'checklist_afternoon' => [
            'description' => 'Afternoon checklist reminder',
            'sample_data' => ['message' => 'OMAPMS: Friendly reminder: please finish and submit your assigned checklist.'],
            'requires_tenant' => true,
        ],
        'checklist_overdue' => [
            'description' => 'Overdue checklist alert',
            'sample_data' => ['message' => 'OMAPMS: Checklist is overdue. Complete it immediately or escalate to your manager.'],
            'requires_tenant' => true,
        ],
        'student_archived' => [
            'description' => 'Student account archived',
            'sample_data' => ['message' => 'OMAPMS: Your room has been released. Please log in to MAP HMS for details.'],
            'requires_tenant' => true,
        ],
        'checkout_reminder' => [
            'description' => 'Checkout reminder',
            'sample_data' => ['message' => 'Update: Out-Pass Approved for John Doe valid until 25-Oct-2025 8PM. Team OMAP Services'],
            'requires_tenant' => true,
        ],
        'checkout_overdue' => [
            'description' => 'Checkout overdue',
            'sample_data' => [
                'template' => 'OMAPMS: Checkout for {#var#} is overdue since {#var#}. Please complete checkout immediately',
                'vars' => ['Rahul', '25/12/25'],
            ],
            'requires_tenant' => true,
        ],
        'sla_breach_outpass' => [
            'description' => 'SLA breach alert - outpass',
            'sample_data' => [
                'template' => 'OMAPMS: SLA breached for request {#var#}. Immediate action required.',
                'vars' => ['OP12345'],
            ],
            'requires_tenant' => true,
        ],
        'sla_breach_leave' => [
            'description' => 'SLA breach alert - leave',
            'sample_data' => [
                'template' => 'OMAPMS: SLA BREACHED: leave request {#var#} from {#var#} has been pending for over {#var#} hours.',
                'vars' => ['ID12345', 'Rahul', '4'],
            ],
            'requires_tenant' => true,
        ],
        'sla_breach_sick_leave' => [
            'description' => 'SLA breach alert - sick leave',
            'sample_data' => [
                'template' => 'OMAPMS: SLA BREACHED: sick_leave request {#var#} from {#var#} has been pending for over {#var#} hours.',
                'vars' => ['ID12345', 'Rahul', '4'],
            ],
            'requires_tenant' => true,
        ],
        'sla_warning_outpass' => [
            'description' => 'SLA warning - outpass',
            'sample_data' => [
                'template' => 'OMAPMS: EXPIRING SOON: outpass request {#var#} from {#var#} will breach SLA in {#var#} hours. Please review.',
                'vars' => ['ID12345', 'Rahul', '2'],
            ],
            'requires_tenant' => true,
        ],
        'late_return_alert' => [
            'description' => 'Late return alert',
            'sample_data' => [
                'template' => 'OMAPMS: Alert: {#var#} returned late at {#var#} (Curfew: {#var#}). Hostel: {#var#}.',
                'vars' => ['John Doe', '22:30', '21:00', 'Block A'],
            ],
            'requires_tenant' => true,
        ],
        'emergency_alert' => [
            'description' => 'Emergency alert',
            'sample_data' => [
                'template' => 'Emergency Alert: {#var#}. Contact: {#var#}.Team OMAP Services',
                'vars' => ['mess under maintenance', '1234567890'],
            ],
            'requires_tenant' => true,
        ],
        'attendance_alert' => [
            'description' => 'Attendance alert',
            'sample_data' => [
                'template' => 'Attendance Alert: {#var#} marked {#var#} on {#var#}. Hostel: {#var#}.Team OMAP Services',
                'vars' => ['Bob Johnson', 'Absent', '2025-10-24', 'Alpha'],
            ],
            'requires_tenant' => true,
        ],
    ];

    public function handle(): int
    {
        $phone = $this->argument('phone');
        $specificTemplate = $this->option('template');

        // Ensure phone has country code
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '91')) {
                $phone = '+' . $phone;
            } else {
                $phone = '+91' . $phone;
            }
        }

        $this->info("===========================================");
        $this->info("Testing MSG91 SMS Templates");
        $this->info("===========================================");
        $this->info("Phone: {$phone}");
        $this->newLine();

        // Check MSG91 configuration
        if (!config('services.msg91.enabled')) {
            $this->error('❌ MSG91 is not enabled! Set MSG91_ENABLED=true in .env');
            return Command::FAILURE;
        }

        if (!config('services.msg91.key')) {
            $this->error('❌ MSG91 API Key is not configured!');
            return Command::FAILURE;
        }

        $this->info('✅ MSG91 Configuration: OK');
        $this->info('   Sender ID: ' . config('services.msg91.sender_id', 'Not set'));
        $this->newLine();

        // List all templates
        $this->info('📋 Available SMS Templates:');
        $this->table(
            ['Template', 'Description', 'Template ID', 'Requires Tenant'],
            array_map(function ($template, $key) {
                return [
                    $key,
                    $this->templates[$key]['description'],
                    config("services.msg91.templates.{$key}") ?: 'Not configured',
                    $this->templates[$key]['requires_tenant'] ? 'Yes' : 'No'
                ];
            }, array_keys($this->templates), array_keys($this->templates))
        );
        $this->newLine();

        // Test specific template or all templates
        if ($specificTemplate) {
            if (!isset($this->templates[$specificTemplate])) {
                $this->error("❌ Template '{$specificTemplate}' not found!");
                return Command::FAILURE;
            }

            $this->info("🧪 Testing specific template: {$specificTemplate}");
            return $this->testTemplate($phone, $specificTemplate);
        } else {
            $this->info("🧪 Testing ALL templates...");
            $this->newLine();

            $results = [];
            foreach ($this->templates as $template => $config) {
                $result = $this->testTemplate($phone, $template);
                $results[$template] = $result;
                $this->newLine();
            }

            // Summary
            $this->info("📊 SUMMARY:");
            $successful = array_filter($results, fn($r) => $r === 0);
            $failed = array_filter($results, fn($r) => $r !== 0);

            $this->info("✅ Successful: " . count($successful));
            $this->info("❌ Failed: " . count($failed));

            if (!empty($failed)) {
                $this->warn("Failed templates: " . implode(', ', array_keys($failed)));
            }

            return count($failed) > 0 ? Command::FAILURE : Command::SUCCESS;
        }
    }

    private function testTemplate(string $phone, string $template): int
    {
        $config = $this->templates[$template];
        $templateId = config("services.msg91.templates.{$template}");

        $this->info("🔍 Testing: {$template} - {$config['description']}");

        if (!$templateId) {
            $this->warn("⚠️  Template ID not configured for '{$template}' - skipping");
            return Command::FAILURE;
        }

        try {
            // Set tenant context if required
            if ($config['requires_tenant']) {
                $tenant = $this->getTestTenant();
                if (!$tenant) {
                    $this->error("❌ No tenant available for testing - skipping");
                    return Command::FAILURE;
                }
                \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
            }

            $smsService = app(SmsService::class);
            
            // Build message from template with variables
            if (isset($config['sample_data']['template']) && isset($config['sample_data']['vars'])) {
                // Template with {#var#} placeholders
                $message = $config['sample_data']['template'];
                foreach ($config['sample_data']['vars'] as $var) {
                    $message = preg_replace('/\{#var#\}/', $var, $message, 1);
                }
            } else {
                // Direct message (backward compatibility)
                $message = $config['sample_data']['message'] ?? '';
            }

            $this->line("   📤 Message: {$message}");
            $this->line("   🆔 Template ID: {$templateId}");

            $tenantId = null;
            if ($config['requires_tenant']) {
                $tenant = $this->getTestTenant();
                $tenantId = $tenant ? $tenant->id : null;
            }

            $result = $smsService->send(
                $phone,
                $message,
                $tenantId,
                $template,
                ['purpose' => 'test', 'template' => $template]
            );

            if ($result) {
                $this->info("   ✅ SMS sent successfully!");
                // Check recent logs for actual delivery status
                $this->line("   📊 Check MSG91 dashboard for delivery status");
                return Command::SUCCESS;
            } else {
                $this->error("   ❌ SMS failed to send");
                $this->warn("   📋 Check logs: tail -f storage/logs/laravel.log | grep msg91");
                return Command::FAILURE;
            }

        } catch (\Exception $e) {
            $this->error("   ❌ Error: {$e->getMessage()}");
            return Command::FAILURE;
        } finally {
            // Clear tenant context
            if ($config['requires_tenant']) {
                try {
                    \Stancl\Tenancy\Facades\Tenancy::end();
                } catch (\Exception $e) {
                    // Ignore
                }
            }
        }
    }

    private function getTestTenant(): ?Tenant
    {
        // Try to find MAP-PPCU tenant first
        $tenant = Tenant::where('code', 'MAP-PPCU')->first();
        if ($tenant) return $tenant;

        // Fall back to any active tenant
        return Tenant::where('status', 'active')->first();
    }
}
