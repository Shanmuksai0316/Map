<?php

namespace App\Console\Commands;

use App\Services\Notifications\SmsService;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateSmsReport extends Command
{
    protected $signature = 'sms:report {phone=+917975452363}';
    protected $description = 'Generate detailed SMS delivery report from notification logs';

    public function handle(): int
    {
        $phone = $this->argument('phone');
        
        // Normalize phone
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '91')) {
                $phone = '+' . $phone;
            } else {
                $phone = '+91' . $phone;
            }
        }

        $this->info("===========================================");
        $this->info("SMS Delivery Report");
        $this->info("===========================================");
        $this->info("Phone: {$phone}");
        $this->newLine();

        // Get all SMS logs for this phone
        $logs = DB::table('notification_logs')
            ->where('channel', 'sms')
            ->where(function ($query) use ($phone) {
                $query->where('recipient', 'LIKE', "%{$phone}%")
                    ->orWhere('recipient', 'LIKE', "%" . substr($phone, -10) . "%");
            })
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get();

        if ($logs->isEmpty()) {
            $this->warn("⚠️  No SMS logs found for {$phone}");
            $this->newLine();
            $this->info("💡 This could mean:");
            $this->line("   1. SMS service is not configured");
            $this->line("   2. No SMS has been sent yet");
            $this->line("   3. Phone number format mismatch");
            return Command::SUCCESS;
        }

        $this->info("📊 Found " . $logs->count() . " SMS records");
        $this->newLine();

        // Group by status
        $byStatus = $logs->groupBy('status');
        $byTemplate = $logs->groupBy('template');

        $this->info("📈 Summary by Status:");
        foreach ($byStatus as $status => $group) {
            $count = $group->count();
            $icon = $status === 'sent' ? '✅' : '❌';
            $this->line("   {$icon} {$status}: {$count}");
        }
        $this->newLine();

        $this->info("📋 Summary by Template:");
        foreach ($byTemplate as $template => $group) {
            $sent = $group->where('status', 'sent')->count();
            $failed = $group->where('status', 'failed')->count();
            $total = $group->count();
            $icon = $failed === 0 ? '✅' : ($sent === 0 ? '❌' : '⚠️');
            $this->line("   {$icon} {$template}: {$sent} sent, {$failed} failed (total: {$total})");
        }
        $this->newLine();

        // Detailed table
        $this->info("📋 Detailed SMS Log:");
        $this->table(
            ['Time', 'Template', 'Status', 'Sent At', 'Recipient'],
            $logs->map(function ($log) {
                return [
                    $log->created_at ? date('H:i:s', strtotime($log->created_at)) : 'N/A',
                    $log->template ?? 'N/A',
                    $log->status ?? 'unknown',
                    $log->sent_at ? date('H:i:s', strtotime($log->sent_at)) : 'Not sent',
                    substr($log->recipient ?? 'N/A', -4),
                ];
            })->toArray()
        );

        // Check MSG91 configuration
        $this->newLine();
        $this->info("⚙️  MSG91 Configuration:");
        $this->line("   Enabled: " . (config('services.msg91.enabled') ? '✅ Yes' : '❌ No'));
        $this->line("   API Key: " . (config('services.msg91.key') ? '✅ Configured' : '❌ Not set'));
        $this->line("   Sender ID: " . (config('services.msg91.sender_id', 'Not set')));
        $this->newLine();

        // Check recent Laravel logs for errors
        $this->info("🔍 Recent Errors (last 20):");
        $errorLogs = DB::table('notification_logs')
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        if ($errorLogs->isEmpty()) {
            $this->line("   ✅ No failed SMS records found");
        } else {
            foreach ($errorLogs as $log) {
                $this->warn("   ❌ {$log->template} - {$log->created_at}");
            }
        }

        $this->newLine();
        $this->info("💡 Next Steps:");
        $this->line("   1. Check MSG91 dashboard: https://control.msg91.com/");
        $this->line("   2. Verify account credits");
        $this->line("   3. Check DLT template approval status");
        $this->line("   4. Verify sender ID matches DLT approval");

        return Command::SUCCESS;
    }
}

