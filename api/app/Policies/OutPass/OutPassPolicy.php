<?php

namespace App\Policies\OutPass;

use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Student;
use App\Models\User;

class OutPassPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->tenant_id === null) {
            return false;
        }

        $kind = strtolower((string) $user->kind);

        // Students can view their own outpasses
        if ($kind === 'student') {
            return true;
        }

        // Staff with management roles can view outpasses
        if ($kind === 'staff' && $user->hasAnyRole(['Rector', 'Campus Manager', 'Super Admin'])) {
            return true;
        }

        return false;
    }

    public function view(User $user, OutPass $outPass): bool
    {
        $attrs = $outPass->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            return (string) $user->tenant_id === (string) $outPass->tenant_id;
        }
        return true;
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && strtolower((string) $user->kind) === 'student';
    }

    public function update(User $user, OutPass $outPass): bool
    {
        $attrs = $outPass->getAttributes() ?? [];
        $tenantMatches = true;

        if (array_key_exists('tenant_id', $attrs)) {
            $tenantMatches = (string) $user->tenant_id === (string) $outPass->tenant_id;
        }

        return $tenantMatches
            && $user->hasAnyRole(['Rector', 'Campus Manager'])
            && $outPass->status === OutPassStatus::PENDING;
    }

    public function cancel(User $user, OutPass $outPass): bool
    {
        $attrs = $outPass->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $outPass->tenant_id) {
                return false;
            }
        }

        if ($user->hasRole('Campus Manager') && in_array($outPass->status, [OutPassStatus::PENDING, OutPassStatus::APPROVED], true)) {
            return true;
        }

        $student = Student::query()->where('user_id', $user->id)->first();

        return $student?->id === $outPass->student_id
            && $outPass->status === OutPassStatus::PENDING;
    }
}
