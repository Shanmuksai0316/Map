<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Notify\PushNotifier;
use Illuminate\Console\Command;

class SendTestPushToAllCommand extends Command
{
    protected $signature = 'push:send-test-all
                            {--title= : Notification title (default: Test Push – MAP HMS)}
                            {--body= : Notification body}
                            {--dry-run : Only list users that would receive, do not send}';

    protected $description = 'Send a test push notification to every user who has a registered device token (all roles). Use to verify push across Rector, Warden, Guard, etc.';

    public function handle(PushNotifier $pushNotifier): int
    {
        $title = $this->option('title') ?: 'Test Push – MAP HMS';
        $body = $this->option('body') ?: 'This is a test notification. If you see this, push is working for your role.';
        $dryRun = $this->option('dry-run');

        if (!$pushNotifier->enabled()) {
            $this->error('FCM is not enabled or not configured. Run php artisan push:check-config and set FCM in .env.');
            return 1;
        }

        $userIds = \DB::table('push_device_tokens')
            ->distinct()
            ->pluck('user_id');

        if ($userIds->isEmpty()) {
            $this->warn('No device tokens registered. Have users log in from the mobile app first.');
            return 0;
        }

        $this->info(sprintf('Found %d user(s) with registered device(s).', $userIds->count()));
        if ($dryRun) {
            $this->newLine();
            foreach ($userIds as $userId) {
                $user = User::find($userId);
                $name = $user ? $user->name : 'Unknown';
                $role = $user && $user->roles()->exists()
                    ? $user->roles()->pluck('name')->join(', ')
                    : '';
                $this->line(sprintf('  user_id %s – %s  %s', $userId, $name, $role ? "({$role})" : ''));
            }
            $this->newLine();
            $this->line('Run without --dry-run to send the test push to all of them.');
            return 0;
        }

        $sent = 0;
        $failed = 0;
        foreach ($userIds as $userId) {
            $user = User::find($userId);
            $name = $user ? $user->name : "User #{$userId}";

            try {
                // Send push to all registered tokens for this user.
                $pushNotifier->toUser((int) $userId, $title, $body, [
                    'screen' => 'Notifications',
                    'type'   => 'general',
                ]);

                $this->line("  ✓ Sent to {$name} (ID {$userId})");
                $sent++;
            } catch (\Throwable $e) {
                $this->line("  ✗ Failed for {$name} (ID {$userId}): " . $e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Done. Sent: {$sent}, Failed: {$failed}. Check devices and in‑app Notifications screens.");
        return $failed > 0 ? 1 : 0;
    }
}
