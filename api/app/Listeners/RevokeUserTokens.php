<?php

namespace App\Listeners;

use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Models\User;

class RevokeUserTokens
{
    /**
     * Handle the event.
     */
    public function handle(UserRoleChanged|StaffAssignmentChanged $event): void
    {
        $user = User::find($event->userId);
        if ($user) {
            $user->tokens()->delete(); // Sanctum
        }

        // Safety net: ensure tokens are removed even if user lookup fails
        \Laravel\Sanctum\PersonalAccessToken::where('tokenable_id', $event->userId)->delete();
    }
}