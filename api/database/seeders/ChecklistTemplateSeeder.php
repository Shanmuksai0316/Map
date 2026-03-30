<?php

namespace Database\Seeders;

use App\Domain\Checklists\Models\ChecklistTemplate;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class ChecklistTemplateSeeder extends Seeder
{
    /**
     * Seed default checklist templates for all tenants.
     */
    public function run(): void
    {
        $templates = [
            // Warden Templates
            [
                'role' => 'Warden',
                'title' => 'Morning Shift - Warden',
                'tasks' => [
                    ['code' => 'CHECK_STUDENTS', 'title' => 'Check all students are present in hostel', 'description' => 'Verify all students are accounted for after morning attendance'],
                    ['code' => 'INSPECT_ROOMS', 'title' => 'Inspect common areas', 'description' => 'Check all common areas are clean and in good condition'],
                    ['code' => 'REVIEW_INCIDENTS', 'title' => 'Review overnight incidents', 'description' => 'Check for any incidents that occurred during the night shift'],
                    ['code' => 'CHECK_MAINTENANCE', 'title' => 'Check pending maintenance requests', 'description' => 'Review and prioritize pending maintenance tickets'],
                ],
            ],
            [
                'role' => 'Warden',
                'title' => 'Evening Shift - Warden',
                'tasks' => [
                    ['code' => 'CHECK_OUTPASSES', 'title' => 'Review pending out-passes', 'description' => 'Approve or decline pending out-pass requests'],
                    ['code' => 'INSPECT_FACILITIES', 'title' => 'Inspect hostel facilities', 'description' => 'Check all facilities are functioning properly'],
                    ['code' => 'CHECK_VISITORS', 'title' => 'Review visitor logs', 'description' => 'Verify all visitor entries are properly documented'],
                ],
            ],
            
            // Guard Templates
            [
                'role' => 'Guard',
                'title' => 'Morning Shift - Guard',
                'tasks' => [
                    ['code' => 'CHECK_GATES', 'title' => 'Check all gates are locked', 'description' => 'Ensure all entry/exit gates are properly secured'],
                    ['code' => 'CHECK_PERIMETER', 'title' => 'Patrol perimeter', 'description' => 'Walk around hostel perimeter and check for issues'],
                    ['code' => 'CHECK_CCTV', 'title' => 'Verify CCTV cameras', 'description' => 'Ensure all CCTV cameras are functioning'],
                    ['code' => 'LOG_ENTRIES', 'title' => 'Log all entries/exits', 'description' => 'Maintain accurate log of all movements'],
                ],
            ],
            [
                'role' => 'Guard',
                'title' => 'Evening Shift - Guard',
                'tasks' => [
                    ['code' => 'CHECK_GATES', 'title' => 'Check all gates', 'description' => 'Ensure all entry/exit gates are properly secured'],
                    ['code' => 'VERIFY_OUTPASSES', 'title' => 'Verify out-passes', 'description' => 'Check and scan all out-passes for exiting students'],
                    ['code' => 'LOG_VISITORS', 'title' => 'Log visitors', 'description' => 'Maintain accurate visitor log'],
                    ['code' => 'CHECK_LIGHTING', 'title' => 'Check outdoor lighting', 'description' => 'Ensure all outdoor lights are functioning'],
                ],
            ],
            [
                'role' => 'Guard',
                'title' => 'Night Shift - Guard',
                'tasks' => [
                    ['code' => 'HOURLY_PATROL', 'title' => 'Hourly patrol', 'description' => 'Patrol entire hostel premises every hour'],
                    ['code' => 'CHECK_CURFEW', 'title' => 'Verify curfew compliance', 'description' => 'Ensure all students are inside after curfew time'],
                    ['code' => 'CHECK_DOORS', 'title' => 'Check all entry doors', 'description' => 'Verify all entry doors are locked'],
                    ['code' => 'MONITOR_EMERGENCY', 'title' => 'Monitor emergency exits', 'description' => 'Ensure emergency exits are clear and accessible'],
                ],
            ],
            
            // Housekeeping Templates
            [
                'role' => 'Housekeeping',
                'title' => 'Daily Cleaning - Housekeeping',
                'tasks' => [
                    ['code' => 'CLEAN_COMMON_AREAS', 'title' => 'Clean common areas', 'description' => 'Clean all common areas including lounges, corridors'],
                    ['code' => 'CLEAN_BATHROOMS', 'title' => 'Clean bathrooms', 'description' => 'Deep clean all bathrooms and restock supplies'],
                    ['code' => 'CLEAN_DINING', 'title' => 'Clean dining area', 'description' => 'Clean and sanitize dining area'],
                    ['code' => 'COLLECT_TRASH', 'title' => 'Collect all trash', 'description' => 'Empty all trash bins and replace liners'],
                    ['code' => 'RESTOCK_SUPPLIES', 'title' => 'Restock supplies', 'description' => 'Restock all cleaning and hygiene supplies'],
                ],
            ],
            
            // Maintenance Templates
            [
                'role' => 'Maintenance',
                'title' => 'Daily Maintenance - Maintenance',
                'tasks' => [
                    ['code' => 'CHECK_ELECTRICAL', 'title' => 'Check electrical systems', 'description' => 'Inspect switches, outlets, and lighting'],
                    ['code' => 'CHECK_PLUMBING', 'title' => 'Check plumbing', 'description' => 'Inspect faucets, toilets, and pipes for leaks'],
                    ['code' => 'CHECK_HVAC', 'title' => 'Check HVAC systems', 'description' => 'Verify heating/cooling systems are working'],
                    ['code' => 'CHECK_FIRE_SAFETY', 'title' => 'Check fire safety equipment', 'description' => 'Inspect fire extinguishers and smoke detectors'],
                ],
            ],
        ];

        // Create templates for each tenant
        Tenant::all()->each(function (Tenant $tenant) use ($templates) {
            foreach ($templates as $template) {
                ChecklistTemplate::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'role' => $template['role'],
                        'title' => $template['title'],
                    ],
                    [
                        'tasks' => $template['tasks'],
                        'active' => true,
                        'created_by_user_id' => null, // System generated
                    ]
                );
            }
        });

        $this->command->info('✓ Checklist templates seeded successfully');
    }
}

