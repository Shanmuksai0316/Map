<?php

namespace App\Services\Gate;

use App\Domain\Gate\Models\GateDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DeviceGuard
{
    /**
     * Check if device enforcement is enabled
     */
    public function enabled(): bool
    {
        return config('features.gate_device_enforcement', false);
    }

    /**
     * Assert that the request comes from an enrolled, active device
     * 
     * @throws HttpException
     */
    public function assert(Request $request, User $user, int $hostelId): void
    {
        if (!$this->enabled()) {
            return; // Feature flag is OFF, no enforcement
        }

        // Get device UUID from header or query param (query param for testing)
        $deviceUuid = $request->header('X-Device-UUID') ?? $request->query('device_uuid');

        if (!$deviceUuid) {
            abort(403, 'Device UUID required');
        }

        // Lookup active device
        $device = GateDevice::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hostel_id', $hostelId)
            ->where('device_uuid', $deviceUuid)
            ->where('is_active', true)
            ->first();

        if (!$device) {
            abort(403, 'Device not enrolled');
        }

        // Update last_seen_at
        $device->update(['last_seen_at' => now()]);
    }
}

