<?php

namespace App\Policies;

use App\Models\Campus;
use App\Models\User;

class CampusPolicy
{
    public function viewAny(User $user): bool
    {
        // Allow Super Admin and users with tenant_id to view campuses
        return $user->hasRole('Super Admin') || $user->tenant_id !== null;
    }

    public function view(User $user, Campus $campus): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        $attrs = $campus->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            return (string) $user->tenant_id === (string) $campus->tenant_id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin') || $user->tenant_id !== null;
    }

    public function update(User $user, Campus $campus): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return (string) $user->tenant_id === (string) $campus->tenant_id;
    }

    public function delete(User $user, Campus $campus): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return (string) $user->tenant_id === (string) $campus->tenant_id;
    }
}
