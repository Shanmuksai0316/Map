<?php

namespace App\Policies;

use App\Models\LaundryRequest;
use App\Models\User;
use App\Support\Roles;

class LaundryRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::LAUNDRY_MANAGER,
            Roles::WARDEN,
            Roles::RM_SUPERVISOR, // RM Supervisors may need to view laundry requests
        ]);
    }

    public function view(User $user, LaundryRequest $request): bool
    {
        $attrs = $request->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $request->tenant_id) {
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
            Roles::STUDENT, // Students can create their own requests
        ]);
    }

    public function update(User $user, LaundryRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        // Campus Managers and Laundry Staff can update any request
        if ($user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::LAUNDRY_MANAGER])) {
            return true;
        }

        // Students can only update their own pending requests
        if ($user->hasRole(Roles::STUDENT)) {
            $student = $user->student;
            if ($student && $student->id === $request->student_id) {
                return $request->status->value === 'pending';
            }
        }

        return false;
    }

    public function delete(User $user, LaundryRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        // Only Campus Managers can delete requests
        if ($user->hasRole(Roles::CAMPUS_MANAGER)) {
            return true;
        }

        // Students can only delete their own pending requests
        if ($user->hasRole(Roles::STUDENT)) {
            $student = $user->student;
            if ($student && $student->id === $request->student_id) {
                return $request->status->value === 'pending';
            }
        }

        return false;
    }

    public function updateStatus(User $user, LaundryRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        // Only Laundry Staff and Campus Managers can update status
        return $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::LAUNDRY_MANAGER]);
    }

    public function collect(User $user, LaundryRequest $request): bool
    {
        return $this->updateStatus($user, $request) && 
               $request->status->value === 'scheduled';
    }

    public function deliver(User $user, LaundryRequest $request): bool
    {
        return $this->updateStatus($user, $request) && 
               $request->status->value === 'ready';
    }

    public function cancel(User $user, LaundryRequest $request): bool
    {
        if ($user->tenant_id !== $request->tenant_id) {
            return false;
        }

        // Campus Managers can cancel any active request
        if ($user->hasRole(Roles::CAMPUS_MANAGER)) {
            return $request->isActive();
        }

        // Students can cancel their own pending/scheduled requests
        if ($user->hasRole(Roles::STUDENT)) {
            $student = $user->student;
            if ($student && $student->id === $request->student_id) {
                return in_array($request->status->value, ['pending', 'scheduled']);
            }
        }

        return false;
    }

    public function markAsLost(User $user, LaundryRequest $request): bool
    {
        return $this->updateStatus($user, $request) && 
               $request->isActive();
    }

    public function markAsDamaged(User $user, LaundryRequest $request): bool
    {
        return $this->updateStatus($user, $request) && 
               $request->isActive();
    }
}
