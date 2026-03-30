<div class="space-y-6">
    {{-- Stats Summary --}}
    <div class="grid grid-cols-3 gap-4 mb-6">
        <div class="bg-primary-50 dark:bg-primary-900/20 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-primary-600">{{ $stats['floors'] }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Floors</div>
        </div>
        <div class="bg-success-50 dark:bg-success-900/20 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-success-600">{{ $stats['rooms'] }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Rooms</div>
        </div>
        <div class="bg-info-50 dark:bg-info-900/20 rounded-lg p-4 text-center">
            <div class="text-3xl font-bold text-info-600">{{ $stats['beds'] }}</div>
            <div class="text-sm text-gray-600 dark:text-gray-400">Beds</div>
        </div>
    </div>

    {{-- Add Floor Button --}}
    <div class="flex justify-end">
        <button type="button" wire:click="addFloor" 
            class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Add Floor
        </button>
    </div>

    {{-- Floors Configuration --}}
    <div class="space-y-4">
        @forelse($floors as $floorIndex => $floor)
            <div class="border rounded-lg p-4 bg-white dark:bg-gray-800 shadow-sm" wire:key="floor-{{ $floorIndex }}">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex items-center gap-4">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Floor {{ $floor['floor_number'] }}
                        </h3>
                        <input type="text" wire:model.live="floors.{{ $floorIndex }}.name" 
                            class="text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600"
                            placeholder="Floor name (optional)">
                    </div>
                    <button type="button" wire:click="removeFloor({{ $floorIndex }})"
                        class="text-danger-600 hover:text-danger-800">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>

                {{-- Room Configurations for this floor --}}
                <div class="space-y-3">
                    @foreach($floor['room_configs'] as $configIndex => $config)
                        <div class="flex items-center gap-4 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg" wire:key="config-{{ $floorIndex }}-{{ $configIndex }}">
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Room Capacity</label>
                                <select wire:model.live="floors.{{ $floorIndex }}.room_configs.{{ $configIndex }}.capacity"
                                    class="w-full border-gray-300 rounded-md text-sm dark:bg-gray-600 dark:border-gray-500">
                                    <option value="1">Single (1 bed)</option>
                                    <option value="2">Double (2 beds)</option>
                                    <option value="3">Triple (3 beds)</option>
                                    <option value="4">Quad (4 beds)</option>
                                    <option value="5">5 beds</option>
                                    <option value="6">6 beds</option>
                                </select>
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Number of Rooms</label>
                                <input type="number" min="1" max="100"
                                    wire:model.live="floors.{{ $floorIndex }}.room_configs.{{ $configIndex }}.room_count"
                                    class="w-full border-gray-300 rounded-md text-sm dark:bg-gray-600 dark:border-gray-500">
                            </div>
                            <div class="flex-1">
                                <label class="block text-xs text-gray-500 mb-1">Numbering</label>
                                <select wire:model.live="floors.{{ $floorIndex }}.room_configs.{{ $configIndex }}.numbering_mode"
                                    class="w-full border-gray-300 rounded-md text-sm dark:bg-gray-600 dark:border-gray-500">
                                    <option value="auto">Auto-generate</option>
                                    <option value="manual">Custom prefix</option>
                                </select>
                            </div>
                            @if($config['numbering_mode'] === 'manual')
                                <div class="flex-1">
                                    <label class="block text-xs text-gray-500 mb-1">Room Prefix</label>
                                    <input type="text" maxlength="10"
                                        wire:model.live="floors.{{ $floorIndex }}.room_configs.{{ $configIndex }}.room_prefix"
                                        placeholder="e.g., A-"
                                        class="w-full border-gray-300 rounded-md text-sm dark:bg-gray-600 dark:border-gray-500">
                                </div>
                            @endif
                            <div class="flex-shrink-0 pt-5">
                                @if(count($floor['room_configs']) > 1)
                                    <button type="button" wire:click="removeRoomConfig({{ $floorIndex }}, {{ $configIndex }})"
                                        class="text-danger-500 hover:text-danger-700">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    
                    <button type="button" wire:click="addRoomConfig({{ $floorIndex }})"
                        class="text-sm text-primary-600 hover:text-primary-800 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add room type to this floor
                    </button>
                </div>
            </div>
        @empty
            <div class="text-center py-12 bg-gray-50 dark:bg-gray-800 rounded-lg">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No floors configured</h3>
                <p class="mt-1 text-sm text-gray-500">Get started by adding your first floor.</p>
                <div class="mt-6">
                    <button type="button" wire:click="addFloor"
                        class="inline-flex items-center px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add First Floor
                    </button>
                </div>
            </div>
        @endforelse
    </div>

    {{-- Preview Section --}}
    @if(count($floors) > 0)
        <div class="mt-8 border-t pt-6">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Room Preview</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($preview as $floorPreview)
                    <div class="border rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                        <h4 class="font-medium text-gray-900 dark:text-white mb-2">
                            {{ $floorPreview['name'] }}
                            <span class="text-sm text-gray-500">({{ $floorPreview['total_rooms'] }} rooms, {{ $floorPreview['total_beds'] }} beds)</span>
                        </h4>
                        <div class="flex flex-wrap gap-1 max-h-32 overflow-y-auto">
                            @foreach(array_slice($floorPreview['rooms'], 0, 20) as $room)
                                <span class="inline-flex items-center px-2 py-1 text-xs rounded bg-white dark:bg-gray-600 border border-gray-200 dark:border-gray-500">
                                    {{ $room['room_no'] }} 
                                    <span class="ml-1 text-gray-400">({{ $room['capacity'] }})</span>
                                </span>
                            @endforeach
                            @if(count($floorPreview['rooms']) > 20)
                                <span class="inline-flex items-center px-2 py-1 text-xs text-gray-500">
                                    +{{ count($floorPreview['rooms']) - 20 }} more
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

