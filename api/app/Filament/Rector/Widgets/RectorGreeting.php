<?php

namespace App\Filament\Rector\Widgets;

use Filament\Widgets\Widget;

class RectorGreeting extends Widget
{
    protected static string $view = 'filament.rector.widgets.rector-greeting';

    protected int | string | array $columnSpan = 'full';

    public function getGreeting(): string
    {
        $hour = now()->hour;
        
        if ($hour < 12) {
            return 'Good Morning';
        } elseif ($hour < 17) {
            return 'Good Afternoon';
        } else {
            return 'Good Evening';
        }
    }

    public function getRectorName(): string
    {
        return auth()->user()?->name ?? 'Rector';
    }
}

