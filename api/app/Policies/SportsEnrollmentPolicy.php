<?php

namespace App\Policies;

use App\Models\SportsEnrollment;
use App\Models\User;
use App\Support\Roles;

class SportsEnrollmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
            Roles::STUDENT,
        ]);
    }

    public function view(User $user, SportsEnrollment $enrollment): bool
    {
        $attrs = $enrollment->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $enrollment->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $user->student?->id === $enrollment->student_id)
        );
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRole(Roles::STUDENT);
    }

    public function update(User $user, SportsEnrollment $enrollment): bool
    {
        $attrs = $enrollment->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $enrollment->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $user->student?->id === $enrollment->student_id && $enrollment->isActive())
        );
    }

    public function delete(User $user, SportsEnrollment $enrollment): bool
    {
        $attrs = $enrollment->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $enrollment->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $user->student?->id === $enrollment->student_id && $enrollment->isActive())
        );
    }

    public function cancel(User $user, SportsEnrollment $enrollment): bool
    {
        $attrs = $enrollment->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $enrollment->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $user->student?->id === $enrollment->student_id && $enrollment->isActive())
        );
    }

    public function markAttended(User $user, SportsEnrollment $enrollment): bool
    {
        $attrs = $enrollment->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $enrollment->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
        ]);
    }

    public function markNoShow(User $user, SportsEnrollment $enrollment): bool
    {
        $attrs = $enrollment->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $enrollment->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
        ]);
    }
}
