<?php

namespace App\Policies;

use App\Models\AttendanceSession;
use App\Models\User;

class AttendanceSessionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager']);
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole(['Campus Manager', 'Rector', 'Guard']);
    }
}
