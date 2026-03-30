<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <x-heroicon-o-calendar class="w-5 h-5 text-[#2F4F2F]/80 shrink-0" />
                <span class="text-sm font-medium text-[#2F4F2F]/80">Time Range:</span>
                <div class="flex items-center gap-2">
                    @foreach ([
                        1 => 'Today',
                        7 => '7 Days',
                        14 => '14 Days',
                        30 => '30 Days',
                    ] as $days => $label)
                        <button
                            wire:click="setRange({{ $days }})"
                            @class([
                                'px-3 py-1.5 text-sm rounded-lg font-medium transition-colors',
                                'campus-dashboard-filter-active shadow-sm' => $rangeDays === $days,
                                'bg-gray-100 text-gray-700 hover:bg-gray-200' => $rangeDays !== $days,
                            ])
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </div>
            </div>
            <div class="text-xs text-[#2F4F2F]/80">
                <x-heroicon-o-arrow-path class="w-3 h-3 inline" />
                Auto-refreshes every 60s
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
