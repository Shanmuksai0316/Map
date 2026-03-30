<?php

namespace App\Filament\CampusManager\Widgets;

use Filament\Widgets\Widget;

/**
 * Global time range filter for the dashboard.
 * Sets session('dashboard_range_days') which all chart widgets read.
 * Similar to Google Analytics time range selector.
 */
class DashboardTimeFilter extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.dashboard-time-filter';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public int $rangeDays = 7;

    public function mount(): void
    {
        $this->rangeDays = (int) session('dashboard_range_days', 7);
    }

    public function setRange(int $days): void
    {
        $this->rangeDays = $days;
        session(['dashboard_range_days' => $days]);

        // Force full page refresh to update all widgets
        $this->redirect(request()->header('Referer', '/campus-manager'));
    }
}
