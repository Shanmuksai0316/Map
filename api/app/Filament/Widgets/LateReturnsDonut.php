<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;

class LateReturnsDonut extends ChartWidget
{
    protected static ?string $heading = 'Late Returns (Last 7 Days)';

    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->lateReturnSplit($tenantId, 7, $hostelIds);

        return [
            'datasets' => [
                [
                    'data' => [$data['on_time'], $data['late']],
                    'backgroundColor' => ['#10B981', '#EF4444'],
                ],
            ],
            'labels' => ['On Time', 'Late'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

