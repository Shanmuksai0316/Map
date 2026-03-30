<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class HmsDemoPrintCreds extends Command
{
    protected $signature = 'hms:demo:print-creds';

    protected $description = 'Print demo login credentials';

    public function handle(): int
    {
        $this->info('🔑 MAP-HMS Demo Credentials');
        $this->newLine();

        // Get all demo users
        $users = User::whereHas('roles')->get();

        if ($users->isEmpty()) {
            $this->error('No demo users found. Run: php artisan hms:demo:reset --fresh');
            return 1;
        }

        $this->info('📱 Staff Login Credentials - Phone/OTP Based');
        $this->info('All staff login using phone + OTP (dev OTP: 123456)');
        $this->newLine();

        $staffData = [];
        foreach ($users as $user) {
            $role = $user->roles->first()?->name ?? 'No Role';
            $panel = $this->getPanelUrl($role);
            
            $staffData[] = [
                $role,
                $user->name,
                $user->phone,
                $panel,
            ];
        }

        $this->table(
            ['Role', 'Name', 'Phone', 'Panel URL'],
            $staffData
        );

        $this->newLine();
        $this->info('🎓 Student Login Credentials');
        $this->info('Sample student accounts (password: demo123):');
        
        // Get sample students
        $students = \App\Models\Student::take(5)->get();
        if ($students->isNotEmpty()) {
            $studentData = [];
            foreach ($students as $student) {
                $studentData[] = [
                    $student->student_uid,
                    $student->name,
                    $student->phone,
                    $student->hostel->name ?? 'N/A',
                    $student->currentRoom?->full_address ?? 'N/A',
                ];
            }
            
            $this->table(
                ['Student ID', 'Name', 'Phone', 'Hostel', 'Room'],
                $studentData
            );
        }

        $this->newLine();
        $this->info('🌐 Access URLs');
        $this->line('• Admin Panel: http://localhost:8000/admin (Super Admin, no tenant context)');
        $this->line('• Campus Manager Panel: http://demo-college.localhost:8000/campus-manager (DEMO-COLLEGE tenant)');
        $this->line('• Alternative: http://map-root.localhost:8000/campus-manager (MAP-ROOT tenant)');
        $this->line('• API Base URL: http://localhost:8000/api/v1');

        $this->newLine();
        $this->info('📱 Mobile Testing');
        $this->line('• Device UUIDs: DEMO-TABLET-01, DEMO-TABLET-02');
        $this->line('• All feature add-ons enabled (Security, Sports, Laundry)');

        return 0;
    }

    private function getPanelUrl(string $role): string
    {
        return match ($role) {
            'Super Admin' => 'http://localhost:8000/admin',
            'Campus Manager', 'Rector', 'Warden' => 'http://demo-college.localhost:8000/campus-manager',
            default => 'Mobile App',
        };
    }
}