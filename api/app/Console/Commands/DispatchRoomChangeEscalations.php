<?php

namespace App\Console\Commands;

use App\Jobs\SendRoomChangeEscalationNotification;
use App\Services\RoomChanges\RoomChangeReminderService;
use Illuminate\Console\Command;

class DispatchRoomChangeEscalations extends Command
{
    protected $signature = 'room-changes:escalate {--tenant=* : Limit to specific tenant IDs}';

    protected $description = 'Dispatch SLA breach notifications for pending room change requests.';

    public function handle(RoomChangeReminderService $reminderService): int
    {
        $tenantIds = array_filter((array) $this->option('tenant'));

        $pending = $reminderService->pendingEscalations($tenantIds ?: null);

        if ($pending->isEmpty()) {
            $this->info('No pending escalations found.');

            return self::SUCCESS;
        }

        foreach ($pending as $roomChange) {
            SendRoomChangeEscalationNotification::dispatch($roomChange->id);
            $reminderService->markEscalated($roomChange);
        }

        $this->info(sprintf('Dispatched %d room change escalations.', $pending->count()));

        return self::SUCCESS;
    }
}

