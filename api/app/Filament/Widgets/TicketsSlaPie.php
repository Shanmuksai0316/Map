<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;

class TicketsSlaPie extends ChartWidget
{
    protected static ?string $heading = 'Open Tickets SLA Status';

    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->ticketSlaSplit($tenantId, $hostelIds);

        return [
            'datasets' => [
                [
                    'data' => [$data['on_time'], $data['breached']],
                    'backgroundColor' => ['#10B981', '#EF4444'],
                ],
            ],
            'labels' => ['Within SLA', 'Breached'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}

