<x-filament-panels::page>
    {{ $this->table }}

    @php
        $delayedLaundry = $this->getDelayedLaundryRequests();
    @endphp
    @if($delayedLaundry->isNotEmpty())
        <x-filament::section class="mt-8">
            <x-slot name="heading">
                Delayed laundry requests
            </x-slot>
            <div class="mb-4">
                <label for="delayed-laundry-search" class="sr-only">Search delayed laundry requests</label>
                <input
                    id="delayed-laundry-search"
                    type="text"
                    wire:model.live.debounce.300ms="laundrySearch"
                    placeholder="Search laundry requests (ID, student, room, status)"
                    class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-[#2F4F2F] placeholder-gray-500 shadow-sm focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-100 dark:placeholder-gray-400"
                />
            </div>
            <div class="overflow-x-auto rounded-xl border border-gray-200 dark:border-gray-700">
                <table class="w-full text-sm text-left">
                    <thead class="bg-gray-50 dark:bg-gray-800 text-[#2F4F2F] dark:text-gray-300">
                        <tr>
                            <th scope="col" class="px-4 py-3">Request ID</th>
                            <th scope="col" class="px-4 py-3">Student – Room</th>
                            <th scope="col" class="px-4 py-3">Status</th>
                            <th scope="col" class="px-4 py-3">Requested</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @foreach($delayedLaundry as $lr)
                            <tr class="bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800">
                                <td class="px-4 py-3 font-medium">LR-{{ str_pad((string) $lr->id, 4, '0', STR_PAD_LEFT) }}</td>
                                <td class="px-4 py-3">
                                    {{ $lr->student?->user?->name ?? $lr->student?->full_name ?? '—' }}
                                    –
                                    {{ $lr->student?->roomAllocations?->first()?->roomBed?->room?->number ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-md px-2 py-1 text-xs font-medium ring-1 ring-inset ring-amber-700/20 bg-amber-50 dark:bg-amber-900/20 text-amber-800 dark:text-amber-200">
                                        {{ $lr->status instanceof \BackedEnum && method_exists($lr->status, 'getLabel') ? $lr->status->getLabel() : (string) $lr->status }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">{{ $lr->requested_at?->format('d M Y H:i') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
