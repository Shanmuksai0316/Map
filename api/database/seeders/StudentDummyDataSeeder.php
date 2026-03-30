<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Notice;
use Illuminate\Support\Facades\DB;

class StudentDummyDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating dummy data for student app testing...');

        // Get the test student
        $user = User::where('phone', '+919900000999')->first();
        
        if (!$user) {
            $this->command->error('Test student not found! Run MobileTestDataSeeder first.');
            return;
        }

        $student = $user->student;
        
        if (!$student) {
            $this->command->error('Student record not found!');
            return;
        }

        $this->command->info("Found student: {$user->name} ({$student->map_student_id})");

        // Create out-passes with different statuses
        $outpasses = [
            [
                'reason' => 'normal',
                'overnight' => false,
                'status' => 'approved',
                'note' => 'Going to city center for shopping',
                'requested_at' => now()->subDays(3),
                'valid_until' => now()->addHours(6),
                'decided_at' => now()->subDays(3)->addHour(),
            ],
            [
                'reason' => 'leave',
                'overnight' => true,
                'status' => 'pending',
                'note' => 'Family function at home',
                'requested_at' => now()->subHours(2),
                'valid_until' => now()->addDays(2),
            ],
            [
                'reason' => 'sick',
                'overnight' => false,
                'status' => 'approved',
                'note' => 'Doctor appointment',
                'requested_at' => now()->subDays(7),
                'valid_until' => now()->subDays(7)->addHours(4),
                'decided_at' => now()->subDays(7)->addMinutes(30),
            ],
            [
                'reason' => 'normal',
                'overnight' => false,
                'status' => 'declined',
                'note' => 'Meeting friends',
                'requested_at' => now()->subDays(10),
                'valid_until' => now()->subDays(10)->addHours(3),
                'decided_at' => now()->subDays(10)->addHour(),
            ],
        ];

        foreach ($outpasses as $passData) {
            OutPass::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'requested_at' => $passData['requested_at'],
                ],
                array_merge($passData, [
                    'tenant_id' => $user->tenant_id,
                    'hostel_id' => $student->hostel_id,
                ])
            );
        }

        $this->command->info('✓ Created 4 out-passes (approved, pending, rejected)');

        // Add more notices
        $additionalNotices = [
            [
                'title' => 'Exam Schedule Released',
                'body' => 'Mid-term examination schedule has been released. Check the notice board for details.',
                'audience' => 'all_students',
                'publish_at' => now()->subHours(12),
                'status' => 'published',
            ],
            [
                'title' => 'Sports Day Announcement',
                'body' => 'Annual sports day will be held next month. Registrations open from tomorrow.',
                'audience' => 'all_students',
                'publish_at' => now()->subHours(6),
                'status' => 'published',
            ],
        ];

        foreach ($additionalNotices as $noticeData) {
            Notice::firstOrCreate(
                [
                    'tenant_id' => $user->tenant_id,
                    'title' => $noticeData['title'],
                ],
                $noticeData
            );
        }

        $this->command->info('✓ Added more notices');

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('Dummy Data Created Successfully!');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('Test Student: ' . $user->name);
        $this->command->info('Phone: ' . $user->phone);
        $this->command->info('Out-passes: 4 (1 approved, 1 pending, 1 rejected, 1 expired)');
        $this->command->info('Notices: 5 total');
        $this->command->info('');
        $this->command->info('You can now test the complete student app!');
        $this->command->info('');
    }
}

