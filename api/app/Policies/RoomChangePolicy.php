<?php

namespace App\Policies;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Models\User;

class RoomChangePolicy
{
    public function before(?User $user, string $ability): ?bool
    {
        if ($user && $user->hasRole('Super Admin')) {
            return true;
        }

        return null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function view(User $user, RoomChange $roomChange): bool
    {
        return $this->sameTenant($user, $roomChange) && $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    public function approve(User $user, RoomChange $roomChange): bool
    {
        return $roomChange->status === 'pending'
            && $this->sameTenant($user, $roomChange)
            && $user->hasRole('Campus Manager');
    }

    public function reject(User $user, RoomChange $roomChange): bool
    {
        return $roomChange->status === 'pending'
            && $this->sameTenant($user, $roomChange)
            && $user->hasAnyRole(['Campus Manager', 'Rector']);
    }

    protected function sameTenant(User $user, RoomChange $roomChange): bool
    {
        return $user->tenant_id === $roomChange->tenant_id;
    }
}
