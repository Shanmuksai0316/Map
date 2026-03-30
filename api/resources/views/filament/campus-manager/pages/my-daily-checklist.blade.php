<x-filament-panels::page>
    <div class="space-y-6">
        @if($this->template && $this->todayChecklist)
            <x-filament::section>
                <x-slot name="heading">
                    {{ $this->getHeading() }}
                </x-slot>
                <x-slot name="description">
                    {{ $this->getSubheading() }}
                </x-slot>

                @php
                    $items = $this->getChecklistItems();
                @endphp

                @if(count($items) === 0)
                    <div class="text-center py-12">
                        <x-heroicon-o-clipboard-document-list class="mx-auto h-12 w-12 text-gray-400" />
                        <h3 class="mt-2 text-sm font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">No checklist items</h3>
                        <p class="mt-1 text-sm text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                            Please add items in the Checklist Configuration page.
                        </p>
                    </div>
                @else
                    <div class="space-y-4">
                        @foreach($items as $index => $item)
                        @php
                            $itemKey = $item['code'] ?? "item-{$index}";
                            $isCompleted = in_array($itemKey, $this->completedItems);
                        @endphp
                        <div 
                            wire:click="toggleItem('{{ $itemKey }}')"
                            class="flex items-center gap-4 p-4 rounded-lg border cursor-pointer transition-all duration-200 {{ $isCompleted ? 'bg-success-50 border-success-300 dark:bg-success-900/20 dark:border-success-700' : 'bg-white border-gray-200 hover:bg-gray-50 dark:bg-gray-900 dark:border-gray-700 dark:hover:bg-gray-800' }}"
                        >
                            <div class="flex-shrink-0">
                                <div class="w-6 h-6 rounded border-2 flex items-center justify-center {{ $isCompleted ? 'bg-success-500 border-success-500' : 'border-gray-300 dark:border-gray-600' }}">
                                    @if($isCompleted)
                                        <x-heroicon-s-check class="w-4 h-4 text-white" />
                                    @endif
                                </div>
                            </div>
                            <div class="flex-grow">
                                <p class="{{ $isCompleted ? 'line-through text-[#2F4F2F]/80' : 'text-[#2F4F2F] dark:text-[#2F4F2F]' }}">
                                    {{ $item['label'] ?? 'Checklist Item' }}
                                </p>
                            </div>
                        </div>
                        @endforeach
                    </div>

                    <div class="mt-6 flex justify-end">
                        <x-filament::button
                            wire:click="submit"
                            color="{{ $this->isAllCompleted() ? 'success' : 'gray' }}"
                            :disabled="!$this->isAllCompleted()"
                        >
                            Submit Checklist
                        </x-filament::button>
                    </div>
                @endif
            </x-filament::section>
        @elseif(!$this->template)
            <x-filament::section>
                <div class="text-center py-12">
                    <x-heroicon-o-clipboard-document-list class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">No Checklist Template</h3>
                    <p class="mt-1 text-sm text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        There is no active checklist template configured for Campus Managers.
                    </p>
                    <p class="mt-1 text-sm text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Please create one in the Checklist Configuration section.
                    </p>
                </div>
            </x-filament::section>
        @else
            <x-filament::section>
                <div class="text-center py-12">
                    <x-heroicon-o-clock class="mx-auto h-12 w-12 text-gray-400" />
                    <h3 class="mt-2 text-sm font-semibold text-[#2F4F2F] dark:text-[#2F4F2F]">No Checklist for Today</h3>
                    <p class="mt-1 text-sm text-[#2F4F2F]/80 dark:text-[#2F4F2F]/80">
                        Your daily checklist has not been generated yet. Please check back later.
                    </p>
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>

