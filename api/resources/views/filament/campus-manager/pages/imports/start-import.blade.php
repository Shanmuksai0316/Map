<x-filament-panels::page>
    <div class="max-w-3xl mx-auto space-y-4">
        {{ $this->form }}

        <div class="flex items-center gap-3">
            <x-filament::button wire:click="submit" wire:loading.attr="disabled" wire:target="data.file,submit">
                Run Import
            </x-filament::button>

            <x-filament::button type="button" color="gray" wire:click="downloadTemplate" icon="heroicon-o-arrow-down-tray">
                Download XLSX Template
            </x-filament::button>

            @if(($this->data['kind'] ?? 'students') === 'students')
                <x-filament::button type="button" color="info" wire:click="downloadGoogleFormTemplate" icon="heroicon-o-document-arrow-down">
                    Download Google Form CSV
                </x-filament::button>
            @endif
        </div>

        @if(($this->data['kind'] ?? 'students') === 'students')
            <div class="text-sm text-[#2F4F2F]/80 dark:text-gray-300">
                Tip: For Google Forms, keep question labels like "Full Name", "Email Address", "Mobile Number", and "Gender".
                Upload the exported responses file directly (extra columns like "Timestamp" are ignored). On clicking "Run Import",
                the system will validate the file and then import all valid rows in one step.
            </div>
        @endif
    </div>
</x-filament-panels::page>
