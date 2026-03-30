<?php

namespace App\Filament\Widgets\SuperAdmin\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;

class GlobalOccupancyWidget extends ChartWidget
{
    protected static ?string $heading = 'Global Occupancy (All Tenants)';

    protected static ?string $pollingInterval = '60s';

    // Match layout feel of Request Status Breakdown
    protected int | string | array $columnSpan = 1;

    protected static ?string $maxHeight = null;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = app(DashboardDataService::class)->occupancyByHostel();

        return [
            'datasets' => [
                [
                    'label' => 'Occupied',
                    'data' => $data['occupied'] ?? [],
                    'backgroundColor' => '#2F4F2F',
                ],
                [
                    'label' => 'Available',
                    'data' => $data['available'] ?? [],
                    'backgroundColor' => '#F6C32E',
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'x' => [
                    'stacked' => true,
                    'ticks' => ['color' => '#64748B'],
                    'grid' => ['color' => '#E5E7EB'],
                ],
                'y' => [
                    'stacked' => true,
                    'beginAtZero' => true,
                    'ticks' => ['color' => '#64748B'],
                    'grid' => ['color' => '#E5E7EB'],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'color' => '#556987',
                    ],
                ],
            ],
        ];
    }
}
