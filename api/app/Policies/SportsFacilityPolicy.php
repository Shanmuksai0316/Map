<?php

namespace App\Policies;

use App\Models\SportsFacility;
use App\Models\User;
use App\Support\Roles;

class SportsFacilityPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
            Roles::STUDENT,
        ]);
    }

    public function view(User $user, SportsFacility $facility): bool
    {
        $attrs = $facility->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $facility->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
        ]);
    }

    public function update(User $user, SportsFacility $facility): bool
    {
        $attrs = $facility->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $facility->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
        ]);
    }

    public function delete(User $user, SportsFacility $facility): bool
    {
        $attrs = $facility->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $facility->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
        ]);
    }
}
