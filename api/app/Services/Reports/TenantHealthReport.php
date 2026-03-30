<?php

namespace App\Services\Reports;

use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TenantHealthReport
{
    public static function generate(array $params): \Generator
    {
        $fromDate = Carbon::parse($params['from_date'] ?? now()->subDays(30))->startOfDay();
        $toDate = Carbon::parse($params['to_date'] ?? now())->endOfDay();

        $tenants = Tenant::query()
            ->when(!empty($params['tenant_id']), fn ($q) => $q->where('id', $params['tenant_id']))
            ->get();

        foreach ($tenants as $tenant) {
            $hostels = DB::table('hostels')->where('tenant_id', $tenant->id)->count();
            $students = DB::table('students')->where('tenant_id', $tenant->id)->count();
            $staff = DB::table('users')
                ->where('tenant_id', $tenant->id)
                ->where('kind', '!=', 'student')
                ->count();

            $beds = Schema::hasTable('room_beds')
                ? DB::table('room_beds')->where('tenant_id', $tenant->id)->count()
                : 0;

            $occupiedBeds = Schema::hasTable('room_allocations')
                ? DB::table('room_allocations')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->count()
                : 0;

            $utilization = $beds > 0 ? round(($occupiedBeds / $beds) * 100, 1) : 0;

            $requestsOpen = Schema::hasTable('tickets')
                ? DB::table('tickets')
                    ->where('tenant_id', $tenant->id)
                    ->whereIn('status', ['open', 'in_progress'])
                    ->count()
                : 0;

            yield [
                'Tenant Code' => $tenant->code,
                'Tenant Name' => $tenant->name,
                'Hostels' => $hostels,
                'Students' => $students,
                'Staff' => $staff,
                'Bed Utilization %' => $utilization,
                'Open Requests' => $requestsOpen,
                'Period' => $fromDate->format('Y-m-d') . ' to ' . $toDate->format('Y-m-d'),
            ];
        }
    }
}

