<?php

namespace App\Services;

use App\Models\User;
use App\Models\Hostel;
use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Notifications\StaffReassignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Staff Assignment Service
 * 
 * Handles cross-tenant staff assignment operations including:
 * - Assigning staff to hostels (including cross-tenant)
 * - Reassigning staff (auto-revokes previous assignment)
 * - Revoking assignments
 * - Querying assignment history
 */
class StaffAssignmentService
{
    /**
     * Assign or reassign staff to a hostel (supports cross-tenant)
     * 
     * Business Rules:
     * - One active assignment per staff (enforced by DB constraint)
     * - Previous assignment automatically revoked
     * - Can reassign across tenants (updates user.tenant_id)
     * - Can change role during reassignment
     * 
     * @param User $staff The staff user to assign
     * @param array $data ['tenant_id', 'hostel_id', 'role', 'notes']
     * @return void
     * @throws \Exception If validation fails
     */
    public function assignStaff(User $staff, array $data): void
    {
        // Validate user is MAP staff
        if (!$staff->isMapStaff()) {
            throw new \Exception('Only MAP staff can be assigned to hostels. Rector and College Management are college representatives and do not require hostel assignment.');
        }

        // Validate role is MAP staff role
        $newRole = $data['role'];
        if (!\App\Support\Roles::isMapStaffRole($newRole)) {
            throw new \Exception("Role '{$newRole}' is not a MAP staff role. Only MAP staff require hostel assignment.");
        }

        // Enforce one-user-per-role-per-hostel (prevent duplicates)
        $newHostelId = $data['hostel_id'] ?? null;
        $newTenantId = $data['tenant_id'] ?? null;

        if ($newHostelId && $newTenantId) {
            $duplicate = DB::table('staff_assignments as sa')
                ->join('model_has_roles as mhr', 'sa.user_id', '=', 'mhr.model_id')
                ->join('roles', 'mhr.role_id', '=', 'roles.id')
                ->whereNull('sa.revoked_at')
                ->where('sa.hostel_id', $newHostelId)
                ->where('sa.tenant_id', $newTenantId)
                ->where('roles.name', $newRole)
                ->where('sa.user_id', '!=', $staff->id)
                ->exists();

            if ($duplicate) {
                throw new \Exception("{$newRole} already assigned to this hostel. Revoke or reassign before adding another.");
            }
        }

        DB::transaction(function () use ($staff, $data) {
            $newTenantId = $data['tenant_id'];
            $newHostelId = $data['hostel_id'];
            $newRole = $data['role'];
            $notes = $data['notes'] ?? null;
            $assignedBy = auth()->id() ?? 1; // Default to system user if not authenticated (seeding context)

            // Validate hostel belongs to tenant
            $hostel = Hostel::where('id', $newHostelId)
                ->where('tenant_id', $newTenantId)
                ->firstOrFail();

            // Get current assignment
            $currentAssignment = DB::table('staff_assignments')
                ->where('user_id', $staff->id)
                ->whereNull('revoked_at')
                ->first();

            // Revoke current assignment if exists
            if ($currentAssignment) {
                $isCrossTenant = $currentAssignment->tenant_id != $newTenantId;
                $revocationReason = $isCrossTenant 
                    ? "Cross-tenant reassignment to {$hostel->name}"
                    : "Reassigned to {$hostel->name}";

                DB::table('staff_assignments')
                    ->where('id', $currentAssignment->id)
                    ->update([
                        'revoked_at' => now(),
                        'revocation_reason' => $revocationReason,
                        'revoked_by' => $assignedBy,
                        'updated_at' => now(),
                    ]);

                Log::info("Revoked staff assignment", [
                    'staff_id' => $staff->id,
                    'old_assignment_id' => $currentAssignment->id,
                    'reason' => $revocationReason,
                ]);
            }

            // Update user's tenant_id if cross-tenant reassignment
            if ($staff->tenant_id !== $newTenantId) {
                $staff->update(['tenant_id' => $newTenantId]);
                
                Log::info("Staff moved to new tenant", [
                    'staff_id' => $staff->id,
                    'old_tenant_id' => $staff->tenant_id,
                    'new_tenant_id' => $newTenantId,
                ]);
            }

            // Update role if changed
            $currentRole = $staff->roles->first()?->name;
            if ($currentRole !== $newRole) {
                // Use Spatie's syncRoles to replace all roles
                $staff->syncRoles([$newRole]);
                event(new UserRoleChanged($staff->id, $assignedBy, now()));
                
                Log::info("Staff role changed", [
                    'staff_id' => $staff->id,
                    'old_role' => $currentRole,
                    'new_role' => $newRole,
                ]);
            }

            // Create new assignment
            DB::table('staff_assignments')->insert([
                'user_id' => $staff->id,
                'tenant_id' => $newTenantId,
                'hostel_id' => $newHostelId,
                'assigned_at' => now(),
                'assigned_by' => $assignedBy,
                'assignment_notes' => $notes,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Fire event for audit logging
            event(new StaffAssignmentChanged($staff->id, $assignedBy, now()));

            // Send notification to staff
            $tenant = \App\Models\Tenant::find($newTenantId);
            if ($tenant) {
                $staff->notify(new StaffReassignedNotification($tenant, $hostel, $newRole));
            }

            Log::info("Staff assigned successfully", [
                'staff_id' => $staff->id,
                'tenant_id' => $newTenantId,
                'hostel_id' => $newHostelId,
                'role' => $newRole,
            ]);
        });
    }

    /**
     * Revoke staff assignment (removes staff from hostel).
     *
     * Hard rule: You cannot leave a hostel position empty.
     * If this is the only staff member with this role at the hostel,
     * revocation is blocked unless a replacement is provided.
     *
     * @param User   $staff        The staff user
     * @param string $reason       Reason for revocation
     * @param User|null $replacement  Optional replacement staff (for swap)
     * @return void
     * @throws \Exception If no replacement exists and position would be empty
     */
    public function revokeAssignment(User $staff, string $reason, ?User $replacement = null): void
    {
        // Find current active assignment
        $currentAssignment = DB::table('staff_assignments')
            ->where('user_id', $staff->id)
            ->whereNull('revoked_at')
            ->first();

        if (! $currentAssignment) {
            throw new \Exception('Staff has no active assignment to revoke.');
        }

        // Only enforce replacement protection for critical hostel roles.
        $protectedRoles = ['Campus Manager', 'Warden'];

        // Check if this is the only person in a protected role at this hostel.
        $currentRole = $staff->roles->first()?->name;
        if ($currentRole && in_array($currentRole, $protectedRoles, true) && $currentAssignment->hostel_id) {
            $othersInRole = DB::table('staff_assignments as sa')
                ->join('model_has_roles as mhr', 'sa.user_id', '=', 'mhr.model_id')
                ->join('roles', 'mhr.role_id', '=', 'roles.id')
                ->whereNull('sa.revoked_at')
                ->where('sa.hostel_id', $currentAssignment->hostel_id)
                ->where('sa.tenant_id', $currentAssignment->tenant_id)
                ->where('roles.name', $currentRole)
                ->where('sa.user_id', '!=', $staff->id)
                ->count();

            if ($othersInRole === 0 && ! $replacement) {
                throw new \Exception(
                    "Cannot revoke: {$currentRole} is the only staff in this role at this hostel. "
                    . 'Assign a replacement first, or use the Reassign action.'
                );
            }
        }

        DB::transaction(function () use ($staff, $reason, $replacement, $currentAssignment) {
            // Revoke the assignment
            DB::table('staff_assignments')
                ->where('id', $currentAssignment->id)
                ->update([
                    'revoked_at' => now(),
                    'revocation_reason' => $reason,
                    'revoked_by' => auth()->id() ?? 1,
                    'updated_at' => now(),
                ]);

            event(new StaffAssignmentChanged($staff->id, auth()->id() ?? 1, now()));

            Log::info('Staff assignment revoked', [
                'staff_id' => $staff->id,
                'reason' => $reason,
                'had_replacement' => $replacement !== null,
            ]);

            // If a replacement was provided, assign them to the same position
            if ($replacement) {
                $currentRole = $staff->roles->first()?->name;
                $this->assignStaff($replacement, [
                    'tenant_id' => $currentAssignment->tenant_id,
                    'hostel_id' => $currentAssignment->hostel_id,
                    'role' => $currentRole,
                    'notes' => "Replacement for {$staff->name} — {$reason}",
                ]);
            }
        });
    }

    /**
     * Reassign a position: revoke current staff and assign replacement in one atomic operation.
     * Guarantees the hostel position is never empty.
     *
     * @param User   $currentStaff  Staff being removed
     * @param User   $newStaff      Replacement staff
     * @param string $reason        Reason for reassignment
     * @return void
     */
    public function reassignPosition(User $currentStaff, User $newStaff, string $reason = 'Reassigned'): void
    {
        $currentAssignment = $this->getActiveAssignment($currentStaff);
        if (! $currentAssignment) {
            throw new \Exception('Current staff has no active assignment to reassign.');
        }

        $currentRole = $currentStaff->roles->first()?->name;
        if (! $currentRole) {
            throw new \Exception('Current staff has no role assigned.');
        }

        // Use revokeAssignment with replacement — atomic swap
        $this->revokeAssignment($currentStaff, $reason, $newStaff);

        Log::info('Position reassigned', [
            'from_staff_id' => $currentStaff->id,
            'to_staff_id' => $newStaff->id,
            'hostel_id' => $currentAssignment->hostel_id,
            'role' => $currentRole,
        ]);
    }

    /**
     * Get staff's active assignment
     * 
     * @param User $staff
     * @return object|null Assignment record or null if not assigned
     */
    public function getActiveAssignment(User $staff): ?object
    {
        return DB::table('staff_assignments')
            ->where('user_id', $staff->id)
            ->whereNull('revoked_at')
            ->first();
    }

    /**
     * Get assignment history for staff (all assignments including revoked)
     * 
     * @param User $staff
     * @return \Illuminate\Support\Collection
     */
    public function getAssignmentHistory(User $staff): \Illuminate\Support\Collection
    {
        return DB::table('staff_assignments')
            ->where('user_id', $staff->id)
            ->orderBy('assigned_at', 'desc')
            ->get();
    }

    /**
     * Check if staff has active assignment
     * 
     * @param User $staff
     * @return bool
     */
    public function hasActiveAssignment(User $staff): bool
    {
        return DB::table('staff_assignments')
            ->where('user_id', $staff->id)
            ->whereNull('revoked_at')
            ->exists();
    }

    /**
     * Get all staff assigned to a specific hostel
     * 
     * @param int $hostelId
     * @return \Illuminate\Support\Collection
     */
    public function getStaffByHostel(int $hostelId): \Illuminate\Support\Collection
    {
        return DB::table('staff_assignments')
            ->where('hostel_id', $hostelId)
            ->whereNull('revoked_at')
            ->get();
    }

    /**
     * Change staff role without changing hostel assignment
     * 
     * @param User $staff
     * @param string $newRole
     * @return void
     */
    public function changeRole(User $staff, string $newRole): void
    {
        DB::transaction(function () use ($staff, $newRole) {
            $currentRole = $staff->roles->first()?->name;
            
            if ($currentRole !== $newRole) {
                $staff->syncRoles([$newRole]);
                event(new UserRoleChanged($staff->id, auth()->id() ?? 1, now()));
                
                Log::info("Staff role changed (no reassignment)", [
                    'staff_id' => $staff->id,
                    'old_role' => $currentRole,
                    'new_role' => $newRole,
                ]);
            }
        });
    }
}
