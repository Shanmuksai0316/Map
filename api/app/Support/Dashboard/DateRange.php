<?php

namespace App\Support\Dashboard;

use Carbon\Carbon;
use Carbon\CarbonPeriod;

class DateRange
{
    public static function lastNDays(int $days): array
    {
        $end = Carbon::today();
        $start = Carbon::today()->subDays($days - 1);

        $period = CarbonPeriod::create($start, $end);
        
        return array_map(fn($date) => $date->toDateString(), iterator_to_array($period));
    }

    public static function yesterday(): Carbon
    {
        return Carbon::yesterday();
    }

    public static function today(): Carbon
    {
        return Carbon::today();
    }
}

