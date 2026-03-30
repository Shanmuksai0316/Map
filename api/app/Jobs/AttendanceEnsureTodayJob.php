<?php

namespace App\Jobs;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceEnsureTodayJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $todayIst = Carbon::now('Asia/Kolkata')->startOfDay();
        $nowIst = Carbon::now('Asia/Kolkata');

        Tenant::query()->each(function (Tenant $tenant) use ($todayIst, $nowIst): void {
            // Set tenant session variable for RLS policies
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
            
            try {
                Hostel::query()
                    ->where('tenant_id', $tenant->id)
                    ->each(function (Hostel $hostel) use ($tenant, $todayIst, $nowIst): void {
                        $curfewTime = Carbon::createFromFormat('H:i:s', $hostel->curfew_time, 'Asia/Kolkata');
                        
                        $openAt = $todayIst->copy()->setTime($curfewTime->hour, $curfewTime->minute)->subHour();
                        $closeAt = $todayIst->copy()->setTime($curfewTime->hour, $curfewTime->minute)->addHours(2);

                        // If curfew is late (e.g., 22:30), close_at might be next day
                        if ($closeAt->lessThan($openAt)) {
                            $closeAt->addDay();
                        }

                        $status = $nowIst->between($openAt, $closeAt) ? 'Open' : 'Scheduled';

                        DB::transaction(function () use ($tenant, $hostel, $todayIst, $openAt, $closeAt, $status): void {
                            AttendanceSession::query()->updateOrCreate(
                                [
                                    'tenant_id' => $tenant->id,
                                    'hostel_id' => $hostel->id,
                                    'kind' => 'night_check',
                                ],
                                [
                                    'campus_id' => $hostel->campus_id,
                                    'name' => "Night Check - {$hostel->name}",
                                    'scheduled_at' => $todayIst,
                                    'status' => $status,
                                    'metadata' => [
                                        'open_at' => $openAt->toISOString(),
                                        'close_at' => $closeAt->toISOString(),
                                        'session_date' => $todayIst->toDateString(),
                                    ],
                                ]
                            );
                        });
                    });
            } finally {
                // Clear tenant session variable after processing
                \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
            }
        });
    }
}
