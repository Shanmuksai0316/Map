<?php

namespace App\Services\Notifications;

use App\Models\User;

class NotificationRecipients
{
    public function rectorForHostel(string $tenantId, int $hostelId): ?User
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Rector'))
            ->first();
    }

    public function wardenForHostel(string $tenantId, int $hostelId): ?User
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Warden'))
            ->whereHas('staffHostels', fn ($q) => $q->where('hostels.id', $hostelId))
            ->first();
    }

    public function hkSupervisorForHostel(string $tenantId, int $hostelId): ?User
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'HK Supervisor'))
            ->whereHas('staffHostels', fn ($q) => $q->where('hostels.id', $hostelId))
            ->first();
    }

    public function campusManagerForTenant(string $tenantId): ?User
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Campus Manager'))
            ->first();
    }

    /**
     * Get all guards assigned to this hostel for the tenant.
     *
     * @return User[]
     */
    public function guardsForHostel(string $tenantId, int $hostelId): array
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Guard'))
            ->whereHas('staffHostels', fn ($q) => $q->where('hostels.id', $hostelId))
            ->get()
            ->all();
    }

    public function rmSupervisorForHostel(string $tenantId, int $hostelId): ?User
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'RM Supervisor'))
            ->whereHas('staffHostels', fn ($q) => $q->where('hostels.id', $hostelId))
            ->first();
    }

    /**
     * All guards for a hostel (already exposed above as guardsForHostel).
     *
     * @return User[]
     */

    /**
     * Laundry managers for a tenant (usually 0 or more).
     *
     * @return User[]
     */
    public function laundryManagersForTenant(string $tenantId): array
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Laundry Manager'))
            ->get()
            ->all();
    }

    /**
     * Sports managers for a tenant (usually 0 or more).
     *
     * @return User[]
     */
    public function sportsManagersForTenant(string $tenantId): array
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Sports Manager'))
            ->get()
            ->all();
    }

    /**
     * All staff (non-student) users for a tenant.
     *
     * @return User[]
     */
    public function staffAllForTenant(string $tenantId): array
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', '!=', 'Student'))
            ->get()
            ->all();
    }

    /**
     * All student users for a tenant.
     *
     * @return User[]
     */
    public function studentsForTenant(string $tenantId): array
    {
        return User::where('tenant_id', $tenantId)
            ->whereHas('roles', fn ($q) => $q->where('name', 'Student'))
            ->get()
            ->all();
    }
}

