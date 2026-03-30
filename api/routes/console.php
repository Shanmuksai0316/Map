<?php

use App\Jobs\AttendanceCloseJob;
use App\Jobs\AttendanceEnsureTodayJob;
use App\Jobs\Attendance\OpenSessionsJob;
use App\Jobs\Attendance\ActivateSessionsJob;
use App\Jobs\Attendance\CloseSessionsJob;
use App\Jobs\ChecklistAutoCreateDailyJob;
use App\Jobs\ChecklistReminderJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new AttendanceEnsureTodayJob())->everyFifteenMinutes();
Schedule::job(new AttendanceCloseJob(app(\App\Services\AuditLogger::class)))->everyFifteenMinutes();
Schedule::job(new ChecklistAutoCreateDailyJob())->dailyAt('05:00')->timezone('Asia/Kolkata');
Schedule::job(new ChecklistReminderJob())->everyFifteenMinutes();
// Overdue + Campus Manager notification handled by checklists:remind --window=overdue at midnight (Kernel.php)

// Attendance V2 Scheduling
Schedule::job(new OpenSessionsJob())->timezone('Asia/Kolkata')->dailyAt('17:30');
Schedule::job(new ActivateSessionsJob())->timezone('Asia/Kolkata')->everyFiveMinutes();
Schedule::job(new CloseSessionsJob(app(\App\Services\AuditLogger::class)))->timezone('Asia/Kolkata')->hourly();

// Out-Pass Auto-Expiry
Schedule::command('app:expire-outpasses')->daily();

// Attendance Session Auto-Scheduler
Schedule::command('app:attendance-session-scheduler')->everyFifteenMinutes();

// Missed Attendance Incidents
Schedule::command('app:create-missed-attendance-incidents')->everyThirtyMinutes();

// Sports No-Show
Schedule::command('app:mark-sports-no-show')->everyFiveMinutes();

// Data Retention & Purge (runs weekly on Sundays at 2 AM)
Schedule::command('app:purge-old-audit-logs')->weekly()->sundays()->at('02:00');
Schedule::command('app:archive-old-product-events')->weekly()->sundays()->at('02:30');
Schedule::command('app:purge-expired-exports')->daily()->at('03:00');
Schedule::command('app:purge-old-import-files')->daily()->at('03:30');
Schedule::command('app:archive-old-attachments')->weekly()->sundays()->at('04:00');
