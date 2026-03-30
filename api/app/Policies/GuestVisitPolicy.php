<?php

namespace App\Policies;

use App\Domain\Visitors\Models\GuestVisit;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class GuestVisitPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Campus Manager', 'Super Admin', 'Rector']);
    }

    public function view(User $user, GuestVisit $guestVisit): bool
    {
        $attrs = $guestVisit->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $guestVisit->tenant_id) {
                return false;
            }
        }

        if ($user->hasAnyRole(['Campus Manager', 'Super Admin', 'Rector'])) {
            return true;
        }

        // Students can view their own
        if ($user->hasRole('Student')) {
            $student = $user->student;
            return $student && $student->id === $guestVisit->student_id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Student');
    }

    public function list(User $user): bool
    {
        return $user->hasAnyRole(['Student', 'Campus Manager', 'Super Admin', 'Rector']);
    }

    public function mine(User $user): bool
    {
        return $user->hasRole('Student');
    }

    public function cancel(User $user, GuestVisit $guestVisit): bool
    {
        if ($user->tenant_id !== $guestVisit->tenant_id) {
            return false;
        }

        // Only allow cancellation if pre-registered and visit date >= today
        if ($guestVisit->status !== GuestVisit::STATUS_PRE_REGISTERED) {
            return false;
        }

        if ($guestVisit->visit_date->lessThan(today())) {
            return false;
        }

        // Student can cancel their own
        if ($user->hasRole('Student')) {
            $student = $user->student;
            return $student && $student->id === $guestVisit->student_id;
        }

        return false;
    }
}

