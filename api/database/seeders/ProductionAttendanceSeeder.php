<?php

namespace Database\Seeders;

use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Attendance Seeder
 * 
 * Creates 30 days of attendance sessions with marks.
 */
class ProductionAttendanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📅 Creating attendance sessions for each tenant...');

        $tenants = Tenant::all();
        $totalSessions = 0;
        $totalMarks = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating attendance for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            
            if ($hostels->isEmpty()) {
                $this->command->warn("  ⚠️  No hostels found for {$tenant->name}, skipping...");
                continue;
            }

            // Get warden for marking
            $warden = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'Warden');
                })
                ->first();

            // Create 30 days of sessions
            for ($day = 0; $day < 30; $day++) {
                $sessionDate = now()->subDays($day);
                
                foreach ($hostels as $hostel) {
                    // Morning session
                    $morningSession = AttendanceSession::create([
                        'tenant_id' => $tenant->id,
                        'hostel_id' => $hostel->id,
                        'campus_id' => $hostel->campus_id,
                        'name' => 'Morning Check - ' . $sessionDate->format('d M Y'),
                        'kind' => 'morning',
                        'scheduled_at' => $sessionDate->copy()->setTime(8, 0),
                        'status' => $day === 0 ? 'open' : 'closed',
                    ]);

                    // Night session
                    $nightSession = AttendanceSession::create([
                        'tenant_id' => $tenant->id,
                        'hostel_id' => $hostel->id,
                        'campus_id' => $hostel->campus_id,
                        'name' => 'Night Check - ' . $sessionDate->format('d M Y'),
                        'kind' => 'night_check',
                        'scheduled_at' => $sessionDate->copy()->setTime(22, 30),
                        'status' => $day === 0 ? 'open' : 'closed',
                    ]);

                    // Get students for this hostel
                    $students = Student::where('tenant_id', $tenant->id)
                        ->where('hostel_id', $hostel->id)
                        ->get();

                    // Mark attendance for students
                    foreach ($students as $student) {
                        // Morning attendance: 80% present, 15% absent, 5% leave
                        $morningStatus = $this->getRandomAttendanceStatus(0.80, 0.15, 0.05);
                        AttendanceLog::create([
                            'tenant_id' => $tenant->id,
                            'hostel_id' => $hostel->id,
                            'attendance_session_id' => $morningSession->id,
                            'session_id' => $morningSession->id,
                            'attendance_date' => $sessionDate->toDateString(),
                            'student_id' => $student->id,
                            'status' => $morningStatus,
                            'marked_at' => $sessionDate->copy()->setTime(8, rand(0, 30)),
                            'marked_by' => $warden?->id ?? $student->user_id,
                        ]);
                        $totalMarks++;

                        // Night attendance: 85% present, 10% absent, 5% leave
                        $nightStatus = $this->getRandomAttendanceStatus(0.85, 0.10, 0.05);
                        AttendanceLog::create([
                            'tenant_id' => $tenant->id,
                            'hostel_id' => $hostel->id,
                            'attendance_session_id' => $nightSession->id,
                            'session_id' => $nightSession->id,
                            'attendance_date' => $sessionDate->toDateString(),
                            'student_id' => $student->id,
                            'status' => $nightStatus,
                            'marked_at' => $sessionDate->copy()->setTime(22, rand(30, 59)),
                            'marked_by' => $warden?->id ?? $student->user_id,
                        ]);
                        $totalMarks++;
                    }

                    $totalSessions += 2;
                }
            }

            $this->command->info("  ✅ Created attendance sessions for {$tenant->name}");
        }

        $this->command->info("\n✅ Production attendance seeding complete!");
        $this->command->info("Total sessions created: {$totalSessions}");
        $this->command->info("Total marks created: {$totalMarks}");
    }

    /**
     * Get random attendance status based on probabilities
     */
    private function getRandomAttendanceStatus(float $presentProb, float $absentProb, float $leaveProb): string
    {
        $rand = rand(1, 100);
        
        if ($rand <= $presentProb * 100) {
            return 'present';
        } elseif ($rand <= ($presentProb + $absentProb) * 100) {
            return 'absent';
        } else {
            return 'leave';
        }
    }
}

