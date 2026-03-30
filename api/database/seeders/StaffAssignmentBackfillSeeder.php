<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaffAssignmentBackfillSeeder extends Seeder
{
    /**
     * Backfill staff assignments for existing users.
     * This seeder should be run after the staff_assignments table is created.
     */
    public function run(): void
    {
        $this->command->info('Starting staff assignment backfill...');
        
        $tenants = \App\Models\Tenant::all();
        
        foreach ($tenants as $tenant) {
            $this->command->info("Processing tenant: {$tenant->name}");
            
            // Get all staff users (non-students) for this tenant
            $staffUsers = User::where('tenant_id', $tenant->id)
                ->where('kind', '!=', 'student')
                ->get();
                
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty()) {
                $this->command->warn("No hostels found for tenant {$tenant->name}, skipping...");
                continue;
            }
            
            foreach ($staffUsers as $user) {
                $this->command->info("Processing user: {$user->name} ({$user->email})");
                
                // Try to infer hostel assignment based on existing data
                $assignedHostelIds = $this->inferHostelAssignments($user, $hostels);
                
                if (empty($assignedHostelIds)) {
                    $this->command->warn("Could not infer hostel assignments for {$user->name}, skipping...");
                    continue;
                }
                
                // Create staff assignments
                foreach ($assignedHostelIds as $hostelId) {
                    DB::table('staff_assignments')->updateOrInsert(
                        [
                            'tenant_id' => $tenant->id,
                            'user_id' => $user->id,
                            'hostel_id' => $hostelId,
                        ],
                        [
                            'assigned_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
                
                $this->command->info("Created assignments for {$user->name} to " . count($assignedHostelIds) . " hostels");
            }
        }
        
        $this->command->info('Staff assignment backfill completed!');
    }
    
    /**
     * Infer hostel assignments for a user based on existing data.
     */
    private function inferHostelAssignments(User $user, $hostels): array
    {
        $assignedHostelIds = [];
        
        // Strategy 1: Check if user has any existing relationships with specific hostels
        // This could be through attendance sessions, tickets, etc.
        
        // Check attendance sessions
        $attendanceHostelIds = DB::table('attendance_sessions')
            ->where('tenant_id', $user->tenant_id)
            ->where('created_by', $user->id)
            ->distinct()
            ->pluck('hostel_id')
            ->toArray();
            
        if (!empty($attendanceHostelIds)) {
            $assignedHostelIds = array_merge($assignedHostelIds, $attendanceHostelIds);
        }
        
        // Check tickets assigned to user
        $ticketHostelIds = DB::table('tickets')
            ->where('tenant_id', $user->tenant_id)
            ->where('assignee_user_id', $user->id)
            ->distinct()
            ->pluck('hostel_id')
            ->toArray();
            
        if (!empty($ticketHostelIds)) {
            $assignedHostelIds = array_merge($assignedHostelIds, $ticketHostelIds);
        }
        
        // Check gate entries created by user
        $gateHostelIds = DB::table('gate_entries')
            ->where('tenant_id', $user->tenant_id)
            ->where('created_by', $user->id)
            ->distinct()
            ->pluck('hostel_id')
            ->toArray();
            
        if (!empty($gateHostelIds)) {
            $assignedHostelIds = array_merge($assignedHostelIds, $gateHostelIds);
        }
        
        // Remove duplicates
        $assignedHostelIds = array_unique($assignedHostelIds);
        
        // If we couldn't infer any specific assignments, return empty array
        // This will allow the user to see all hostels (backward compatibility)
        if (empty($assignedHostelIds)) {
            return [];
        }
        
        // Filter to only include hostels that exist for this tenant
        $validHostelIds = $hostels->pluck('id')->toArray();
        $assignedHostelIds = array_intersect($assignedHostelIds, $validHostelIds);
        
        return $assignedHostelIds;
    }
}
