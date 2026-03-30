<?php

namespace App\Policies;

use App\Models\LaundryCycle;
use App\Models\User;
use App\Support\Roles;

class LaundryCyclePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::LAUNDRY_MANAGER,
            Roles::LAUNDRY_STAFF,
            Roles::WARDEN,
        ]);
    }

    public function view(User $user, LaundryCycle $cycle): bool
    {
        $attrs = $cycle->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $cycle->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::LAUNDRY_MANAGER,
            Roles::LAUNDRY_STAFF,
        ]);
    }

    public function update(User $user, LaundryCycle $cycle): bool
    {
        if ($user->tenant_id !== $cycle->tenant_id) {
            return false;
        }

        // Only Laundry Staff and Campus Managers can update cycles
        return $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::LAUNDRY_MANAGER, Roles::LAUNDRY_STAFF]);
    }

    public function delete(User $user, LaundryCycle $cycle): bool
    {
        if ($user->tenant_id !== $cycle->tenant_id) {
            return false;
        }

        // Only Campus Managers can delete cycles
        return $user->hasRole(Roles::CAMPUS_MANAGER);
    }

    public function start(User $user, LaundryCycle $cycle): bool
    {
        return $this->update($user, $cycle) && 
               $cycle->status->value === 'scheduled';
    }

    public function complete(User $user, LaundryCycle $cycle): bool
    {
        return $this->update($user, $cycle) && 
               in_array($cycle->status->value, ['ready', 'in_progress']);
    }

    public function cancel(User $user, LaundryCycle $cycle): bool
    {
        if ($user->tenant_id !== $cycle->tenant_id) {
            return false;
        }

        // Only Campus Managers can cancel cycles
        return $user->hasRole(Roles::CAMPUS_MANAGER) && $cycle->isActive();
    }

    public function assignOperator(User $user, LaundryCycle $cycle): bool
    {
        return $this->update($user, $cycle) && $cycle->isActive();
    }

    public function viewMetrics(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::LAUNDRY_MANAGER,
            Roles::LAUNDRY_STAFF,
        ]);
    }
}
