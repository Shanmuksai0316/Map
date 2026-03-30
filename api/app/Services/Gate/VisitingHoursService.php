<?php

namespace App\Services\Gate;

use App\Models\Hostel;
use Carbon\Carbon;

class VisitingHoursService
{
    /**
     * Get visiting window for today for a given hostel
     * 
     * Returns start and end Carbon instances in Asia/Kolkata timezone
     * Uses hostel's visiting_start_time and visiting_end_time if available,
     * otherwise defaults to 16:00-19:00 IST
     *
     * @return array{start: Carbon, end: Carbon}
     */
    public function getWindowForToday(Hostel $hostel): array
    {
        $today = Carbon::today('Asia/Kolkata');

        // Check if hostel has custom visiting hours
        $startTime = $hostel->visiting_start_time ?? '16:00:00';
        $endTime = $hostel->visiting_end_time ?? '19:00:00';

        $start = Carbon::parse($today->format('Y-m-d') . ' ' . $startTime, 'Asia/Kolkata');
        $end = Carbon::parse($today->format('Y-m-d') . ' ' . $endTime, 'Asia/Kolkata');

        return [
            'start' => $start,
            'end' => $end,
        ];
    }
}

