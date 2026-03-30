<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\Student;
use App\Models\Domain\OutPass\OutPass;
use App\Domain\Tickets\Models\Ticket;
use App\Models\Notice;
use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class StaffTestDataSeeder extends Seeder
{
    /**
     * Seed test data for all 8 staff roles.
     * 
     * Creates:
     * - Test users for each staff role
     * - Sample campus, hostel, rooms
     * - Sample students
     * - Sample gate passes (outpasses)
     * - Sample tickets
     * - Sample attendance sessions
     * - Sample notices
     * 
     * Usage: php artisan db:seed --class=StaffTestDataSeeder
     */
    public function run(): void
    {
        $this->command->info('🌱 Seeding staff test data...');

        // Create or get test campus
        $campus = Campus::firstOrCreate(
            ['code' => 'TEST-CAMPUS'],
            ['name' => 'Test Campus', 'address' => '123 Test Street']
        );

        // Create test hostel
        $hostel = Hostel::firstOrCreate(
            ['campus_id' => $campus->id, 'name' => 'Test Hostel A'],
            ['code' => 'TEST-HOSTEL-A', 'address' => 'Test Campus', 'capacity' => 100]
        );

        $this->command->info("✅ Test campus and hostel created");

        // Create rooms
        $rooms = $this->seedRooms($hostel);
        
        // Create staff users for all 8 roles
        $staffUsers = $this->seedStaffUsers($hostel);
        
        // Create test students
        $students = $this->seedTestStudents($hostel, $rooms);
        
        // Create gate passes (outpasses)
        $this->seedGatePasses($students);
        
        // Create tickets
        $this->seedTickets($students, $hostel);
        
        // Create attendance sessions
        $this->seedAttendanceSessions($hostel, $students);
        
        // Create notices
        $this->seedNotices($campus, $hostel);

        $this->command->info('');
        $this->command->info('🎉 Staff test data seeding complete!');
        $this->command->info('');
        $this->command->info('📱 Test Staff Credentials:');
        foreach ($staffUsers as $role => $user) {
            $this->command->info("   {$role}: {$user->phone} / password");
        }
        $this->command->info('');
        $this->command->info('   Test Student: 9876543210 / password');
    }

    private function seedRooms(Hostel $hostel): array
    {
        $this->command->info('🏠 Creating test rooms...');

        $rooms = [];
        for ($i = 1; $i <= 5; $i++) {
            $rooms[] = Room::firstOrCreate(
                ['hostel_id' => $hostel->id, 'room_no' => "R{$i}01"],
                [
                    'floor' => 1,
                    'capacity' => 4,
                    'type' => 'shared',
                    'status' => 'active',
                ]
            );
        }

        $this->command->info("   ✅ Created 5 test rooms");
        return $rooms;
    }

    private function seedStaffUsers(Hostel $hostel): array
    {
        $this->command->info('👥 Creating staff users for all 8 roles...');

        $staffUsers = [];
        $roles = [
            'Campus Manager' => '9001000001',
            'Rector' => '9001000002',
            'Warden' => '9001000003',
            'Guard' => '9001000004',
            'HK Supervisor' => '9001000005',
            'RM Supervisor' => '9001000006',
            'Laundry Manager' => '9001000007',
            'Sports Manager' => '9001000008',
        ];

        foreach ($roles as $role => $phone) {
            $user = User::firstOrCreate(
                ['phone' => $phone],
                [
                    'name' => "Test {$role}",
                    'email' => strtolower(str_replace(' ', '', $role)) . '@map.local',
                    'password' => Hash::make('password'),
                    'kind' => 'staff',
                ]
            );

            // Assign role
            if (!$user->hasRole($role)) {
                $user->assignRole($role);
            }

            // Create staff assignment for hostel-specific roles
            if (in_array($role, ['Warden', 'Guard', 'HK Supervisor', 'RM Supervisor'])) {
                $user->staffAssignments()->firstOrCreate([
                    'hostel_id' => $hostel->id,
                    'role_type' => strtolower(str_replace(' ', '_', $role)),
                ]);
            }

            $staffUsers[$role] = $user;
        }

        $this->command->info("   ✅ Created 8 staff users with roles");
        return $staffUsers;
    }

    private function seedTestStudents(Hostel $hostel, array $rooms): array
    {
        $this->command->info('👨‍🎓 Creating test students...');

        $students = [];
        
        // Create 10 test students
        for ($i = 1; $i <= 10; $i++) {
            $user = User::firstOrCreate(
                ['phone' => "987654321{$i}"],
                [
                    'name' => "Test Student {$i}",
                    'email' => "student{$i}@map.local",
                    'password' => Hash::make('password'),
                    'kind' => 'student',
                ]
            );

            $student = Student::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'map_student_id' => 'STU-' . str_pad((string)($i + 1000), 5, '0', STR_PAD_LEFT),
                    'roll_no' => "2025{$i}",
                    'enrollment_no' => "EN2025{$i}",
                    'program' => 'B.Tech',
                    'branch' => 'Computer Science',
                    'year_of_study' => 2,
                    'hostel_id' => $hostel->id,
                    'campus_id' => $hostel->campus_id,
                ]
            );

            $students[] = $student;
        }

        $this->command->info("   ✅ Created 10 test students");
        return $students;
    }

    private function seedGatePasses(array $students): void
    {
        $this->command->info('📋 Creating sample gate passes (outpasses)...');

        $statuses = ['pending', 'approved', 'rejected', 'active', 'completed'];
        
        foreach (array_slice($students, 0, 5) as $index => $student) {
            OutPass::create([
                'student_id' => $student->id,
                'reason' => match($index % 3) {
                    0 => 'Medical checkup at hospital',
                    1 => 'Family emergency visit',
                    2 => 'Shopping for study materials',
                },
                'overnight' => $index % 2 === 0,
                'status' => $statuses[$index % count($statuses)],
                'requested_at' => now()->subDays(rand(1, 5)),
                'valid_until' => now()->addDays(rand(1, 3)),
            ]);
        }

        $this->command->info('   ✅ Created 5 sample gate passes');
    }

    private function seedTickets(array $students, Hostel $hostel): void
    {
        $this->command->info('🎫 Creating sample tickets...');

        $categories = ['maintenance', 'housekeeping', 'room_maintenance', 'electrical', 'plumbing', 'technical'];
        $priorities = ['low', 'medium', 'high', 'urgent'];
        $statuses = ['open', 'in_progress', 'resolved', 'closed'];

        foreach (array_slice($students, 0, 6) as $index => $student) {
            Ticket::create([
                'hostel_id' => $hostel->id,
                'reporter_student_id' => $student->id,
                'category' => $categories[$index % count($categories)],
                'priority' => $priorities[$index % count($priorities)],
                'status' => $statuses[$index % count($statuses)],
                'title' => "Test Ticket #{$index + 1}",
                'description' => "This is a test ticket for {$categories[$index % count($categories)]} category",
                'created_by_user_id' => $student->user_id,
                'sla_due_at' => now()->addHours(4),
            ]);
        }

        $this->command->info('   ✅ Created 6 sample tickets');
    }

    private function seedAttendanceSessions(Hostel $hostel, array $students): void
    {
        $this->command->info('📅 Creating attendance sessions...');

        // Create today's session
        $today = now();

        $todaySession = AttendanceSession::create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
            'campus_id' => $hostel->campus_id,
            'name' => 'Morning Attendance',
            'kind' => 'morning',
            'session_date' => $today->toDateString(),
            'session_time' => '08:00:00',
            'scheduled_at' => $today->copy()->setTime(8, 0),
            'status' => 'open',
        ]);

        // Mark attendance for some students
        foreach (array_slice($students, 0, 8) as $student) {
            AttendanceLog::create([
                'tenant_id' => $hostel->tenant_id,
                'hostel_id' => $hostel->id,
                'attendance_session_id' => $todaySession->id,
                'attendance_date' => $today->toDateString(),
                'student_id' => $student->id,
                'status' => rand(1, 10) > 2 ? 'present' : 'absent',
                'marked_at' => $today->copy()->setTime(8, 15),
                'marked_by' => $student->user_id,
            ]);
        }

        $this->command->info('   ✅ Created attendance session with 8 records');
    }

    private function seedNotices(Campus $campus, Hostel $hostel): void
    {
        $this->command->info('📢 Creating staff/admin notices...');

        $notices = [
            [
                'campus_id' => $campus->id,
                'hostel_id' => $hostel->id,
                'title' => 'Staff Meeting Tomorrow',
                'body' => 'All staff members are requested to attend the monthly meeting at 10 AM.',
                'category' => 'general',
                'priority' => 'high',
                'status' => 'published',
                'audience' => 'staff',
                'channels' => ['app', 'email'],
                'published_at' => now()->subDay(),
            ],
            [
                'campus_id' => $campus->id,
                'title' => 'New Security Protocols',
                'body' => 'Updated security protocols are now in effect. Please review the guidelines.',
                'category' => 'urgent',
                'priority' => 'high',
                'status' => 'published',
                'audience' => 'all',
                'channels' => ['app'],
                'published_at' => now()->subDays(2),
            ],
        ];

        foreach ($notices as $noticeData) {
            Notice::create($noticeData);
        }

        $this->command->info('   ✅ Created 2 staff notices');
    }
}

