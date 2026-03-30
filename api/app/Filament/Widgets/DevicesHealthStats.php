<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DevicesHealthStats extends BaseWidget
{
    protected static ?int $sort = 7;

    protected function getStats(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->devicesHealth($tenantId, $hostelIds);

        return [
            Stat::make('Registered Devices', $data['total'])
                ->description('Total enrolled')
                ->icon('heroicon-o-device-tablet')
                ->color('primary'),
            
            Stat::make('Active', $data['active'])
                ->description('Seen in last 10 min')
                ->icon('heroicon-o-signal')
                ->color('success'),
            
            Stat::make('Stale', $data['stale'])
                ->description('Not seen recently')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger'),
        ];
    }
}

