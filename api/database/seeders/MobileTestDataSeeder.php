<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Campus;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Notice;
use Illuminate\Support\Facades\Hash;

class MobileTestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating mobile test data...');

        // Get or create tenant
        $tenant = Tenant::first();
        if (!$tenant) {
            $tenant = Tenant::create([
                'name' => 'Demo College',
                'status' => 'active',
            ]);
        }

        // Get or create campus
        $campus = Campus::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'MC'],
            ['name' => 'Main Campus']
        );

        // Get or create hostel
        $hostel = Hostel::firstOrCreate(
            ['tenant_id' => $tenant->id, 'code' => 'DH01'],
            [
                'campus_id' => $campus->id,
                'name' => 'Demo Hostel',
                'gender_mode' => 'male',
                'curfew_time' => '22:00:00',
                'overnight_enabled' => true,
                'visiting_start' => '09:00:00',
                'visiting_end' => '18:00:00',
            ]
        );

        // Create test student user
        $user = User::updateOrCreate(
            ['phone' => '+919900000999'],
            [
                'name' => 'Test Student',
                'email' => 'teststudent@example.com',
                'password' => Hash::make('password123'),
                'kind' => 'Student',
                'tenant_id' => $tenant->id,
            ]
        );

        // Assign Student role
        if (!$user->hasRole('Student')) {
            $user->assignRole('Student');
        }

        // Create student record
        $student = Student::updateOrCreate(
            ['user_id' => $user->id],
            [
                'tenant_id' => $tenant->id,
                'hostel_id' => $hostel->id,
                'map_student_id' => 'STU001',
                'student_uid' => 'STU001',
            ]
        );

        $this->command->info("✓ Student created: {$user->phone}");

        // Create sample notices
        $notices = [
            [
                'title' => 'Welcome to New Semester',
                'body' => 'Welcome back students! The new semester starts on Monday. Please ensure all dues are cleared.',
                'audience' => 'all_students',
                'publish_at' => now()->subDays(5),
            ],
            [
                'title' => 'Hostel Maintenance Notice',
                'body' => 'Hostel maintenance will be conducted this weekend. Please cooperate with the staff.',
                'audience' => 'all_students',
                'publish_at' => now()->subDays(3),
            ],
            [
                'title' => 'Library Extended Hours',
                'body' => 'The library will be open until 11 PM during exam week starting next Monday.',
                'audience' => 'all_students',
                'publish_at' => now()->subDay(),
            ],
        ];

        foreach ($notices as $noticeData) {
            Notice::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'title' => $noticeData['title'],
                ],
                array_merge($noticeData, [
                    'status' => 'published',
                ])
            );
        }

        $this->command->info('✓ Sample notices created');
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('Mobile Test Data Created Successfully!');
        $this->command->info('═══════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('Test Credentials:');
        $this->command->info("  Phone: {$user->phone}");
        $this->command->info('  Name: '.$user->name);
        $this->command->info('  Student ID: '.$student->map_student_id);
        $this->command->info('  Roll No: '.$student->roll_no);
        $this->command->info('');
        $this->command->info('Use OTP login in the mobile app with this phone number.');
        $this->command->info('OTP will be logged to laravel.log in development mode.');
        $this->command->info('');
    }
}

