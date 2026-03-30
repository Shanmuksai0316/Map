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

class ChecklistEscalationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(ChecklistNotifier $notifier): void
    {
        $nowIst = Carbon::now('Asia/Kolkata');
        $dueAt = $nowIst->copy()->setTime(21, 30)->startOfMinute();

        if ($nowIst->lessThan($dueAt->copy()->addMinutes(60))) {
            return; // Not yet time for escalation
        }

        ChecklistInstance::query()
            ->where('status', 'Pending')
            ->whereDate('date', $nowIst->toDateString())
            ->each(function (ChecklistInstance $instance) use ($notifier): void {
                $exists = ChecklistJobEvent::query()
                    ->where('instance_id', $instance->id)
                    ->where('event_type', 'escalation')
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::transaction(function () use ($instance, $notifier): void {
                    ChecklistJobEvent::query()->firstOrCreate(
                        [
                            'instance_id' => $instance->id,
                            'event_type' => 'escalation',
                        ],
                        [
                            'tenant_id' => $instance->tenant_id,
                        ]
                    );

                    $notifier->escalation($instance);
                });
            });
    }
}
