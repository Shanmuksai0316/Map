<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\RoomAllocation;
use App\Models\User;

class RoomAllocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function view(User $user, RoomAllocation $allocation): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }
        $attrs = $allocation->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $allocation->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRole('Campus Manager');
    }

    public function update(User $user, RoomAllocation $allocation): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }
        $attrs = $allocation->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $allocation->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Campus Manager');
    }

    public function delete(User $user, RoomAllocation $allocation): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }
        $attrs = $allocation->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $allocation->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Campus Manager');
    }
}
