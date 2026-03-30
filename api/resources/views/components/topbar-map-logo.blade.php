@php
    $panelId = filament()->getCurrentPanel()?->getId();
@endphp

@if ($panelId !== 'college-mgmt')
    <div class="flex items-center justify-end">
        <img src="{{ asset('images/map-logo.svg') }}" alt="MAP" class="h-8 w-auto" />
    </div>
@endif
