<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\DateRange;
use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;

class ChecklistCompletionBar extends ChartWidget
{
    protected static ?string $heading = 'Checklist Completion (Yesterday)';

    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->checklistCompletionByRole($tenantId, DateRange::yesterday(), $hostelIds);

        return [
            'datasets' => [
                [
                    'label' => 'Completion Rate (%)',
                    'data' => array_values($data),
                    'backgroundColor' => ['#10B981', '#F59E0B', '#8B5CF6'],
                ],
            ],
            'labels' => array_keys($data),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

