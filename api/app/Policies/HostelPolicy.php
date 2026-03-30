<?php

namespace App\Policies;

use App\Models\Hostel;
use App\Models\User;

class HostelPolicy
{
    public function viewAny(User $user): bool
    {
        // Allow Super Admin and users with tenant_id to view hostels
        return $user->hasRole('Super Admin') || $user->tenant_id !== null;
    }

    public function create(User $user): bool
    {
        // Only Super Admin can add hostels to activated tenants
        return $user->hasRole('Super Admin');
    }

    public function update(User $user, Hostel $hostel): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function view(User $user, Hostel $hostel): bool
    {
        $attrs = $hostel->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            return (string) $user->tenant_id === (string) $hostel->tenant_id;
        }
        return true;
    }
}
