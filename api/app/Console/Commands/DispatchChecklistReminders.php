<?php

namespace App\Console\Commands;

use App\Jobs\SendChecklistReminderNotification;
use App\Services\Checklists\ChecklistReminderService;
use App\Services\Notifications\ChecklistNotifier;
use Illuminate\Console\Command;

class DispatchChecklistReminders extends Command
{
    protected $signature = 'checklists:remind {--window=morning : morning|afternoon|overdue}';

    protected $description = 'Dispatch checklist reminder notifications for staff assignments.';

    public function handle(ChecklistReminderService $service, ChecklistNotifier $notifier): int
    {
        $window = strtolower((string) $this->option('window'));

        if (! in_array($window, ['morning', 'afternoon', 'overdue'], true)) {
            $this->error('Invalid window. Allowed values: morning, afternoon, overdue.');

            return self::INVALID;
        }

        $instances = $service->dueForWindow($window);

        if ($instances->isEmpty()) {
            $this->info('No checklists queued for reminders.');

            return self::SUCCESS;
        }

        if ($window === 'overdue') {
            // Overdue = after midnight only. Notify assignee + Campus Managers (staff checklist overdue, not completed).
            foreach ($instances as $instance) {
                $notifier->escalation($instance);
                $service->markNotified($instance, 'overdue');
            }
            $this->info(sprintf('Notified assignees and Campus Managers for %d overdue checklist(s).', $instances->count()));
        } else {
            foreach ($instances as $instance) {
                SendChecklistReminderNotification::dispatch($instance->id, $window);
                $service->markNotified($instance, $window);
            }
            $this->info(sprintf('Dispatched %d %s checklist reminders.', $instances->count(), $window));
        }

        return self::SUCCESS;
    }
}

