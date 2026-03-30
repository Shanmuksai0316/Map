<?php

namespace App\Policies;

use App\Models\SportsEvent;
use App\Models\User;
use App\Support\Roles;

class SportsEventPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
            Roles::STUDENT,
        ]);
    }

    public function view(User $user, SportsEvent $event): bool
    {
        $attrs = $event->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $event->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
        ]);
    }

    public function update(User $user, SportsEvent $event): bool
    {
        $attrs = $event->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $event->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
        ]);
    }

    public function delete(User $user, SportsEvent $event): bool
    {
        $attrs = $event->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $event->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
        ]);
    }

    public function enroll(User $user, SportsEvent $event): bool
    {
        $attrs = $event->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $event->tenant_id) {
                return false;
            }
        }
        return (
            $user->hasAnyRole([Roles::CAMPUS_MANAGER, Roles::SPORTS_MANAGER]) ||
            ($user->hasRole(Roles::STUDENT) && $event->isActive() && $event->isRegistrationOpen())
        );
    }

    public function cancel(User $user, SportsEvent $event): bool
    {
        $attrs = $event->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $event->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole([
            Roles::CAMPUS_MANAGER,
            Roles::SPORTS_MANAGER,
        ]);
    }
}
