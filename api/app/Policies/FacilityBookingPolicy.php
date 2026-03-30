<?php

namespace App\Policies;

use App\Models\FacilityBooking;
use App\Models\User;
use App\Support\Roles;

class FacilityBookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
            Roles::STUDENT,
        ]);
    }

    public function view(User $user, FacilityBooking $booking): bool
    {
        $attrs = $booking->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $booking->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRole(Roles::STUDENT);
    }

    public function update(User $user, FacilityBooking $booking): bool
    {
        $attrs = $booking->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $booking->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $booking->student_id === $user->student->id)
        );
    }

    public function delete(User $user, FacilityBooking $booking): bool
    {
        $attrs = $booking->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $booking->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $booking->student_id === $user->student->id)
        );
    }
}
