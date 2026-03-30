<?php

namespace App\Policies;

use App\Models\Domain\OutPass\OutPassExport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class OutPassExportPolicy
{
    public function view(User $user, OutPassExport $export): Response
    {
        $attrs = $export->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $export->tenant_id) {
                return Response::deny('You do not own this export.');
            }
        }
        return Response::allow();
    }

    public function create(User $user): Response
    {
        $isAllowedKind = in_array($user->kind, ['CampusManager', 'Rector'], true);
        $hasRole = method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['Campus Manager', 'Rector']);

        return $user->tenant_id !== null && ($isAllowedKind || $hasRole)
            ? Response::allow()
            : Response::deny('You are not allowed to export outpasses.');
    }
}
