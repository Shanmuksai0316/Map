<?php

namespace App\Policies;

use App\Models\Notice;
use App\Models\User;

class NoticePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin', 'Student']);
    }

    public function view(User $user, Notice $notice): bool
    {
        $attrs = $notice->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $notice->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, Notice $notice): bool
    {
        $attrs = $notice->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $notice->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function delete(User $user, Notice $notice): bool
    {
        $attrs = $notice->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $notice->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole(['Campus Manager', 'Super Admin']);
    }
}
