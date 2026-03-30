<?php

namespace App\Services\Checklists;

use App\Domain\Checklists\Models\ChecklistInstance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class ChecklistReminderService
{
    public function dueForWindow(string $window): Collection
    {
        return match ($window) {
            'afternoon' => $this->afternoonPending(),
            'overdue' => $this->overduePending(),
            default => $this->morningPending(),
        };
    }

    public function markNotified(ChecklistInstance $instance, string $window): void
    {
        $now = Carbon::now();

        $attributes = match ($window) {
            'afternoon' => ['afternoon_reminded_at' => $now],
            'overdue' => ['overdue_notified_at' => $now],
            default => ['morning_reminded_at' => $now],
        };

        $instance->forceFill($attributes)->save();
    }

    protected function morningPending(): Collection
    {
        $today = Carbon::today();

        return ChecklistInstance::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['Pending', 'SentBack'])
            ->whereNull('morning_reminded_at')
            ->get();
    }

    protected function afternoonPending(): Collection
    {
        $today = Carbon::today();

        return ChecklistInstance::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['Pending', 'SentBack'])
            ->whereNull('afternoon_reminded_at')
            ->get();
    }

    /**
     * Overdue = only after midnight. One checklist per day; overdue when the checklist date
     * is in the past (strictly before today) and still not submitted.
     */
    protected function overduePending(): Collection
    {
        $today = Carbon::today('Asia/Kolkata');

        return ChecklistInstance::query()
            ->whereIn('status', ['Pending', 'SentBack'])
            ->whereNull('overdue_notified_at')
            ->whereDate('date', '<', $today)
            ->get();
    }

    public function summary(): array
    {
        $today = Carbon::today();
        $pendingToday = ChecklistInstance::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['Pending', 'SentBack'])
            ->count();

        $morningPending = ChecklistInstance::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['Pending', 'SentBack'])
            ->whereNull('morning_reminded_at')
            ->count();

        $afternoonPending = ChecklistInstance::query()
            ->whereDate('date', $today)
            ->whereIn('status', ['Pending', 'SentBack'])
            ->whereNull('afternoon_reminded_at')
            ->count();

        $today = Carbon::today('Asia/Kolkata');
        $overdue = ChecklistInstance::query()
            ->whereIn('status', ['Pending', 'SentBack'])
            ->whereNull('overdue_notified_at')
            ->whereDate('date', '<', $today)
            ->count();

        return [
            'pendingToday' => $pendingToday,
            'morningPending' => $morningPending,
            'afternoonPending' => $afternoonPending,
            'overdue' => $overdue,
        ];
    }
}

