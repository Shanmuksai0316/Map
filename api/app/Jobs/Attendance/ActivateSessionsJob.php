<?php

namespace App\Jobs\Attendance;

use App\Domain\Attendance\Models\AttendanceSessionV2;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class ActivateSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $now = now('Asia/Kolkata');
        
        // Process each tenant separately to ensure RLS works correctly
        \App\Models\Tenant::query()->each(function (\App\Models\Tenant $tenant) use ($now) {
            // Set tenant session variable for RLS policies
            \App\Http\Middleware\SetPostgresSessionTenant::setTenantSessionVariable($tenant->id);
            
            try {
                // Find sessions that should be activated (scheduled status and window has started)
                $sessionsToActivate = AttendanceSessionV2::where('tenant_id', $tenant->id)
                    ->where('status', 'scheduled')
                    ->where('session_date', $now->toDateString())
                    ->get();
                    
                foreach ($sessionsToActivate as $session) {
                    $windowStart = Carbon::parse($session->metadata['window']['start'] ?? $session->scheduled_at);
                    
                    // If current time is past window start, activate the session
                    if ($now->gte($windowStart)) {
                        $session->update(['status' => 'active']);
                    }
                }
            } finally {
                // Clear tenant session variable after processing
                \App\Http\Middleware\SetPostgresSessionTenant::clearTenantSessionVariable();
            }
        });
    }
}
