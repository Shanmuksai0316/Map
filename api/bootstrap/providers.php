<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Providers\AuthServiceProvider::class,
    App\Providers\EventServiceProvider::class,
    // Main Filament service provider - required for Filament to work
    Filament\FilamentServiceProvider::class,
    App\Providers\Filament\AdminPanelProvider::class,
    App\Providers\Filament\CampusManagerPanelProvider::class,
    App\Providers\Filament\CollegeMgmtPanelProvider::class,
    App\Providers\Filament\RectorPanelProvider::class,
    App\Providers\HorizonServiceProvider::class,
    App\Providers\ReportsServiceProvider::class,
    Stancl\Tenancy\TenancyServiceProvider::class,
    App\Providers\TenancyServiceProvider::class,
    // Debug provider - only loaded when DEBUG_403=true
    ...(env('DEBUG_403') ? [App\Providers\Admin403DebugServiceProvider::class] : []),
];
