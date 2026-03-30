<?php

namespace App\Console\Commands;

use App\Services\Notifications\SmsService;
use Illuminate\Console\Command;

class TestMsg91Sms extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sms:test-msg91 {phone=+919663275871}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send test SMS via MSG91 to specified phone number';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $phone = $this->argument('phone');
        
        // Ensure phone has country code
        if (!str_starts_with($phone, '+')) {
            if (str_starts_with($phone, '91')) {
                $phone = '+' . $phone;
            } else {
                $phone = '+91' . $phone;
            }
        }

        $this->info("Sending test SMS to: {$phone}");
        
        // Check configuration
        if (!config('services.msg91.enabled')) {
            $this->error('MSG91 is not enabled! Set MSG91_ENABLED=true in .env');
            return Command::FAILURE;
        }

        if (!config('services.msg91.key')) {
            $this->error('MSG91 API Key is not configured! Set MSG91_API_KEY in .env');
            return Command::FAILURE;
        }

        $this->info('Configuration: OK');
        $this->info('Sender ID: ' . config('services.msg91.sender_id', 'Not set'));
        $this->info('Template ID: ' . (config('services.msg91.templates.otp_login') ?: 'Not set'));
        
        // Build message with MSG91 format
        $message = 'Your OMAPMS login code is 123456. It expires in 10 minutes.';
        
        $this->info("Message: {$message}");
        $this->newLine();

        try {
            $smsService = app(SmsService::class);
            
            $this->info('Sending SMS...');
            $result = $smsService->send(
                $phone,
                $message,
                'test-tenant',
                'otp_login',
                ['purpose' => 'test', 'test' => true]
            );
            
            if ($result) {
                $this->newLine();
                $this->info('✅ SMS sent successfully!');
                $this->newLine();
                $this->info('Next steps:');
                $this->line('1. Check your phone (' . $phone . ') for the SMS');
                $this->line('2. Check logs: tail -f storage/logs/laravel.log | grep -i sms.msg91');
                $this->line('3. Check MSG91 dashboard for delivery report');
                $this->line('4. Check notification_logs table in database');
                return Command::SUCCESS;
            } else {
                $this->newLine();
                $this->error('❌ SMS failed to send');
                $this->line('Check logs: storage/logs/laravel.log');
                return Command::FAILURE;
            }
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('❌ Error: ' . $e->getMessage());
            $this->line('File: ' . $e->getFile() . ':' . $e->getLine());
            return Command::FAILURE;
        }
    }
}


