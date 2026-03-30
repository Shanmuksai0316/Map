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

class OpenSessionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        private readonly ?string $date = null
    ) {}

    public function handle(): void
    {
        $sessionDate = $this->date ? Carbon::parse($this->date)->toDateString() : now('Asia/Kolkata')->toDateString();
        
        // Dispatch sub-jobs for each tenant to ensure proper tenant isolation
        Tenant::with('hostels')->cursor()->each(function (Tenant $tenant) use ($sessionDate) {
            OpenSessionsForTenantJob::dispatch($tenant->id, $sessionDate);
        });
    }
}
