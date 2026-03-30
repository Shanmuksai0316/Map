<?php

namespace App\Policies;

use App\Domain\Attendance\Models\AttendanceSession;
use App\Models\User;

class AttendancePolicy
{
    public function viewSession(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }

        if ($user->hasAnyRole(['Campus Manager', 'Super Admin', 'College Management', 'College Mgmt'])) {
            return true;
        }

        if ($user->hasRole('Warden')) {
            return $this->isWardenForHostel($user, $session->hostel_id);
        }

        return false;
    }

    public function listRooms(User $user, AttendanceSession $session): bool
    {
        return $this->viewSession($user, $session);
    }

    public function viewRoster(User $user, AttendanceSession $session): bool
    {
        return $this->viewSession($user, $session);
    }

    public function mark(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }

        if ($session->status === AttendanceSession::STATUS_CLOSED) {
            return false;
        }

        if ($user->hasAnyRole(['Campus Manager', 'Super Admin', 'College Management', 'College Mgmt'])) {
            return true;
        }

        if ($user->hasRole('Warden')) {
            return $this->isWardenForHostel($user, $session->hostel_id);
        }

        return false;
    }

    public function submitRoom(User $user, AttendanceSession $session, int $roomId): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }

        if ($session->status === 'closed') {
            return false;
        }

        if (!$session->isOpen()) {
            return false;
        }

        if ($user->hasAnyRole(['Campus Manager', 'Super Admin', 'College Management', 'College Mgmt'])) {
            return true;
        }

        if ($user->hasRole('Warden')) {
            return $this->isWardenForHostel($user, $session->hostel_id);
        }

        return false;
    }

    public function editMark(User $user, AttendanceSession $session): bool
    {
        if ($user->tenant_id !== $session->tenant_id) {
            return false;
        }

        // Only allow editing if session is not closed
        if ($session->status === AttendanceSession::STATUS_CLOSED) {
            return false;
        }

        // Only allow editing if session is still open
        if (!$session->isOpen()) {
            return false;
        }

        if ($user->hasAnyRole(['Campus Manager', 'Super Admin', 'College Management', 'College Mgmt'])) {
            return true;
        }

        if ($user->hasRole('Warden')) {
            return $this->isWardenForHostel($user, $session->hostel_id);
        }

        return false;
    }

    public function batchMark(User $user, AttendanceSession $session, int $roomId): bool
    {
        if ($user->tenant_id !== $session->tenant_id) {
            return false;
        }

        if ($session->status === 'closed') {
            return false;
        }

        if ($user->hasAnyRole(['Campus Manager', 'Super Admin', 'College Management', 'College Mgmt'])) {
            return true;
        }

        if ($user->hasRole('Warden')) {
            return $this->isWardenForHostel($user, $session->hostel_id);
        }

        return false;
    }

    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager', 'Rector', 'College Management', 'College Mgmt']);
    }

    public function view(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null && $user->hasAnyRole(['Campus Manager']);
    }

    public function update(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole(['Campus Manager', 'Rector', 'Guard', 'College Management', 'College Mgmt']);
    }

    public function delete(User $user, AttendanceSession $session): bool
    {
        $attrs = $session->getAttributes() ?? [];
        if (array_key_exists('tenant_id', $attrs)) {
            if ((string) $user->tenant_id !== (string) $session->tenant_id) {
                return false;
            }
        }
        return $user->hasAnyRole(['Campus Manager']);
    }

    private function isWardenForHostel(User $user, int $hostelId): bool
    {
        // TODO: Implement hostel visibility logic based on user_scopes or similar
        // For now, assume all wardens can access all hostels in their tenant
        return true;
    }
}
