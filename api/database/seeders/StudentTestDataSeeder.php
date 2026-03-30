<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\Domain\OutPass\OutPass;
use App\Models\Notice;
use App\Models\Student;
use App\Models\User;
use App\Domain\Tickets\Models\Ticket;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StudentTestDataSeeder extends Seeder
{
    /**
     * Seed test data for student app testing.
     * 
     * Creates:
     * - Test student user (phone: 9876543210)
     * - Sample gate passes (pending, approved, rejected)
     * - Sample attendance records
     * - Sample tickets
     * - Sample public notices
     * 
     * Usage: php artisan db:seed --class=StudentTestDataSeeder
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding student test data...');

        // Find or create test student
        $studentUser = User::firstOrCreate(
            ['phone' => '9876543210'],
            [
                'name' => 'Test Student',
                'email' => 'teststudent@map.local',
                'password' => Hash::make('password'),
                'kind' => 'student',
            ]
        );

        // Ensure student record exists
        if (!$studentUser->student) {
            $student = Student::create([
                'user_id' => $studentUser->id,
                'map_student_id' => 'STU-' . str_pad((string) rand(1, 99999), 5, '0', STR_PAD_LEFT),
                'roll_no' => '2025001',
                'enrollment_no' => 'EN2025001',
                'program' => 'B.Tech',
                'branch' => 'Computer Science',
                'year_of_study' => 2,
            ]);
            $studentUser->refresh();
        } else {
            $student = $studentUser->student;
        }

        $this->command->info("✅ Test student created: {$studentUser->name} ({$studentUser->phone})");

        // Create sample gate passes
        $this->seedGatePasses($student);
        
        // Create sample attendance records
        $this->seedAttendance($student);
        
        // Create sample tickets
        $this->seedTickets($student, $studentUser);
        
        // Create sample notices
        $this->seedNotices($student);

        $this->command->info('');
        $this->command->info('🎉 Student test data seeding complete!');
        $this->command->info('');
        $this->command->info('📱 Test Student Credentials:');
        $this->command->info('   Phone: 9876543210');
        $this->command->info('   OTP: 123456 (any OTP works in development)');
        $this->command->info('');
    }

    private function seedGatePasses(Student $student): void
    {
        $this->command->info('📋 Creating sample gate passes...');

        $passes = [
            [
                'student_id' => $student->id,
                'reason' => 'Medical checkup at city hospital',
                'overnight' => false,
                'status' => 'approved',
                'requested_at' => now()->subDays(3),
                'decided_at' => now()->subDays(2),
                'valid_until' => now()->addDays(1),
                'approved_by_id' => $student->user_id, // Placeholder
            ],
            [
                'student_id' => $student->id,
                'reason' => 'Family emergency - need to visit home',
                'overnight' => true,
                'status' => 'pending',
                'requested_at' => now()->subDays(1),
                'valid_until' => now()->addDays(2),
            ],
            [
                'student_id' => $student->id,
                'reason' => 'Shopping for study materials',
                'overnight' => false,
                'status' => 'rejected',
                'requested_at' => now()->subDays(5),
                'decided_at' => now()->subDays(4),
                'valid_until' => now()->subDays(3),
                'rejection_reason' => 'Insufficient reason provided',
            ],
        ];

        foreach ($passes as $passData) {
            OutPass::create($passData);
        }

        $this->command->info('   ✅ Created 3 sample gate passes');
    }

    private function seedAttendance(Student $student): void
    {
        $this->command->info('📅 Creating sample attendance records...');

        $records = [];
        
        // Last 15 days of attendance
        for ($i = 14; $i >= 0; $i--) {
            $date = now()->subDays($i);
            
            // Random status (mostly present)
            $status = match(rand(1, 10)) {
                1 => 'absent',
                2 => 'leave',
                default => 'present',
            };
            
            $records[] = [
                'student_id' => $student->id,
                'status' => $status,
                'marked_at' => $date->setTime(8, 30),
                'marked_by' => $student->user_id, // Placeholder for system
                'note' => $status === 'leave' ? 'Medical leave' : null,
                'created_at' => $date,
                'updated_at' => $date,
            ];
        }

        AttendanceLog::insert($records);

        $this->command->info('   ✅ Created 15 attendance records');
    }

    private function seedTickets(Student $student, User $user): void
    {
        $this->command->info('🎫 Creating sample tickets...');

        $tickets = [
            [
                'reporter_student_id' => $student->id,
                'category' => 'maintenance',
                'priority' => 'high',
                'status' => 'open',
                'title' => 'Broken light in room',
                'description' => 'The ceiling light in my room is not working. Please fix it as soon as possible.',
                'created_by_user_id' => $user->id,
                'sla_due_at' => now()->addHours(4),
            ],
            [
                'reporter_student_id' => $student->id,
                'category' => 'technical',
                'priority' => 'medium',
                'status' => 'in_progress',
                'title' => 'WiFi connectivity issues',
                'description' => 'Internet connection is very slow in the hostel common area.',
                'created_by_user_id' => $user->id,
                'sla_due_at' => now()->addHours(8),
            ],
        ];

        foreach ($tickets as $ticketData) {
            Ticket::create($ticketData);
        }

        $this->command->info('   ✅ Created 2 sample tickets');
    }

    private function seedNotices(Student $student): void
    {
        $this->command->info('📢 Creating sample notices...');

        $notices = [
            [
                'campus_id' => $student->campus_id,
                'title' => 'Hostel Timing Change',
                'body' => 'Please note that hostel gate closing time has been changed to 10:00 PM from tomorrow.',
                'category' => 'urgent',
                'priority' => 'high',
                'status' => 'published',
                'audience' => 'all',
                'channels' => ['app', 'email'],
                'published_at' => now()->subDays(1),
                'expires_at' => now()->addDays(7),
            ],
            [
                'campus_id' => $student->campus_id,
                'title' => 'Sports Day Announcement',
                'body' => 'Annual sports day will be held on 15th November. Registration starts from 1st November.',
                'category' => 'events',
                'priority' => 'normal',
                'status' => 'published',
                'audience' => 'all',
                'channels' => ['app'],
                'published_at' => now()->subDays(3),
                'expires_at' => now()->addDays(15),
            ],
            [
                'campus_id' => $student->campus_id,
                'title' => 'Exam Schedule Released',
                'body' => 'Mid-term examination schedule has been uploaded. Check the notice board for details.',
                'category' => 'general',
                'priority' => 'normal',
                'status' => 'published',
                'audience' => 'all',
                'channels' => ['app', 'sms'],
                'published_at' => now()->subDays(5),
                'expires_at' => now()->addDays(30),
            ],
        ];

        foreach ($notices as $noticeData) {
            Notice::create($noticeData);
        }

        $this->command->info('   ✅ Created 3 sample notices');
    }
}

