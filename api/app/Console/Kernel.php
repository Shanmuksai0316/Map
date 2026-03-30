<?php

namespace App\Console;

use App\Console\Commands\AssignTulipStaffToTenant;
use App\Console\Commands\DispatchChecklistReminders;
use App\Console\Commands\DispatchCheckoutReminders;
use App\Console\Commands\DispatchRoomChangeEscalations;
use App\Console\Commands\PurgeDataByRetentionPolicy;
use App\Console\Commands\RetryWebhookDeliveries;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array<class-string>
     */
    protected $commands = [
        DispatchCheckoutReminders::class,
        DispatchChecklistReminders::class,
        DispatchRoomChangeEscalations::class,
        \App\Console\Commands\CheckDelayedRequests::class,
        PurgeDataByRetentionPolicy::class,
        RetryWebhookDeliveries::class,
        AssignTulipStaffToTenant::class,
        \App\Console\Commands\TestAllSmsTemplates::class,
        \App\Console\Commands\GenerateSmsReport::class,
        \App\Console\Commands\SmsDetailedReport::class,
        \App\Console\Commands\TestSmsOneByOne::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('inspire')->hourly();
        $schedule->command('demo:data-seed')->twiceDaily();
        $schedule->command('checkouts:remind')->dailyAt('09:00');
        $schedule->command('checklists:remind --window=morning')->dailyAt(sprintf('%02d:00', config('reminders.checklists.morning_hour', 9)));
        $schedule->command('checklists:remind --window=afternoon')->dailyAt(sprintf('%02d:00', config('reminders.checklists.afternoon_hour', 15)));
        // Overdue = midnight only (one checklist per day). Notify assignee + Campus Managers.
        $schedule->command('checklists:remind --window=overdue')->dailyAt('00:05')->timezone('Asia/Kolkata');
        $schedule->command('room-changes:escalate')->hourly();
        $schedule->command('approvals:check-sla')->hourly();
        $schedule->command('requests:check-delayed')->hourly();
        $schedule->command('retention:purge')->dailyAt('03:00');
        $schedule->command('webhooks:retry')->everyTenMinutes();
        $schedule->command('outpasses:expire')->everyFiveMinutes();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}

