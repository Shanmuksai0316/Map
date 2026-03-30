<?php

namespace App\Policies\Filament;

use App\Models\User;

class AccessPolicy
{
    public function access(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function campusManager(User $user): bool
    {
        return $user->hasRole('Campus Manager');
    }
}
