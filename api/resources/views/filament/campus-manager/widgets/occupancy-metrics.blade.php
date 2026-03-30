<x-filament::section>
    <x-slot name="heading">Occupancy Metrics</x-slot>
    <div class="grid gap-3">
        <div>
            <p class="text-sm text-[#2F4F2F]/80">Occupancy Rate</p>
            <p class="text-3xl font-semibold">{{ $occupancyRate }}%</p>
        </div>
        <div class="flex items-center justify-between text-sm text-[#2F4F2F]/80">
            <span>Available Beds</span>
            <span class="text-lg font-semibold">{{ $availableBeds }} / {{ $totalBeds }}</span>
        </div>
        <div class="flex items-center justify-between text-sm text-[#2F4F2F]/80">
            <span>Unassigned Students</span>
            <span class="text-lg font-semibold">{{ $unassignedStudents }}</span>
        </div>
        <div class="flex items-center justify-between text-sm text-[#2F4F2F]/80">
            <span>Active Notices</span>
            <span class="text-lg font-semibold">{{ $activeNotices }}</span>
        </div>
    </div>
</x-filament::section>

