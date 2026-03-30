<?php

namespace App\Policies;

use App\Models\Incident;
use App\Models\User;

class IncidentPolicy
{
    public function viewAny(User $user): bool
    {
        // Warden, Campus Manager, Rector can view incidents
        return $user->hasAnyRole(['Warden', 'Campus Manager', 'Rector', 'Super Admin']);
    }

    public function view(User $user, Incident $incident): bool
    {
        // Must be in same tenant (only if model has tenant_id)
        $attrs = $incident->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $incident->tenant_id) {
                return false;
            }
        }

        // Warden can view incidents in their hostels
        if ($user->hasRole('Warden')) {
            return $user->staffHostels->contains($incident->hostel_id);
        }

        // Campus Manager, Rector, Super Admin can view all
        return $user->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin']);
    }

    public function create(User $user): bool
    {
        // System creates most incidents automatically
        // But staff can also create manual incidents
        return $user->hasAnyRole(['Warden', 'Campus Manager', 'Guard', 'Super Admin']);
    }

    public function update(User $user, Incident $incident): bool
    {
        // Only open incidents can be updated
        if (!$incident->isOpen()) {
            return false;
        }

        // Must be in same tenant (only if model has tenant_id)
        $attrs = $incident->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $incident->tenant_id) {
                return false;
            }
        }

        return $user->hasAnyRole(['Warden', 'Campus Manager', 'Super Admin']);
    }

    public function close(User $user, Incident $incident): bool
    {
        // Only open incidents can be closed
        if (!$incident->isOpen()) {
            return false;
        }

        // Must be in same tenant (only if model has tenant_id)
        $attrs = $incident->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $incident->tenant_id) {
                return false;
            }
        }

        // Warden can close incidents in their hostels
        if ($user->hasRole('Warden')) {
            return $user->staffHostels->contains($incident->hostel_id);
        }

        return $user->hasAnyRole(['Campus Manager', 'Super Admin']);
    }

    public function delete(User $user, Incident $incident): bool
    {
        // Only Super Admin can delete incidents (for data cleanup)
        $attrs = $incident->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $incident->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Super Admin');
    }
}

