<?php

namespace App\Filament\CampusManager\Widgets\CampusManager;

use App\Models\RoomAllocation;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class CheckoutSummaryWidget extends Widget
{
    protected static string $view = 'filament.campus-manager.widgets.checkout-summary';

    protected int|string|array $columnSpan = [
        'md' => 1,
    ];

    protected function getViewData(): array
    {
        $upcoming = RoomAllocation::query()
            ->where('is_active', true)
            ->where('checkout_status', 'pending')
            ->whereBetween('expected_checkout_at', [now(), now()->addDays(7)])
            ->count();

        $overdue = RoomAllocation::query()
            ->where('is_active', true)
            ->where('checkout_status', 'pending')
            ->where('expected_checkout_at', '<', now())
            ->count();

        $inProgress = RoomAllocation::query()
            ->where('checkout_status', 'in_progress')
            ->count();

        $completedToday = RoomAllocation::query()
            ->where('checkout_status', 'completed')
            ->whereDate('updated_at', Carbon::today())
            ->count();

        return compact('upcoming', 'overdue', 'inProgress', 'completedToday');
    }
}

