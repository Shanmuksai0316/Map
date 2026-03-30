<?php

namespace App\Services\Reports;

use App\Domain\Gate\Models\GateDevice;
use App\Models\Hostel;
use App\Support\HostelScope;
use Carbon\Carbon;

class DeviceHealthReport
{
    public static function generate(array $params): \Generator
    {
        $tenantId = auth()->user()->tenant_id;
        $fromDate = Carbon::parse($params['from_date'])->startOfDay();
        $toDate = Carbon::parse($params['to_date'])->endOfDay();
        $hostelId = $params['hostel_id'] ?? null;
        
        $devices = GateDevice::where('tenant_id', $tenantId)
            ->when($hostelId, fn($q) => $q->where('hostel_id', $hostelId))
            ->with('hostel')
            ->get();
            
        foreach ($devices as $device) {
            $isActive = $device->last_seen_at && $device->last_seen_at->gt(now()->subMinutes(10));
            $status = $isActive ? 'Active' : 'Stale';
            $lastSeen = $device->last_seen_at ? $device->last_seen_at->format('Y-m-d H:i') : 'Never';
            $uptime = $device->last_seen_at ? $device->last_seen_at->diffInHours(now()) : 'N/A';
            
            yield [
                'Device ID' => $device->id,
                'Device Name' => $device->name,
                'Hostel' => $device->hostel->name,
                'Status' => $status,
                'Last Seen' => $lastSeen,
                'Uptime (Hours)' => $uptime,
                'IP Address' => $device->ip_address,
                'Location' => $device->location,
                'Created' => $device->created_at->format('Y-m-d H:i'),
            ];
        }
    }
}
