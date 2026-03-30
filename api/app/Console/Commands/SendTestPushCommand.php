<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notify\PushNotifier;
use Illuminate\Console\Command;

class SendTestPushCommand extends Command
{
    protected $signature = 'push:send-test
                            {user_id : The user ID to send the test notification to}
                            {--title= : Notification title (default: Test Push)}
                            {--body= : Notification body (default: This is a test notification from MAP HMS)}';

    protected $description = 'Send a test push notification to a user (for testing FCM). User must have logged in from the app so their token is in push_device_tokens.';

    public function handle(PushNotifier $pushNotifier): int
    {
        $userId = (int) $this->argument('user_id');
        $title = $this->option('title') ?: 'Test Push';
        $body = $this->option('body') ?: 'This is a test notification from MAP HMS.';

        if (!$pushNotifier->enabled()) {
            $this->error('FCM is not enabled or not configured. Run php artisan push:check-config and set FCM in .env.');
            return 1;
        }

        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found.");
            return 1;
        }

        $tokenCount = \DB::table('push_device_tokens')->where('user_id', $userId)->count();
        if ($tokenCount === 0) {
            $this->warn("User {$user->name} (ID {$userId}) has no registered device tokens.");
            $this->line('They must open the mobile app and log in so the app can register the FCM token.');
            if (!$this->confirm('Send anyway? (Will do nothing but may log no-op)')) {
                return 0;
            }
        }

        $this->info("Sending test push to user: {$user->name} (ID {$userId})…");
        $pushNotifier->toUser($userId, $title, $body, [
            'screen' => 'Notifications',
            'type'   => 'general',
        ]);

        $this->info('Done. Push sent. User should see it both as a push and in the in-app Notifications screen.');
        return 0;
    }
}
