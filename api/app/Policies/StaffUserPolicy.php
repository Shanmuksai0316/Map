<?php

namespace App\Policies;

use App\Models\StaffUser;
use App\Models\User;

class StaffUserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin') && 
               config('features.super_admin_staff_mgmt', false);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, StaffUser $staffUser): bool
    {
        $attrs = $staffUser->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $staffUser->tenant_id !== (string) $user->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Super Admin') && 
               config('features.super_admin_staff_mgmt', false);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin') && 
               config('features.super_admin_staff_mgmt', false);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, StaffUser $staffUser): bool
    {
        $attrs = $staffUser->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $staffUser->tenant_id !== (string) $user->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Super Admin') && 
               config('features.super_admin_staff_mgmt', false);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, StaffUser $staffUser): bool
    {
        $attrs = $staffUser->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $staffUser->tenant_id !== (string) $user->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Super Admin') && 
               config('features.super_admin_staff_mgmt', false);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, StaffUser $staffUser): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, StaffUser $staffUser): bool
    {
        return false;
    }
}