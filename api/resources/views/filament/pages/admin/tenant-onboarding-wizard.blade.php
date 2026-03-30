<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex items-center justify-end gap-2">
            <x-filament::button color="secondary" wire:click="saveDraft">
                Save Draft
            </x-filament::button>
            <x-filament::button color="gray" wire:click="saveAndExit">
                Save &amp; Exit
            </x-filament::button>
        </div>

        <div class="space-y-6">
            @if($this->wizardForm)
                {{ $this->wizardForm }}

                {{-- Activation footer --}}
                <div class="flex items-center justify-between mt-4">
                    <div></div>
                    <div class="flex gap-2">
                        <x-filament::button color="primary"
                                            wire:click="activate"
                                            wire:loading.attr="disabled"
                                            wire:target="activate">
                            Activate Tenant
                        </x-filament::button>
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
