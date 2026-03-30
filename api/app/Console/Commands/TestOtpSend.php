<?php

namespace App\Console\Commands;

use App\Services\FilamentOtpService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TestOtpSend extends Command
{
    protected $signature = 'otp:test-send {phone : Phone number to send OTP to (e.g., +917975452363)}';
    protected $description = 'Test sending OTP to a specific phone number';

    public function handle(): int
    {
        $phoneNumber = $this->argument('phone');
        
        // Normalize phone number
        $normalizedPhone = preg_replace('/\D+/', '', $phoneNumber);
        $possiblePhones = array_values(array_unique(array_filter([
            $phoneNumber,
            $normalizedPhone,
            str_starts_with($phoneNumber, '+') ? ltrim($phoneNumber, '+') : null,
            str_starts_with($phoneNumber, '+') ? $phoneNumber : '+' . $phoneNumber,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '+91' . $normalizedPhone : null,
            $normalizedPhone !== '' && strlen($normalizedPhone) === 10 ? '91' . $normalizedPhone : null,
            preg_match('/^(\+?91)?(\d{10})$/', $normalizedPhone, $matches) ? ($matches[2] ?? null) : null,
        ])));

        $this->info("========================================");
        $this->info("Testing OTP Send");
        $this->info("========================================");
        $this->info("Phone Number: {$phoneNumber}");
        $this->info("Normalized formats: " . implode(', ', $possiblePhones));
        $this->newLine();

        try {
            // Find user with this phone number (across all tenants)
            $this->info("Searching for user with phone number...");
            
            $user = User::where(function ($query) use ($possiblePhones) {
                foreach ($possiblePhones as $phone) {
                    $query->orWhere('phone', 'LIKE', "%{$phone}%");
                }
            })->first();
            
            if (!$user) {
                // Try exact matches
                foreach ($possiblePhones as $phone) {
                    $user = User::where('phone', $phone)->first();
                    if ($user) break;
                }
            }
            
            if (!$user) {
                $this->error("No user found with phone number: {$phoneNumber}");
                $this->warn("Please ensure a user exists with this phone number in the database.");
                return 1;
            }
            
            $this->info("✅ Found user: {$user->name} (ID: {$user->id})");
            
            // Get tenant for this user
            if (!$user->tenant_id) {
                $this->error("User has no tenant_id assigned");
                return 1;
            }
            
            $tenant = Tenant::find($user->tenant_id);
            if (!$tenant) {
                $this->error("Tenant not found for user (tenant_id: {$user->tenant_id})");
                return 1;
            }
            
            $this->info("✅ Found tenant: {$tenant->name} (Code: {$tenant->code})");
            
            // Initialize tenant context
            \Stancl\Tenancy\Facades\Tenancy::initialize($tenant);
            $this->info("✅ Tenant context initialized");
            $this->newLine();
            
            // Now send OTP
            $this->info("Sending OTP via FilamentOtpService...");
            $otpService = app(FilamentOtpService::class);
            $result = $otpService->sendOtp($phoneNumber);
            
            $this->newLine();
            $this->info("✅ OTP Sent Successfully!");
            $this->line("━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━");
            $this->line("User ID: {$result['user_id']}");
            $this->line("User Name: {$result['user_name']}");
            $this->line("Expires in: {$result['expires_in']} seconds (5 minutes)");
            
            // Check logs for OTP (in local/dev environments)
            if (app()->environment('local') || config('otp.automation_secret')) {
                $this->newLine();
                $this->comment("📝 Check Laravel logs for OTP code:");
                $this->comment("   tail -f storage/logs/laravel.log | grep OTP");
            }
            
            $this->newLine();
            $this->info("📱 Check your phone ({$phoneNumber}) for the OTP SMS.");
            $this->info("📊 Check MSG91 dashboard for delivery status:");
            $this->line("   https://control.msg91.com/");
            $this->line("   Look for messages to: {$phoneNumber}");
            $this->newLine();
            $this->comment("💡 If SMS doesn't arrive, check:");
            $this->comment("   1. MSG91 account credits");
            $this->comment("   2. DLT template approval (Template ID: 67687b88d6fc0551fc4c4932)");
            $this->comment("   3. Sender ID approval (MAPHMS)");
            $this->comment("   4. Phone number DND status");
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            
            if (strpos($e->getMessage(), 'Tenant context') !== false) {
                $this->warn("💡 Tip: The script should have initialized tenant context automatically.");
                $this->warn("   If this error persists, check that the user has a valid tenant_id.");
            }
            
            if (strpos($e->getMessage(), 'No account exists') !== false) {
                $this->warn("💡 Tip: Create a user with this phone number first:");
                $this->warn("   - Via admin panel: https://admin.mapservices.in/admin/staff-managements/create");
                $this->warn("   - Or via database directly");
            }
            
            $this->error("\nStack trace (first 500 chars):");
            $this->line(substr($e->getTraceAsString(), 0, 500));
            
            return 1;
        }
    }
}

