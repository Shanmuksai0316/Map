<?php

namespace App\Services\Reports;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OccupancyTrendReport
{
    public static function generate(array $params): \Generator
    {
        $fromDate = Carbon::parse($params['from_date'] ?? now()->subDays(14))->startOfDay();
        $toDate = Carbon::parse($params['to_date'] ?? now())->endOfDay();
        $tenantId = $params['tenant_id'] ?? null;

        $dates = [];
        $cursor = $fromDate->copy();
        while ($cursor->lte($toDate)) {
            $dates[] = $cursor->copy();
            $cursor->addDay();
        }

        foreach ($dates as $date) {
            $beds = 0;
            $occupied = 0;

            if (Schema::hasTable('room_beds')) {
                $bedsQuery = DB::table('room_beds');
                if ($tenantId) {
                    $bedsQuery->where('tenant_id', $tenantId);
                }
                $beds = $bedsQuery->count();
            }

            if (Schema::hasTable('room_allocations')) {
                $allocQuery = DB::table('room_allocations')
                    ->where('is_active', true)
                    ->whereDate('created_at', '<=', $date)
                    ->where(function ($q) use ($date) {
                        $q->whereNull('ended_at')->orWhereDate('ended_at', '>=', $date);
                    });
                if ($tenantId) {
                    $allocQuery->where('tenant_id', $tenantId);
                }
                $occupied = $allocQuery->count();
            }

            $util = $beds > 0 ? round(($occupied / $beds) * 100, 1) : 0;

            yield [
                'Date' => $date->format('Y-m-d'),
                'Beds' => $beds,
                'Occupied' => $occupied,
                'Utilization %' => $util,
            ];
        }
    }
}

