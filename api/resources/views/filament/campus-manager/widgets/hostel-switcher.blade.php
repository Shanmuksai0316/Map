<x-filament-widgets::widget>
    <x-filament::section>
        <div class="flex items-center gap-3">
            <x-heroicon-o-home class="w-5 h-5 text-[#2F4F2F]/80 shrink-0" />
            <span class="text-sm font-medium text-[#2F4F2F]/80">Hostel:</span>
            <div class="flex items-center gap-2 flex-wrap">
                {{-- All Hostels button --}}
                <button
                    wire:click="switchHostel(null)"
                    @class([
                        'px-3 py-1.5 text-sm rounded-lg font-medium transition-colors',
                        'campus-dashboard-filter-active shadow-sm' => !$this->getSelectedHostelId(),
                        'bg-gray-100 text-gray-700 hover:bg-gray-200' => $this->getSelectedHostelId(),
                    ])
                >
                    All Hostels
                </button>

                {{-- Individual hostel buttons --}}
                @foreach ($this->getHostels() as $id => $name)
                    <button
                        wire:click="switchHostel({{ $id }})"
                        @class([
                            'px-3 py-1.5 text-sm rounded-lg font-medium transition-colors',
                            'campus-dashboard-filter-active shadow-sm' => $this->getSelectedHostelId() == $id,
                            'bg-gray-100 text-gray-700 hover:bg-gray-200' => $this->getSelectedHostelId() != $id,
                        ])
                    >
                        {{ $name }}
                    </button>
                @endforeach
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
