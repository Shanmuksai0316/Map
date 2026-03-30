<?php

namespace App\Policies;

use App\Models\GateDutyHandover;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GatePolicy
{
    use HandlesAuthorization;

    public function out(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function in(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function listOutPasses(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function devicesRegister(User $user): bool
    {
        return $user->hasAnyRole(['Campus Manager', 'Super Admin']);
    }

    public function devicesHeartbeat(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function visitorsList(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function visitorsAllow(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function visitorsDeny(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function listDutyHandovers(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function createDutyHandover(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function completeDutyHandover(User $user, GateDutyHandover $handover): bool
    {
        // Guards can complete their own handovers, managers can complete any in their hostels
        if ($user->hasAnyRole(['Campus Manager', 'Super Admin'])) {
            return true;
        }

        return $user->hasRole('Guard') && $handover->guard_id === $user->id;
    }

    public function scanQR(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function sendOtp(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }

    public function verifyBackupCode(User $user): bool
    {
        return $user->hasAnyRole(['Guard', 'Campus Manager', 'Super Admin']);
    }
}


