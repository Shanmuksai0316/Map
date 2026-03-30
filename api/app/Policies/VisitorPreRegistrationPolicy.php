<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VisitorPreRegistration;

class VisitorPreRegistrationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Student', 'Warden', 'Guard', 'Campus Manager', 'Super Admin']);
    }

    public function view(User $user, VisitorPreRegistration $preRegistration): bool
    {
        // Must be in same tenant (if model has tenant_id)
        $attrs = $preRegistration->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $preRegistration->tenant_id) {
                return false;
            }
        }

        // Students can view their own pre-registrations
        if ($user->hasRole('Student') && $user->student) {
            return $user->student->id === $preRegistration->student_id;
        }

        // Warden can view for their hostels
        if ($user->hasRole('Warden')) {
            return $user->staffHostels->contains($preRegistration->hostel_id);
        }

        // Guards can view for checking visitors
        if ($user->hasRole('Guard')) {
            return $user->staffHostels->contains($preRegistration->hostel_id);
        }

        return $user->hasAnyRole(['Campus Manager', 'Super Admin']);
    }

    public function create(User $user): bool
    {
        // Students can pre-register their visitors
        return $user->hasRole('Student') && $user->student !== null;
    }

    public function update(User $user, VisitorPreRegistration $preRegistration): bool
    {
        // Only if pending
        if ($preRegistration->status !== 'Pending') {
            return false;
        }

        // Must be in same tenant (only if model has tenant_id)
        $attrs = $preRegistration->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $preRegistration->tenant_id) {
                return false;
            }
        }

        // Students can update their own pending pre-registrations
        if ($user->hasRole('Student') && $user->student) {
            return $user->student->id === $preRegistration->student_id;
        }

        return false;
    }

    public function approve(User $user, VisitorPreRegistration $preRegistration): bool
    {
        // Only Warden or higher can approve
        if (!$user->hasAnyRole(['Warden', 'Campus Manager', 'Super Admin'])) {
            return false;
        }

        // Only if pending
        if ($preRegistration->status !== 'Pending') {
            return false;
        }

        // Must be in same tenant (only if model has tenant_id)
        $attrs = $preRegistration->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $preRegistration->tenant_id) {
                return false;
            }
        }

        // Warden can only approve for their hostels
        if ($user->hasRole('Warden')) {
            return $user->staffHostels->contains($preRegistration->hostel_id);
        }

        return true;
    }

    public function decline(User $user, VisitorPreRegistration $preRegistration): bool
    {
        return $this->approve($user, $preRegistration); // Same logic
    }

    public function cancel(User $user, VisitorPreRegistration $preRegistration): bool
    {
        // Student can cancel their own
        if ($user->hasRole('Student') && $user->student) {
            return $user->student->id === $preRegistration->student_id && $preRegistration->status === 'Pending';
        }

        return false;
    }

    public function delete(User $user, VisitorPreRegistration $preRegistration): bool
    {
        // Only Super Admin can delete (respect tenant_id only if present)
        $attrs = $preRegistration->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $preRegistration->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Super Admin');
    }
}

