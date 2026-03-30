<?php

namespace App\Filament\Rector\Widgets\Charts;

use App\Models\Domain\OutPass\OutPass;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class ApprovalTrendWidget extends ChartWidget
{
    protected static ?string $heading = 'Approval Trend (7 Days)';

    protected static ?string $pollingInterval = '60s';

    protected int | string | array $columnSpan = 2;

    protected static ?int $sort = 4;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $tenantId = Auth::user()?->tenant_id;
        $days = 7;
        $dates = [];
        $approved = [];
        $declined = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dates[] = $date->format('d M');

            $baseQ = OutPass::where('tenant_id', $tenantId)
                ->whereDate('created_at', $date);

            $approved[] = (clone $baseQ)->where('status', 'approved')->count();
            $declined[] = (clone $baseQ)->where('status', 'declined')->count();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Approved',
                    'data' => $approved,
                    'borderColor' => '#059669',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => 'Declined',
                    'data' => $declined,
                    'borderColor' => '#DC2626',
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
            'labels' => $dates,
        ];
    }
}
