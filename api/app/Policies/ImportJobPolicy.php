<?php

namespace App\Policies;

use App\Models\ImportJob;
use App\Models\User;

class ImportJobPolicy
{
    public function create(User $user, string $kind): bool
    {
        return $user->tenant_id !== null
            && $user->hasRole('Campus Manager')
            && in_array($kind, ['students', 'room_allotments'], true);
    }

    public function update(User $user, ImportJob $job): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }
        $attrs = $job->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $job->tenant_id) {
                return false;
            }
        }
        return $user->hasRole('Campus Manager');
    }
}
