<?php

namespace App\Filament\CampusManager\Widgets\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class RequestStatusWidget extends ChartWidget
{
    protected static ?string $heading = 'Request Status Breakdown';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $service = app(DashboardDataService::class);
        $tenantId = Auth::user()?->tenant_id;
        $hostelId = session('active_hostel_id');

        $days = (int) session('dashboard_range_days', 7);
        $from = now()->subDays($days);
        $to = now();

        $data = $service->requestsByStatus($tenantId, $hostelId, $from, $to);
        $datasets = $data['datasets'] ?? [];

        return [
            'datasets' => [
                [
                    'label' => 'Housekeeping',
                    'data' => $datasets['housekeeping'] ?? [],
                    'backgroundColor' => '#2F4F2F',
                    'borderColor' => '#264226',
                    'borderWidth' => 1,
                ],
                [
                    'label' => 'Maintenance',
                    'data' => $datasets['maintenance'] ?? [],
                    'backgroundColor' => '#F0B90B',
                    'borderColor' => '#D99E00',
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'labels' => [
                        'color' => '#556987',
                    ],
                ],
            ],
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
        ];
    }
}
