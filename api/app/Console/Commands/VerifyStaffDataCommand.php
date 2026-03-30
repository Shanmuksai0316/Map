<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Tenant;
use App\Models\Hostel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerifyStaffDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:verify-staff-data';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify staff assignment data integrity';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔍 Verifying Staff Assignment Data Integrity...');
        $this->newLine();

        $issues = [];

        // Check 1: All staff have valid tenant_id
        $this->info('1. Checking staff tenant_id validity...');
        $invalidTenants = User::where('kind', 'staff')
            ->whereNotIn('tenant_id', Tenant::pluck('id'))
            ->count();
        
        if ($invalidTenants > 0) {
            $issues[] = "Found {$invalidTenants} staff with invalid tenant_id";
            $this->error("  ✗ {$invalidTenants} staff have invalid tenant_id");
        } else {
            $this->info('  ✓ All staff have valid tenant_id');
        }

        // Check 2: Assignments reference existing hostels
        $this->info('2. Checking assignment hostel references...');
        $invalidHostels = DB::table('staff_assignments')
            ->whereNotIn('hostel_id', DB::table('hostels')->pluck('id'))
            ->count();
        
        if ($invalidHostels > 0) {
            $issues[] = "Found {$invalidHostels} assignments with invalid hostel_id";
            $this->error("  ✗ {$invalidHostels} assignments reference non-existent hostels");
        } else {
            $this->info('  ✓ All assignments reference existing hostels');
        }

        // Check 3: No duplicate active assignments
        $this->info('3. Checking for duplicate active assignments...');
        $duplicates = DB::table('staff_assignments')
            ->select('user_id')
            ->whereNull('revoked_at')
            ->groupBy('user_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();
        
        if ($duplicates->isNotEmpty()) {
            $issues[] = "Found {$duplicates->count()} staff with multiple active assignments";
            $this->error("  ✗ {$duplicates->count()} staff have duplicate active assignments");
            foreach ($duplicates as $dup) {
                $staff = User::find($dup->user_id);
                $this->warn("    - {$staff->name} (ID: {$staff->id})");
            }
        } else {
            $this->info('  ✓ No duplicate active assignments');
        }

        // Check 4: Assignment history complete
        $this->info('4. Checking assignment history integrity...');
        $incompleteHistory = DB::table('staff_assignments')
            ->whereNull('assigned_at')
            ->orWhereNull('created_at')
            ->count();
        
        if ($incompleteHistory > 0) {
            $issues[] = "Found {$incompleteHistory} assignments with missing timestamps";
            $this->error("  ✗ {$incompleteHistory} assignments have incomplete timestamps");
        } else {
            $this->info('  ✓ All assignments have complete timestamps');
        }

        // Check 5: Roles properly assigned
        $this->info('5. Checking role assignments...');
        $staffWithoutRoles = User::where('kind', 'staff')
            ->doesntHave('roles')
            ->count();
        
        if ($staffWithoutRoles > 0) {
            $issues[] = "Found {$staffWithoutRoles} staff without assigned roles";
            $this->error("  ✗ {$staffWithoutRoles} staff have no roles assigned");
        } else {
            $this->info('  ✓ All staff have roles assigned');
        }

        // Check 6: Cross-tenant data consistency
        $this->info('6. Checking cross-tenant consistency...');
        $inconsistent = DB::table('staff_assignments as sa')
            ->join('users as u', 'sa.user_id', '=', 'u.id')
            ->whereNull('sa.revoked_at')
            ->whereColumn('sa.tenant_id', '!=', 'u.tenant_id')
            ->count();
        
        if ($inconsistent > 0) {
            $issues[] = "Found {$inconsistent} staff with inconsistent tenant_id";
            $this->error("  ✗ {$inconsistent} active assignments have mismatched tenant_id");
        } else {
            $this->info('  ✓ All active assignments have consistent tenant_id');
        }

        // Summary
        $this->newLine();
        if (empty($issues)) {
            $this->info('✅ All checks passed! Data integrity verified.');
            
            // Show statistics
            $this->newLine();
            $this->info('📊 Statistics:');
            $this->table(
                ['Metric', 'Count'],
                [
                    ['Total Staff', User::where('kind', 'staff')->count()],
                    ['Active Assignments', DB::table('staff_assignments')->whereNull('revoked_at')->count()],
                    ['Revoked Assignments', DB::table('staff_assignments')->whereNotNull('revoked_at')->count()],
                    ['Unassigned Staff', User::where('kind', 'staff')->whereDoesntHave('staffHostels')->count()],
                    ['Cross-tenant Transfers', DB::table('staff_assignments')->whereNotNull('revoked_at')->where('revocation_reason', 'like', '%Cross-tenant%')->count()],
                ]
            );
            
            return 0;
        } else {
            $this->error('❌ Data integrity issues found:');
            foreach ($issues as $issue) {
                $this->error("  - {$issue}");
            }
            return 1;
        }
    }
}

