<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class SmsDetailedReport extends Command
{
    protected $signature = 'sms:detailed-report {phone=+917975452363}';
    protected $description = 'Generate comprehensive SMS delivery report with MSG91 API responses';

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
        $this->info("COMPREHENSIVE SMS DELIVERY REPORT");
        $this->info("===========================================");
        $this->info("Phone: {$phone}");
        $this->newLine();

        // 1. Check notification logs
        $this->info("1️⃣ DATABASE RECORDS:");
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
            $this->warn("   ⚠️  No SMS records found in database");
        } else {
            $sent = $logs->where('status', 'sent')->count();
            $failed = $logs->where('status', 'failed')->count();
            $this->line("   📊 Total records: " . $logs->count());
            $this->line("   ✅ Marked as 'sent': {$sent}");
            $this->line("   ❌ Marked as 'failed': {$failed}");
        }
        $this->newLine();

        // 2. Check Laravel logs for MSG91 API responses
        $this->info("2️⃣ MSG91 API RESPONSES (from Laravel logs):");
        $logFile = storage_path('logs/laravel.log');
        
        if (!File::exists($logFile)) {
            $this->warn("   ⚠️  Laravel log file not found");
        } else {
            $logContent = File::get($logFile);
            
            // Extract MSG91 API responses
            preg_match_all('/"sms\.msg91\.api_response".*?\{.*?\}/s', $logContent, $matches);
            
            if (empty($matches[0])) {
                $this->warn("   ⚠️  No MSG91 API response logs found");
            } else {
                $this->line("   📋 Found " . count($matches[0]) . " API response entries");
                
                // Parse last 5 responses
                $recentResponses = array_slice($matches[0], -5);
                foreach ($recentResponses as $idx => $match) {
                    $this->line("   Response #" . ($idx + 1) . ":");
                    
                    // Try to extract key info
                    if (preg_match('/"http_status":\s*(\d+)/', $match, $statusMatch)) {
                        $this->line("      HTTP Status: " . $statusMatch[1]);
                    }
                    if (preg_match('/"template":\s*"([^"]+)"/', $match, $templateMatch)) {
                        $this->line("      Template: " . $templateMatch[1]);
                    }
                    if (preg_match('/"response_body":\s*"([^"]+)"/', $match, $bodyMatch)) {
                        $body = substr($bodyMatch[1], 0, 200);
                        $this->line("      Response: " . $body);
                    }
                    $this->newLine();
                }
            }
        }
        $this->newLine();

        // 3. Configuration check
        $this->info("3️⃣ CONFIGURATION:");
        $this->line("   MSG91 Enabled: " . (config('services.msg91.enabled') ? '✅ Yes' : '❌ No'));
        $this->line("   API Key: " . (config('services.msg91.key') ? '✅ Set (' . substr(config('services.msg91.key'), 0, 8) . '...)' : '❌ Not set'));
        $this->line("   Sender ID: " . (config('services.msg91.sender_id', 'Not set')));
        $this->newLine();

        // 4. Recent errors
        $this->info("4️⃣ RECENT ERRORS:");
        $errorLogs = DB::table('notification_logs')
            ->where('channel', 'sms')
            ->where('status', 'failed')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        if ($errorLogs->isEmpty()) {
            $this->line("   ✅ No failed records in database");
        } else {
            foreach ($errorLogs as $log) {
                $this->warn("   ❌ {$log->template} at {$log->created_at}");
            }
        }
        $this->newLine();

        // 5. Recommendations
        $this->info("5️⃣ DIAGNOSIS & RECOMMENDATIONS:");
        $this->newLine();
        
        if ($logs->isEmpty()) {
            $this->warn("   ⚠️  No SMS records found - SMS service may not be sending");
            $this->line("   💡 Check: Is MSG91_ENABLED=true in .env?");
        } elseif ($sent > 0 && $failed === 0) {
            $this->info("   ✅ All SMS marked as 'sent' in database");
            $this->warn("   ⚠️  But messages not arriving - likely MSG91 account issue:");
            $this->line("      • Check MSG91 dashboard: https://control.msg91.com/");
            $this->line("      • Verify account credits");
            $this->line("      • Check DLT template approval status");
            $this->line("      • Verify PE-ID/TM-ID binding");
            $this->line("      • Check if number is on DND");
        } else {
            $this->error("   ❌ Some SMS failed - check error logs above");
        }
        
        $this->newLine();
        $this->info("6️⃣ NEXT STEPS:");
        $this->line("   1. Check MSG91 dashboard delivery reports");
        $this->line("   2. Verify DLT template content matches exactly");
        $this->line("   3. Check MSG91 account credits");
        $this->line("   4. Contact MSG91 support if issue persists");

        return Command::SUCCESS;
    }
}

