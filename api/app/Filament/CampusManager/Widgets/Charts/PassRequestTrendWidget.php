<?php

namespace App\Filament\CampusManager\Widgets\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class PassRequestTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Pass Request Trend';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 2;

    protected static ?string $maxHeight = '180px';

    protected static ?int $sort = 6;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $service = app(DashboardDataService::class);
        $tenantId = Auth::user()?->tenant_id;
        $hostelId = session('active_hostel_id');

        $days = (int) session('dashboard_range_days', 7);

        $data = $service->passRequestTrend($tenantId, $hostelId, now()->subDays($days), now());

        return [
            'datasets' => [
                [
                    'label' => 'Approved',
                    'data' => $data['approved'] ?? [],
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Declined',
                    'data' => $data['declined'] ?? [],
                    'borderColor' => '#DC2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
                [
                    'label' => 'Pending',
                    'data' => $data['pending'] ?? [],
                    'borderColor' => '#F59E0B',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'fill' => false,
                    'tension' => 0.3,
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
