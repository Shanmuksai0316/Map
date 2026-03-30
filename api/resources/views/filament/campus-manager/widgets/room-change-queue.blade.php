<x-filament::section>
    <x-slot name="heading">Room Change Queue</x-slot>
    <div class="space-y-3">
        <div class="flex items-center justify-between">
            <span class="text-sm text-[#2F4F2F]/80">Pending requests</span>
            <span class="text-xl font-semibold">{{ $pending }}</span>
        </div>
        <div class="flex items-center justify-between">
            <span class="text-sm text-[#2F4F2F]/80">Urgent (&gt;3 days)</span>
            <span class="text-xl font-semibold text-danger-600">{{ $urgent }}</span>
        </div>
        <div class="flex items-center justify-between text-sm text-[#2F4F2F]/80">
            <span>Oldest request</span>
            <span>{{ $oldestStudent ? $oldestStudent.' · '.$oldestAge.' old' : '—' }}</span>
        </div>
    </div>
</x-filament::section>

