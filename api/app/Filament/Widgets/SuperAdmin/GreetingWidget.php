<?php

namespace App\Filament\Widgets\SuperAdmin;

use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;

class GreetingWidget extends Widget
{
    protected static string $view = 'filament.widgets.greeting-widget';

    protected int | string | array $columnSpan = 'full';

    protected static ?int $sort = -1;

    public static function canView(): bool
    {
        return auth()->check() && auth()->user()->hasRole('Super Admin');
    }

    public function getGreeting(): string
    {
        $hour = Carbon::now()->hour;

        if ($hour < 12) {
            return 'Good Morning';
        } elseif ($hour < 17) {
            return 'Good Afternoon';
        } else {
            return 'Good Evening';
        }
    }

    public function getUserName(): string
    {
        return auth()->user()->name ?? 'Admin';
    }

    public function getFormattedDate(): string
    {
        return Carbon::now()->format('l, F j, Y');
    }
}

