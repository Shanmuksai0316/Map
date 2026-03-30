<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Production Payments Seeder
 * 
 * Creates payment records for students (hostel fees).
 * Updates student payment fields.
 */
class ProductionPaymentsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('💰 Creating payment records for each tenant...');

        $tenants = Tenant::all();
        $totalUpdated = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating payment records for {$tenant->name}...");
            
            $students = Student::where('tenant_id', $tenant->id)->get();
            
            if ($students->isEmpty()) {
                $this->command->warn("  ⚠️  No students found for {$tenant->name}, skipping...");
                continue;
            }

            // Payment status distribution: 70% paid, 20% pending, 10% overdue
            $statusDistribution = ['paid' => 0.70, 'pending' => 0.20, 'overdue' => 0.10];
            $paymentModes = ['cash', 'upi', 'card', 'bank_transfer', 'cheque'];

            foreach ($students as $student) {
                $rand = rand(1, 100);
                $status = 'paid';
                $cumulative = 0;
                foreach ($statusDistribution as $stat => $prob) {
                    $cumulative += $prob * 100;
                    if ($rand <= $cumulative) {
                        $status = $stat;
                        break;
                    }
                }

                $amount = rand(50000, 150000); // ₹50,000 to ₹1,50,000
                $paymentDate = $status === 'paid' 
                    ? now()->subDays(rand(0, 90)) 
                    : ($status === 'overdue' ? now()->subDays(rand(91, 180)) : null);

                $student->update([
                    'hostel_fee_paid' => $status === 'paid',
                    'payment_mode' => $status === 'paid' ? $paymentModes[array_rand($paymentModes)] : null,
                    'payment_amount' => $status === 'paid' ? $amount : null,
                    'payment_date' => $paymentDate,
                    'payment_reference' => $status === 'paid' ? 'PAY' . strtoupper($tenant->code) . date('Ymd') . rand(1000, 9999) : null,
                    'payment_notes' => $status === 'paid' ? 'Hostel fee payment' : ($status === 'overdue' ? 'Payment overdue' : 'Payment pending'),
                ]);

                $totalUpdated++;
            }

            $this->command->info("  ✅ Updated payment records for {$totalUpdated} students in {$tenant->name}");
        }

        $this->command->info("\n✅ Production payments seeding complete!");
        $this->command->info("Total payment records updated: {$totalUpdated}");
    }
}

