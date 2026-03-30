<?php

namespace App\Services;

use App\Models\Hostel;
use App\Models\Tenant;
use App\Models\User;
use App\Events\StaffAssignmentChanged;
use App\Events\UserRoleChanged;
use App\Notifications\StaffReassignedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class StaffPoolService
{
    /**
     * Create a new staff member in the pool (unassigned).
     */
    public function createStaffMember(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                // Use provided password or generate a secure random one (staff uses OTP login)
                'password' => Hash::make($data['password'] ?? Str::random(32)),
                'kind' => 'staff',
                'is_map_staff' => true,
                'is_active' => true,
                'tenant_id' => null, // Unassigned initially
            ]);

            return $user;
        });
    }

    /**
     * Assign a staff member to a tenant and hostel with a role.
     */
    public function assignToTenant(
        User $user,
        Tenant $tenant,
        ?Hostel $hostel,
        string $roleName,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($user, $tenant, $hostel, $roleName, $notes) {
            // Update user's tenant
            $user->update([
                'tenant_id' => $tenant->id,
            ]);

            // Assign role
            $role = Role::findOrCreate($roleName, 'web');
            $user->syncRoles([$role->name]);

            // Create staff assignment if hostel is specified
            if ($hostel) {
                DB::table('staff_assignments')->insert([
                    'tenant_id' => $tenant->id,
                    'user_id' => $user->id,
                    'hostel_id' => $hostel->id,
                    'assigned_at' => now(),
                    'assignment_notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Fire events
            event(new UserRoleChanged($user->id, auth()->id() ?? 1, now()->toISOString()));
            if ($hostel) {
                event(new StaffAssignmentChanged($user->id, auth()->id() ?? 1, now()->toISOString()));
            }

            // Send notification
            $user->notify(new StaffReassignedNotification($tenant, $hostel, $roleName));
        });
    }

    /**
     * Reassign staff to a different tenant/hostel.
     */
    public function reassignStaff(
        User $user,
        Tenant $newTenant,
        ?Hostel $newHostel,
        string $newRole,
        string $reason,
        ?string $notes = null
    ): void {
        DB::transaction(function () use ($user, $newTenant, $newHostel, $newRole, $reason, $notes) {
            $oldTenantId = $user->tenant_id;
            $oldHostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();

            // Revoke existing assignments
            DB::table('staff_assignments')
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revocation_reason' => $reason,
                    'updated_at' => now(),
                ]);

            // Update user's tenant
            $user->update([
                'tenant_id' => $newTenant->id,
            ]);

            // Update role
            $role = Role::findOrCreate($newRole, 'web');
            $user->syncRoles([$role->name]);

            // Create new assignment if hostel is specified
            if ($newHostel) {
                DB::table('staff_assignments')->insert([
                    'tenant_id' => $newTenant->id,
                    'user_id' => $user->id,
                    'hostel_id' => $newHostel->id,
                    'assigned_at' => now(),
                    'assignment_notes' => $notes,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Revoke all tokens (force re-login)
            $user->tokens()->delete();

            // Fire events
            event(new UserRoleChanged($user->id, auth()->id() ?? 1, now()->toISOString()));
            event(new StaffAssignmentChanged($user->id, auth()->id() ?? 1, now()->toISOString()));

            // Send notification
            $user->notify(new StaffReassignedNotification($newTenant, $newHostel, $newRole));

            // Log audit entry
            DB::table('audit_logs')->insert([
                'tenant_id' => $newTenant->id,
                'user_id' => auth()->id() ?? 1,
                'action' => 'staff_reassignment',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'old_values' => json_encode([
                    'tenant_id' => $oldTenantId,
                    'hostel_ids' => $oldHostelIds,
                ]),
                'new_values' => json_encode([
                    'tenant_id' => $newTenant->id,
                    'hostel_id' => $newHostel?->id,
                    'role' => $newRole,
                ]),
                'reason' => $reason,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Archive a staff member.
     */
    public function archiveStaff(User $user, string $reason): void
    {
        DB::transaction(function () use ($user, $reason) {
            // Revoke all assignments
            DB::table('staff_assignments')
                ->where('user_id', $user->id)
                ->whereNull('revoked_at')
                ->update([
                    'revoked_at' => now(),
                    'revocation_reason' => 'Staff archived: ' . $reason,
                    'updated_at' => now(),
                ]);

            // Archive user
            $user->update([
                'archived' => true,
                'archived_at' => now(),
                'archived_reason' => $reason,
                'is_active' => false,
            ]);

            // Revoke all tokens
            $user->tokens()->delete();

            // Log audit entry
            DB::table('audit_logs')->insert([
                'tenant_id' => $user->tenant_id,
                'user_id' => auth()->id() ?? 1,
                'action' => 'staff_archived',
                'entity_type' => 'user',
                'entity_id' => $user->id,
                'reason' => $reason,
                'created_at' => now(),
            ]);
        });
    }

    /**
     * Get staff statistics.
     */
    public function getStaffStats(): array
    {
        return [
            'total' => User::staff()->count(),
            'assigned' => User::assigned()->count(),
            'unassigned' => User::unassigned()->count(),
            'archived' => User::archivedStaff()->count(),
        ];
    }

    /**
     * Get unassigned staff available for assignment.
     */
    public function getUnassignedStaff()
    {
        return User::unassigned()
            ->orderBy('name')
            ->get();
    }

    /**
     * Get staff by tenant.
     */
    public function getStaffByTenant(Tenant $tenant)
    {
        return User::staff()
            ->where('tenant_id', $tenant->id)
            ->where('archived', false)
            ->with(['roles', 'staffHostels'])
            ->orderBy('name')
            ->get();
    }
}

