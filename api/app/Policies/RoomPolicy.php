<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function view(User $user, Room $room): bool
    {
        $attrs = $room->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            return (string) $user->tenant_id === (string) $room->tenant_id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasRole('Campus Manager');
    }

    public function update(User $user, Room $room): bool
    {
        $attrs = $room->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $room->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Campus Manager');
    }

    public function delete(User $user, Room $room): bool
    {
        $attrs = $room->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $room->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Campus Manager');
    }
}
