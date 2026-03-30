<x-filament-panels::page.simple>
    @component('filament.auth.shared-login-layout', [
        'title' => $title,
        'subtitle' => $subtitle,
    ])
        {{ $slot }}
    @endcomponent
</x-filament-panels::page.simple>
