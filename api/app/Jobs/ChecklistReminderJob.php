<?php

namespace App\Jobs;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Domain\Checklists\Models\ChecklistJobEvent;
use App\Services\Notifications\ChecklistNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ChecklistReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ChecklistNotifier $notifier): void
    {
        $nowIst = Carbon::now('Asia/Kolkata');
        $dueAt = $nowIst->copy()->setTime(21, 30)->startOfMinute();

        ChecklistInstance::query()
            ->where('status', 'Pending')
            ->whereDate('date', $nowIst->toDateString())
            ->each(function (ChecklistInstance $instance) use ($dueAt, $nowIst, $notifier): void {
                $diff = $dueAt->diffInMinutes($nowIst, false);

                $phase = match (true) {
                    $diff >= -60 && $diff < -45 => 'T-60',
                    $diff >= -15 && $diff < -5 => 'T-15',
                    default => null,
                };

                if (! $phase) {
                    return;
                }

                $exists = ChecklistJobEvent::query()
                    ->where('instance_id', $instance->id)
                    ->where('event_type', 'reminder')
                    ->where('phase', $phase)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::transaction(function () use ($instance, $phase, $notifier): void {
                    ChecklistJobEvent::query()->firstOrCreate(
                        [
                            'instance_id' => $instance->id,
                            'event_type' => 'reminder',
                            'phase' => $phase,
                        ],
                        [
                            'tenant_id' => $instance->tenant_id,
                        ]
                    );

                    $notifier->reminder($instance, $phase);
                });
            });
    }
}
