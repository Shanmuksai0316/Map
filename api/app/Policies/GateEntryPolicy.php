<?php

namespace App\Policies;

use App\Models\GateEntry;
use App\Models\User;

class GateEntryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Guard', 'Campus Manager']);
    }

    public function view(User $user, GateEntry $entry): bool
    {
        $attrs = $entry->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $entry->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole(['Campus Manager', 'Rector']);
    }
}
