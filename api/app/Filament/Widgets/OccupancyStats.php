<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OccupancyStats extends BaseWidget
{
    protected function getStats(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        // Get visible hostel IDs (simplified - use all hostels for now)
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->occupancy($tenantId, $hostelIds);

        return [
            Stat::make('Total Beds', $data['total'])
                ->description('Across all hostels')
                ->icon('heroicon-o-home')
                ->color('primary'),
            
            Stat::make('Occupied', $data['occupied'])
                ->description('Active allocations')
                ->icon('heroicon-o-user-group')
                ->color('success'),
            
            Stat::make('Available', $data['available'])
                ->description('Ready for allocation')
                ->icon('heroicon-o-check-circle')
                ->color('warning'),
            
            Stat::make('Utilization', $data['utilization'] . '%')
                ->description('Occupancy rate')
                ->icon('heroicon-o-chart-bar')
                ->color($data['utilization'] > 90 ? 'danger' : 'success'),
        ];
    }
}

