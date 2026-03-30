<?php

namespace App\Policies;

use App\Domain\Checklists\Models\ChecklistInstance;
use App\Models\User;

class ChecklistPolicy
{
    public function view(User $user, ChecklistInstance $instance): bool
    {
        $attrs = $instance->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $instance->tenant_id) {
                return false;
            }
        }

        if ($user->id === $instance->assignee_user_id) {
            return true;
        }

        return $user->hasAnyRole(['Campus Manager', 'Rector', 'Super Admin']);
    }

    public function mark(User $user, ChecklistInstance $instance): bool
    {
        $attrs = $instance->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $instance->tenant_id) {
                return false;
            }
        }
        return $user->id === $instance->assignee_user_id
            && $instance->status === 'Pending';
    }

    public function submit(User $user, ChecklistInstance $instance): bool
    {
        return $this->mark($user, $instance);
    }

    public function approve(User $user, ChecklistInstance $instance): bool
    {
        $attrs = $instance->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $instance->tenant_id) {
                return false;
            }
        }

        if (! $user->hasAnyRole(['Campus Manager', 'Super Admin'])) {
            return false;
        }

        if ($instance->status !== 'Submitted') {
            return false;
        }

        if (! in_array($instance->review_status, ['Pending', 'SentBack'], true)) {
            return false;
        }

        return true;
    }

    public function sendBack(User $user, ChecklistInstance $instance): bool
    {
        return $this->approve($user, $instance);
    }
}

