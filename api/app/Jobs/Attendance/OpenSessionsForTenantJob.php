<?php

namespace App\Jobs\Attendance;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class OpenSessionsForTenantJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly int $tenantId,
        private readonly ?string $date = null
    ) {}

    public function handle(): void
    {
        // Set tenant session variable for RLS policies (required for background jobs)
        \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable((string)$this->tenantId);
        
        try {
            $sessionDate = $this->date ? Carbon::parse($this->date)->toDateString() : now('Asia/Kolkata')->toDateString();
            
            // Get hostels for this specific tenant only
            $hostels = Hostel::where('tenant_id', $this->tenantId)->get();
            
            foreach ($hostels as $hostel) {
                // Create session if it doesn't exist
                $session = AttendanceSessionV2::firstOrCreate(
                    [
                        'hostel_id' => $hostel->id,
                        'session_date' => $sessionDate,
                    ],
                    [
                        'tenant_id' => $hostel->tenant_id,
                        'status' => 'scheduled',
                        'scheduled_at' => Carbon::parse($sessionDate)->setTime(19, 0), // 7 PM default
                        'metadata' => [
                            'window' => [
                                'start' => Carbon::parse($sessionDate)->setTime(19, 0)->toISOString(),
                                'end' => Carbon::parse($sessionDate)->setTime(21, 0)->toISOString(),
                            ]
                        ]
                    ]
                );
                
                // Update metadata if session already existed
                if (!$session->wasRecentlyCreated) {
                    $session->update([
                        'metadata' => array_merge($session->metadata ?? [], [
                            'window' => [
                                'start' => Carbon::parse($sessionDate)->setTime(19, 0)->toISOString(),
                                'end' => Carbon::parse($sessionDate)->setTime(21, 0)->toISOString(),
                            ]
                        ])
                    ]);
                }
            }
        } finally {
            // Clear tenant session variable after job completion
            \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
        }
    }
}



