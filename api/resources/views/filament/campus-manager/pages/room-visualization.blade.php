<x-filament-panels::page>
    {{-- Block & Floor Filters --}}
    <div class="mb-4 flex items-center gap-4 flex-wrap">
        <div class="flex items-center gap-2">
            <label for="filterBlock" class="text-sm font-medium text-[#2F4F2F]/80">Block:</label>
            <select wire:model.live="filterBlock" id="filterBlock" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">All Blocks</option>
                @foreach ($this->getBlockOptions() as $code => $label)
                    <option value="{{ $code }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-center gap-2">
            <label for="filterFloor" class="text-sm font-medium text-[#2F4F2F]/80">Floor:</label>
            <select wire:model.live="filterFloor" id="filterFloor" class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                <option value="">All Floors</option>
                @foreach ($this->getFloorOptions() as $code => $label)
                    <option value="{{ $code }}">Floor {{ $label }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($hostels->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-[#2F4F2F]/80 dark:border-gray-700 dark:text-gray-300">
            No rooms found. Adjust filters or add rooms under <strong>Rooms & Allocation</strong> to visualize occupancy.
        </div>
    @else
        <div class="space-y-6">
            @foreach ($hostels as $hostel)
                <x-filament::section
                    :heading="$hostel->name"
                    :description="$hostel->rooms->count() . ' room' . ($hostel->rooms->count() === 1 ? '' : 's')"
                >
                    <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                        @forelse ($hostel->rooms as $room)
                            <div
                                class="cursor-pointer rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:ring-2 hover:ring-primary-500 dark:border-gray-700 dark:bg-gray-900"
                                wire:click="showRoomDetails({{ $room->id }})"
                                wire:key="room-card-{{ $room->id }}"
                            >
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">Room</p>
                                        <h3 class="text-lg font-semibold text-[#2F4F2F] dark:text-white">
                                            {{ $room->number }}
                                        </h3>
                                        <p class="text-xs text-[#2F4F2F]/80 dark:text-gray-400">{{ $room->hostel_name }}</p>
                                    </div>
                                    <x-filament::badge color="{{ $room->is_active ? 'success' : 'warning' }}">
                                        {{ $room->is_active ? 'Active' : 'Inactive' }}
                                    </x-filament::badge>
                                </div>

                                <dl class="mt-4 space-y-2 text-sm text-[#2F4F2F] dark:text-[#2F4F2F]">
                                    <div class="flex justify-between">
                                        <dt class="text-[#2F4F2F]/80 dark:text-gray-400">Block / Floor</dt>
                                        <dd>{{ $room->block_code ?? '—' }} / {{ $room->floor_code ?? '—' }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-[#2F4F2F]/80 dark:text-gray-400">Capacity</dt>
                                        <dd>{{ $room->beds_total_count }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-[#2F4F2F]/80 dark:text-gray-400">Available</dt>
                                        <dd>{{ $room->beds_available_count }}</dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-[#2F4F2F]/80 dark:text-gray-400">Occupied</dt>
                                        <dd>{{ max($room->beds_total_count - $room->beds_available_count, 0) }}</dd>
                                    </div>
                                </dl>
                            </div>
                        @empty
                            <div class="col-span-full rounded-xl border border-dashed border-gray-300 p-6 text-center text-sm text-[#2F4F2F]/80 dark:border-gray-700 dark:text-gray-300">
                                No rooms configured for {{ $hostel->name }}.
                            </div>
                        @endforelse
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    @endif

    @if ($isRoomModalOpen && $roomModalData)
        <div class="fixed inset-0 z-50 flex items-center justify-center bg-gray-900/50 p-4" wire:click="closeRoomModal">
            <div class="w-full max-w-4xl overflow-hidden rounded-lg bg-white shadow-xl dark:bg-gray-800" wire:click.stop>
                <!-- Header -->
                <div class="border-b border-gray-200 bg-gray-50 px-6 py-4 dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-[#2F4F2F] dark:text-white">
                                Room {{ $roomModalData['room_number'] }}
                            </h2>
                            <p class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">
                                {{ $roomModalData['hostel_name'] }}
                                @if ($roomModalData['block_code'] || $roomModalData['floor_code'])
                                    • 
                                    @if ($roomModalData['block_code'])
                                        Block {{ $roomModalData['block_code'] }}
                                    @endif
                                    @if ($roomModalData['floor_code'])
                                        Floor {{ $roomModalData['floor_code'] }}
                                    @endif
                                @endif
                            </p>
                        </div>
                        <button
                            type="button"
                            class="rounded-md p-2 text-gray-400 hover:bg-gray-100 hover:text-[#2F4F2F]/80 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                            wire:click="closeRoomModal"
                            aria-label="Close"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Beds List -->
                <div class="max-h-[70vh] overflow-y-auto p-6">
                    @forelse ($roomModalData['beds'] as $bed)
                        <div class="mb-4 rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">Bed</p>
                                    <h3 class="text-lg font-semibold text-[#2F4F2F] dark:text-white">{{ $bed['code'] }}</h3>
                                </div>
                                <x-filament::badge color="{{ $bed['allocation'] ? 'success' : 'gray' }}">
                                    {{ $bed['allocation'] ? 'Occupied' : ucfirst($bed['status']) }}
                                </x-filament::badge>
                            </div>

                            @if ($bed['allocation'])
                                <div class="space-y-3 border-t border-gray-200 pt-3 dark:border-gray-700">
                                    <div>
                                        <p class="text-sm font-medium text-[#2F4F2F] dark:text-white">
                                            {{ $bed['allocation']['student_name'] ?? 'Student' }}
                                        </p>
                                        <p class="text-xs text-[#2F4F2F]/80 dark:text-gray-400">
                                            UID: {{ $bed['allocation']['student_uid'] ?? '—' }}
                                            @if ($bed['allocation']['roll_no'])
                                                • Roll: {{ $bed['allocation']['roll_no'] }}
                                            @endif
                                        </p>
                                    </div>

                                    @if ($bed['allocation']['program'] || $bed['allocation']['year_of_study'])
                                        <div class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">
                                            @if ($bed['allocation']['program'])
                                                {{ $bed['allocation']['program'] }}
                                            @endif
                                            @if ($bed['allocation']['year_of_study'])
                                                • Year {{ $bed['allocation']['year_of_study'] }}
                                            @endif
                                        </div>
                                    @endif

                                    @if ($bed['allocation']['phone'] || $bed['allocation']['email'])
                                        <div class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">
                                            @if ($bed['allocation']['phone'])
                                                {{ $bed['allocation']['phone'] }}
                                            @endif
                                            @if ($bed['allocation']['email'])
                                                @if ($bed['allocation']['phone']) • @endif
                                                {{ $bed['allocation']['email'] }}
                                            @endif
                                        </div>
                                    @endif

                                    @if ($bed['allocation']['effective_from'])
                                        <p class="text-xs text-[#2F4F2F]/80 dark:text-gray-400">
                                            Allocated on {{ $bed['allocation']['effective_from'] }}
                                        </p>
                                    @endif

                                    @if ($bed['allocation']['note'])
                                        <div class="rounded-md bg-gray-100 p-2 dark:bg-gray-700">
                                            <p class="text-xs text-[#2F4F2F]/80 dark:text-gray-300">{{ $bed['allocation']['note'] }}</p>
                                        </div>
                                    @endif

                                    <div class="pt-2">
                                        <button
                                            type="button"
                                            wire:click="removeAllocation({{ $bed['allocation']['id'] }}, {{ $roomModalData['room_id'] }})"
                                            wire:confirm="Are you sure you want to remove this student from this bed? The bed will become available."
                                            class="inline-flex items-center gap-2 rounded-md bg-danger-600 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-danger-700"
                                        >
                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                            Remove Allocation
                                        </button>
                                    </div>
                                </div>
                            @else
                                <div class="border-t border-gray-200 pt-3 text-center dark:border-gray-700">
                                    <p class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">Available for Allocation</p>
                                </div>
                            @endif
                        </div>
                    @empty
                        <div class="rounded-lg border border-dashed border-gray-300 bg-gray-50 p-8 text-center dark:border-gray-700 dark:bg-gray-800">
                            <p class="text-sm text-[#2F4F2F]/80 dark:text-gray-400">No beds configured for this room.</p>
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>

