<?php

namespace App\Filament\Pages\Admin;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';
    
    protected static ?string $navigationGroup = null;

    protected static ?int $navigationSort = -10;
    
    protected static ?string $title = 'Dashboard';
    
    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\Widgets\SuperAdmin\GreetingWidget::class,
        ];
    }
    
    public function getColumns(): int | string | array
    {
        return 2;
    }
    
    public function getWidgets(): array
    {
        return [
            \App\Filament\Widgets\SuperAdmin\SuperAdminStatsWidget::class,
            \App\Filament\Widgets\SuperAdmin\Charts\StudentsByTenantWidget::class,
            \App\Filament\Widgets\SuperAdmin\TicketsByPriorityChart::class,
            \App\Filament\Widgets\SuperAdmin\Charts\GlobalOccupancyWidget::class,
            \App\Filament\Widgets\SuperAdmin\RequestsOverviewWidget::class,
            \App\Filament\Widgets\SuperAdmin\AttendanceClosureChart::class,
        ];
    }
    
    protected function getFooterWidgets(): array
    {
        return [];
    }
}
