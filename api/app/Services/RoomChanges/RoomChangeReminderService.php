<?php

namespace App\Services\RoomChanges;

use App\Domain\RoomChanges\Models\RoomChange;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class RoomChangeReminderService
{
    /**
     * @param  array<int, string>|null  $tenantIds
     */
    public function pendingEscalations(?array $tenantIds = null): Collection
    {
        $now = Carbon::now();
        $cooldown = (int) config('reminders.room_changes.escalation_cooldown_minutes', 120);

        $roomChanges = RoomChange::query()
            ->where('status', 'pending')
            ->when($tenantIds, fn ($query) => $query->whereIn('tenant_id', $tenantIds))
            ->where(function ($query) use ($now): void {
                $query->whereNull('sla_due_at')
                    ->orWhere('sla_due_at', '<=', $now);
            })
            ->where(function ($query) use ($now, $cooldown): void {
                $cooldownCutoff = $now->copy()->subMinutes($cooldown);

                $query->whereNull('last_escalated_at')
                    ->orWhere('last_escalated_at', '<=', $cooldownCutoff);
            })
            ->get();

        return $roomChanges->filter(function (RoomChange $roomChange) use ($now): bool {
            if (! $roomChange->sla_due_at) {
                $roomChange->forceFill([
                    'sla_due_at' => $this->calculateSlaDueAt($roomChange),
                ])->save();
            }

            return optional($roomChange->sla_due_at)->lte($now);
        });
    }

    public function calculateSlaDueAt(RoomChange $roomChange): Carbon
    {
        $base = $roomChange->submitted_at
            ? Carbon::parse($roomChange->submitted_at)
            : Carbon::now();

        return $base->copy()->addHours(config('reminders.room_changes.sla_hours', 24));
    }

    public function markEscalated(RoomChange $roomChange): void
    {
        $roomChange->forceFill([
            'last_escalated_at' => Carbon::now(),
            'last_reminded_at' => Carbon::now(),
        ])->save();
    }

    public function summary(): array
    {
        $now = Carbon::now();

        $pending = RoomChange::query()
            ->where('status', 'pending')
            ->count();

        $overdue = RoomChange::query()
            ->where('status', 'pending')
            ->where(function ($query) use ($now): void {
                $query->whereNull('sla_due_at')
                    ->orWhere('sla_due_at', '<=', $now);
            })
            ->count();

        $next = RoomChange::query()
            ->where('status', 'pending')
            ->whereNotNull('sla_due_at')
            ->orderBy('sla_due_at')
            ->value('sla_due_at');

        return [
            'pending' => $pending,
            'overdue' => $overdue,
            'nextDueAt' => $next ? Carbon::parse($next) : null,
        ];
    }
}

