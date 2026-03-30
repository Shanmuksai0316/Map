<?php

namespace App\Filament\Widgets\SuperAdmin\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;

class StudentsByTenantWidget extends ChartWidget
{
    protected static ?string $heading = 'Students by Tenant';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected static ?string $maxHeight = '280px';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $data = app(DashboardDataService::class)->studentsByTenant();

        return [
            'datasets' => [
                [
                    'label' => 'Students',
                    'data' => $data['data'] ?? [],
                    'backgroundColor' => '#3B82F6',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
            ],
            'scales' => ['x' => ['beginAtZero' => true]],
        ];
    }
}
