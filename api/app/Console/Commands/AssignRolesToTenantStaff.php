<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Hostel;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Str;

class AssignRolesToTenantStaff extends Command
{
    protected $signature = 'tenant:assign-roles {tenantCode} {--auto-detect : Auto-detect roles from staff names}';
    protected $description = 'Assigns roles to all staff members for a specific tenant.';

    public function handle(): int
    {
        $tenantCode = $this->argument('tenantCode');
        $autoDetect = $this->option('auto-detect');

        $tenant = Tenant::where('code', $tenantCode)->first();
        if (!$tenant) {
            $this->error("Tenant with code {$tenantCode} not found.");
            return self::FAILURE;
        }

        $this->info("Tenant: {$tenant->name} ({$tenant->code})");
        $this->info("Tenant ID: {$tenant->id}\n");

        // Get all staff assigned to this tenant
        $staff = User::where('tenant_id', $tenant->id)
            ->where('kind', '!=', 'student')
            ->get();

        if ($staff->isEmpty()) {
            $this->warn("No staff found for this tenant.");
            return self::SUCCESS;
        }

        $this->info("Found {$staff->count()} staff members:\n");

        $hostel = Hostel::where('tenant_id', $tenant->id)->first();
        if (!$hostel) {
            $this->warn("No hostel found for this tenant. Staff assignments will be created without hostel.");
        }

        $assignedCount = 0;
        $skippedCount = 0;

        foreach ($staff as $user) {
            $currentRoles = $user->roles->pluck('name')->toArray();

            if (!empty($currentRoles)) {
                $this->line("  - {$user->name} ({$user->phone}) -> Already has roles: " . implode(', ', $currentRoles));
                $skippedCount++;
                continue;
            }

            // Determine role to assign
            $roleToAssign = 'Staff'; // Default

            if ($autoDetect) {
                // Try to determine role from staff assignments
                $assignment = DB::table('staff_assignments')
                    ->where('user_id', $user->id)
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('revoked_at')
                    ->first();

                // Check if user name suggests a role
                $nameLower = strtolower($user->name);
                if (str_contains($nameLower, 'campus manager') || str_contains($nameLower, 'cm')) {
                    $roleToAssign = 'Campus Manager';
                } elseif (str_contains($nameLower, 'warden')) {
                    $roleToAssign = 'Warden';
                } elseif (str_contains($nameLower, 'guard')) {
                    $roleToAssign = 'Guard';
                } elseif (str_contains($nameLower, 'rector')) {
                    $roleToAssign = 'Rector';
                } elseif (str_contains($nameLower, 'hk') || str_contains($nameLower, 'housekeeping')) {
                    $roleToAssign = 'HK Supervisor';
                } elseif (str_contains($nameLower, 'rm') || str_contains($nameLower, 'repair') || str_contains($nameLower, 'maintenance')) {
                    $roleToAssign = 'RM Supervisor';
                } elseif (str_contains($nameLower, 'sports')) {
                    $roleToAssign = 'Sports Manager';
                } elseif (str_contains($nameLower, 'laundry')) {
                    $roleToAssign = 'Laundry Manager';
                } elseif (str_contains($nameLower, 'tulip')) {
                    // For tulip staff, derive role from name parts
                    $nameParts = explode(' ', $nameLower);
                    if (count($nameParts) > 1 && $nameParts[0] === 'tulip') {
                        $roleCandidate = Str::title(implode(' ', array_slice($nameParts, 1)));
                        if (in_array($roleCandidate, User::mapStaffRoles())) {
                            $roleToAssign = $roleCandidate;
                        }
                    }
                }
            } else {
                // Interactive mode - ask user
                $roleToAssign = $this->choice(
                    "Select role for {$user->name} ({$user->phone}):",
                    array_merge(['Staff'], User::mapStaffRoles(), User::collegeRepresentativeRoles()),
                    'Staff'
                );
            }

            // Create role if it doesn't exist
            $role = Role::firstOrCreate(['name' => $roleToAssign, 'guard_name' => 'web']);
            $user->syncRoles([$role->name]);

            $this->info("  ✓ {$user->name} ({$user->phone}) -> Assigned role: {$roleToAssign}");
            $assignedCount++;

            // Create staff assignment if hostel exists and assignment doesn't exist
            if ($hostel) {
                $exists = DB::table('staff_assignments')
                    ->where('tenant_id', $tenant->id)
                    ->where('user_id', $user->id)
                    ->where('hostel_id', $hostel->id)
                    ->whereNull('revoked_at')
                    ->exists();

                if (!$exists) {
                    DB::table('staff_assignments')->insert([
                        'tenant_id' => $tenant->id,
                        'user_id' => $user->id,
                        'hostel_id' => $hostel->id,
                        'assigned_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        $this->newLine();
        $this->info("✅ Role assignment complete!");
        $this->info("   - Assigned roles to: {$assignedCount} staff");
        $this->info("   - Skipped (already had roles): {$skippedCount} staff");

        return self::SUCCESS;
    }
}

