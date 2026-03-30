<x-filament::section>
    <x-slot name="heading">Checkout Summary</x-slot>
    <div class="grid gap-3">
        <div class="flex items-center justify-between">
            <span class="text-sm text-[#2F4F2F]/80">Upcoming (7 days)</span>
            <span class="text-lg font-semibold">{{ $upcoming }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-sm text-[#2F4F2F]/80">Overdue</span>
            <span class="text-lg font-semibold text-danger-600">{{ $overdue }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-sm text-[#2F4F2F]/80">In progress</span>
            <span class="text-lg font-semibold text-warning-600">{{ $inProgress }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-sm text-[#2F4F2F]/80">Completed today</span>
            <span class="text-lg font-semibold text-success-600">{{ $completedToday }}</span>
        </div>
    </div>
</x-filament::section>

