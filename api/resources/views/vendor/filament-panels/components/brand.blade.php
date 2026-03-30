@php
    $panel = filament()->getCurrentPanel();
@endphp

<div {{ $attributes->class('flex items-center gap-3') }}>
    <img
        src="{{ asset('images/map-logo.svg') }}"
        alt="MAP HMS logo"
        class="h-8 w-auto"
    />

    @if ($panel && $panel->getId() === 'campus-manager')
        <span class="text-sm font-semibold tracking-tight text-white">
            Campus Manager Dashboard
        </span>
    @elseif ($panel && $panel->getId() === 'rector')
        <span class="text-sm font-semibold tracking-tight text-white">
            Rector Dashboard
        </span>
    @endif
</div>





