<?php

namespace App\Filament\CampusManager\Widgets\Charts;

use App\Services\Dashboard\DashboardDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class OccupancyChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Hostel Occupancy';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 1;

    protected static ?string $maxHeight = '220px';

    protected static ?int $sort = 2;

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $service = app(DashboardDataService::class);
        $tenantId = Auth::user()?->tenant_id;
        $hostelId = session('active_hostel_id');

        $data = $service->occupancyByHostel($tenantId, $hostelId);

        return [
            'datasets' => [
                [
                    'label' => 'Occupied',
                    'data' => $data['occupied'] ?? [],
                    'backgroundColor' => ['#2F4F2F', '#0F766E', '#1D4ED8', '#B45309', '#6D28D9', '#BE185D'],
                ],
                [
                    'label' => 'Available',
                    'data' => $data['available'] ?? [],
                    'backgroundColor' => ['#DDEBDD', '#CCFBF1', '#DBEAFE', '#FEF3C7', '#EDE9FE', '#FCE7F3'],
                ],
            ],
            'labels' => $data['labels'] ?? [],
        ];
    }
}
