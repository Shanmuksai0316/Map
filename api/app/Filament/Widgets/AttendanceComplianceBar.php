<?php

namespace App\Filament\Widgets;

use App\Support\Dashboard\KpisRepository;
use Filament\Widgets\ChartWidget;

class AttendanceComplianceBar extends ChartWidget
{
    protected static ?string $heading = 'Attendance Compliance (Last 7 Days)';

    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $user = auth()->user();
        $tenantId = $user->tenant_id;
        
        $hostelIds = \App\Models\Hostel::where('tenant_id', $tenantId)->pluck('id')->toArray();

        $repo = app(KpisRepository::class);
        $data = $repo->attendanceCompliance($tenantId, 7, $hostelIds);

        return [
            'datasets' => [
                [
                    'label' => 'Sessions Closed (%)',
                    'data' => array_values($data),
                    'backgroundColor' => '#1E56D9',
                ],
            ],
            'labels' => array_map(fn($d) => date('M d', strtotime($d)), array_keys($data)),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}

