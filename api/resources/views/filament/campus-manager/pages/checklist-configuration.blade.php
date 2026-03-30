<x-filament-panels::page>
    <div class="space-y-6">
        {{ $this->form }}

        <div class="flex flex-wrap justify-end gap-2">
            <x-filament::button wire:click="seedAllRoleTemplates" color="gray">
                Apply Defaults to All Roles
            </x-filament::button>
            <x-filament::button wire:click="save" color="primary">
                Save Checklist
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>

