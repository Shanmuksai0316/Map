<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VisitorLog;
use App\Models\VisitorPreRegistration;
use Illuminate\Database\Seeder;

/**
 * Production Visitors Seeder
 * 
 * Creates 30-50 visitor pre-registrations and logs per tenant.
 */
class ProductionVisitorsSeeder extends Seeder
{
    private array $indianVisitorNames = [
        'Rajesh Kumar', 'Priya Sharma', 'Suresh Patel', 'Anita Reddy', 'Manoj Singh',
        'Kavita Verma', 'Ramesh Iyer', 'Sunita Nair', 'Vikram Gupta', 'Divya Menon',
        'Amit Joshi', 'Neha Agarwal', 'Deepak Malhotra', 'Pooja Kapoor', 'Ravi Saxena',
    ];

    private array $visitorPurposes = [
        'Family visit',
        'Parent meeting',
        'Friend visit',
        'Relative visit',
        'Official work',
        'Medical emergency',
        'Festival celebration',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👥 Creating visitors for each tenant...');

        $tenants = Tenant::all();
        $totalPreRegs = 0;
        $totalLogs = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating visitors for {$tenant->name}...");
            
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->limit(30)->get();
            
            if ($hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            // Get guard for approvals
            $guard = User::where('tenant_id', $tenant->id)
                ->whereHas('roles', function ($q) {
                    $q->where('name', 'Guard');
                })
                ->first();

            // Create 30-50 pre-registrations
            $preRegCount = rand(30, 50);
            $statusDistribution = ['approved' => 0.60, 'denied' => 0.15, 'pending' => 0.25];

            for ($i = 0; $i < $preRegCount; $i++) {
                $student = $students->random();
                $hostel = $hostels->where('id', $student->hostel_id)->first() ?? $hostels->random();
                $visitorName = $this->indianVisitorNames[array_rand($this->indianVisitorNames)];
                $visitorPhone = '+91' . rand(9000000000, 9999999999);
                $purpose = $this->visitorPurposes[array_rand($this->visitorPurposes)];
                $visitingDate = now()->addDays(rand(-7, 7));

                // Determine status
                $rand = rand(1, 100);
                $status = 'pending';
                $cumulative = 0;
                foreach ($statusDistribution as $stat => $prob) {
                    $cumulative += $prob * 100;
                    if ($rand <= $cumulative) {
                        $status = $stat;
                        break;
                    }
                }

                $preReg = VisitorPreRegistration::create([
                    'hostel_id' => $hostel->id,
                    'student_id' => $student->id,
                    'guest_name' => $visitorName,
                    'guest_phone' => $visitorPhone,
                    'person_to_meet' => $student->user->name ?? 'Student',
                    'visiting_date' => $visitingDate,
                    'purpose' => $purpose,
                    'status' => $status,
                    'approved_by' => $status === 'approved' ? $guard?->id : null,
                ]);

                // Create visitor log for approved/denied
                if ($status !== 'pending') {
                    VisitorLog::create([
                        'hostel_id' => $hostel->id,
                        'pre_registration_id' => $preReg->id,
                        'guest_name' => $visitorName,
                        'guest_phone' => $visitorPhone,
                        'decision' => $status,
                        'reason' => $status === 'approved' ? 'Approved by guard' : 'Not allowed',
                        'occurred_at' => $visitingDate->copy()->setTime(rand(10, 18), rand(0, 59)),
                    ]);
                    $totalLogs++;
                }

                $totalPreRegs++;
            }

            $this->command->info("  ✅ Created {$preRegCount} visitor pre-registrations for {$tenant->name}");
        }

        $this->command->info("\n✅ Production visitors seeding complete!");
        $this->command->info("Total pre-registrations created: {$totalPreRegs}");
        $this->command->info("Total visitor logs created: {$totalLogs}");
    }
}

