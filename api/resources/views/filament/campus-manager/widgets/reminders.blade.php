<x-filament::section>
    <x-slot name="heading">Reminder Center</x-slot>

    <div class="space-y-4">
        <div class="space-y-2">
            <p class="text-xs uppercase text-[#2F4F2F]/80">Room Changes</p>
            <div class="flex items-center justify-between text-sm">
                <span>Pending</span>
                <span class="font-semibold">{{ $roomSummary['pending'] }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span>Overdue (SLA)</span>
                <span class="font-semibold text-danger-600">{{ $roomSummary['overdue'] }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-[#2F4F2F]/80">
                <span>Next due at</span>
                <span>{{ $roomSummary['nextDueAt']?->timezone('Asia/Kolkata')->format('d M · h:i A') ?? '—' }}</span>
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-xs uppercase text-[#2F4F2F]/80">Checklist Reminders</p>
            <div class="flex items-center justify-between text-sm">
                <span>Morning pending</span>
                <span class="font-semibold">{{ $checklistSummary['morningPending'] }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span>Afternoon pending</span>
                <span class="font-semibold">{{ $checklistSummary['afternoonPending'] }}</span>
            </div>
            <div class="flex items-center justify-between text-sm">
                <span>Overdue</span>
                <span class="font-semibold text-danger-600">{{ $checklistSummary['overdue'] }}</span>
            </div>
            <div class="flex items-center justify-between text-xs text-[#2F4F2F]/80">
                <span>Assignments today</span>
                <span>{{ $checklistSummary['pendingToday'] }}</span>
            </div>
        </div>
    </div>
</x-filament::section>

