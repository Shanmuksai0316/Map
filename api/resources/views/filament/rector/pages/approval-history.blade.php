<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">
                Approval History
            </x-slot>

            <x-slot name="description">
                View all approval decisions across Out-Pass, Leave, and Sick Leave requests
            </x-slot>

            <x-filament-tables::table class="w-full">
                {{ $this->table }}
            </x-filament-tables::table>
        </x-filament::section>
    </div>
</x-filament-panels::page>
