<?php

namespace App\Policies\Auth;

use App\Models\User;

class LoginPolicy
{
    /**
     * Check if user can attempt login (mobile API)
     */
    public function attempt(?User $user): bool
    {
        if ($user === null) {
            return true;
        }

        // Super Admin can access (no tenant_id check needed)
        if ($user->hasRole('Super Admin')) {
            return !$user->archived;
        }

        // All other users must have tenant_id
        if ($user->tenant_id === null) {
            return false;
        }

        // Allow Students and mobile-enabled roles (use Spatie roles instead of kind)
        // Note: MAP staff roles (Guard, Warden, etc.) should have active hostel assignment
        // but we don't enforce it here - it's handled at the API endpoint level if needed
        $mobileRoles = [
            'Student',
            'Guard',
            'Warden',
            'HK Supervisor',
            'RM Supervisor',
            'Laundry Manager',
            'Sports Manager',
        ];
        
        if ($user->hasAnyRole($mobileRoles)) {
            return !$user->archived;
        }

        // Allow web-accessible roles for mobile app too (they have both web + mobile access)
        // College representatives (Rector, College Management) do NOT require hostel assignment
        // Campus Manager is MAP staff and should have hostel assignment, but we allow login here
        // Actual hostel assignment validation should be done at the endpoint level for MAP staff
        if ($user->hasAnyRole(['Campus Manager', 'Rector', 'College Management', 'College Mgmt'])) {
            return !$user->archived;
        }

        return false;
    }

    /**
     * Check if user can access web panels (Filament)
     */
    public function canAccessWeb(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        // Super Admin, Campus Manager, Rector, College Mgmt can access web
        return $user->hasAnyRole(['Super Admin', 'Campus Manager', 'Rector', 'College Management', 'College Mgmt']) 
            && !$user->archived;
    }
}
