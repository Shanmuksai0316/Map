<?php

use Illuminate\Support\Facades\Route;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Hostel;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

// Temporary route to assign roles to PPCU staff
// Access: https://admin.mapservices.in/assign-ppcu-roles (must be logged in as Super Admin)
Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/assign-ppcu-roles', function () {
        // Only allow Super Admin
        if (!auth()->user()->hasRole('Super Admin')) {
            abort(403, 'Only Super Admin can access this action.');
        }

        $tenant = Tenant::where('code', 'MAP-PPCU')->first();

        if (!$tenant) {
            return response()->json(['error' => 'Tenant MAP-PPCU not found.'], 404);
        }

        $output = [];
        $output[] = "========================================";
        $output[] = "Assigning Roles to PPCU Staff";
        $output[] = "========================================";
        $output[] = "";
        $output[] = "Tenant: {$tenant->name} ({$tenant->code})";
        $output[] = "Tenant ID: {$tenant->id}";
        $output[] = "";

        // Get all staff assigned to this tenant
        $staff = User::where('tenant_id', $tenant->id)
            ->where('kind', '!=', 'student')
            ->get();

        if ($staff->isEmpty()) {
            $output[] = "No staff found for this tenant.";
            return response('<pre>' . implode("\n", $output) . '</pre>');
        }

        $output[] = "Found {$staff->count()} staff members:";
        $output[] = "";

        $hostel = Hostel::where('tenant_id', $tenant->id)->first();
        if ($hostel) {
            $output[] = "Hostel: {$hostel->name} (ID: {$hostel->id})";
            $output[] = "";
        }

        $assignedCount = 0;
        $skippedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($staff as $user) {
                $currentRoles = $user->roles->pluck('name')->toArray();
                
                $output[] = "Processing: {$user->name} ({$user->phone})";
                $output[] = "  Current roles: " . (empty($currentRoles) ? "NONE" : implode(', ', $currentRoles));
                
                if (!empty($currentRoles)) {
                    $output[] = "  → Skipped (already has roles)";
                    $output[] = "";
                    $skippedCount++;
                    continue;
                }
                
                // Auto-detect role from name
                $roleToAssign = 'Staff';
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
                    $nameParts = explode(' ', $nameLower);
                    if (count($nameParts) > 1 && $nameParts[0] === 'tulip') {
                        $roleCandidate = Str::title(implode(' ', array_slice($nameParts, 1)));
                        if (in_array($roleCandidate, User::mapStaffRoles())) {
                            $roleToAssign = $roleCandidate;
                        } elseif (str_contains($roleCandidate, 'Sports')) {
                            $roleToAssign = 'Sports Manager';
                        } elseif (str_contains($roleCandidate, 'Laundry')) {
                            $roleToAssign = 'Laundry Manager';
                        } elseif (str_contains($roleCandidate, 'HK') || str_contains($roleCandidate, 'Housekeeping')) {
                            $roleToAssign = 'HK Supervisor';
                        } elseif (str_contains($roleCandidate, 'RM') || str_contains($roleCandidate, 'Repair') || str_contains($roleCandidate, 'Maintenance')) {
                            $roleToAssign = 'RM Supervisor';
                        }
                    }
                }
                
                // Create role if it doesn't exist
                $role = Role::firstOrCreate(['name' => $roleToAssign, 'guard_name' => 'web']);
                $user->syncRoles([$role->name]);
                
                $output[] = "  ✓ Assigned role: {$roleToAssign}";
                $assignedCount++;
                
                // Create staff assignment if hostel exists
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
                        $output[] = "  ✓ Created staff assignment";
                    }
                }
                
                $output[] = "";
            }
            
            DB::commit();
            
            $output[] = "========================================";
            $output[] = "✅ Role Assignment Complete!";
            $output[] = "========================================";
            $output[] = "   - Assigned roles to: {$assignedCount} staff";
            $output[] = "   - Skipped (already had roles): {$skippedCount} staff";
            
        } catch (\Exception $e) {
            DB::rollBack();
            $output[] = "";
            $output[] = "❌ Error: {$e->getMessage()}";
            $output[] = "Rolled back all changes.";
        }

        return response('<pre style="font-family: monospace; padding: 20px; background: #f5f5f5; border-radius: 5px;">' . htmlspecialchars(implode("\n", $output)) . '</pre>');
    });
});

