<?php

namespace App\Console\Commands;

use App\Jobs\SendCheckoutReminderNotifications;
use Illuminate\Console\Command;

class DispatchCheckoutReminders extends Command
{
    protected $signature = 'checkouts:remind';

    protected $description = 'Queue checkout reminder jobs for upcoming/overdue students';

    public function handle(): int
    {
        SendCheckoutReminderNotifications::dispatch();

        $this->info('Checkout reminder job dispatched.');

        return static::SUCCESS;
    }
}

