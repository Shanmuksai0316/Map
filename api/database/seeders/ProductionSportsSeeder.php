<?php

namespace Database\Seeders;

use App\Enums\SportsEventStatus;
use App\Models\Campus;
use App\Models\FacilityBooking;
use App\Models\Hostel;
use App\Models\SportsEnrollment;
use App\Models\SportsEvent;
use App\Models\SportsFacility;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Production Sports Seeder
 * 
 * Creates sports facilities, events, and bookings.
 */
class ProductionSportsSeeder extends Seeder
{
    private array $facilityTypes = [
        ['name' => 'Badminton Court', 'type' => 'badminton', 'capacity' => 4],
        ['name' => 'Cricket Ground', 'type' => 'cricket', 'capacity' => 22],
        ['name' => 'Basketball Court', 'type' => 'basketball', 'capacity' => 10],
        ['name' => 'Gym', 'type' => 'gym', 'capacity' => 20],
        ['name' => 'Tennis Court', 'type' => 'tennis', 'capacity' => 4],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('⚽ Creating sports data for each tenant...');

        $tenants = Tenant::where('addon_sports', true)->get();
        $totalFacilities = 0;
        $totalEvents = 0;
        $totalBookings = 0;

        foreach ($tenants as $tenant) {
            $this->command->info("\n📋 Creating sports data for {$tenant->name}...");
            
            $campuses = Campus::where('tenant_id', $tenant->id)->get();
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();
            $students = Student::where('tenant_id', $tenant->id)->limit(50)->get();
            
            if ($campuses->isEmpty() || $hostels->isEmpty() || $students->isEmpty()) {
                $this->command->warn("  ⚠️  Insufficient data for {$tenant->name}, skipping...");
                continue;
            }

            $campus = $campuses->first();
            $hostel = $hostels->first();

            // Create facilities
            foreach ($this->facilityTypes as $facilityData) {
                $facility = SportsFacility::create([
                    'hostel_id' => $hostel->id,
                    'name' => $facilityData['name'],
                    'type' => $facilityData['type'],
                    'open_time' => now()->setTime(6, 0),
                    'close_time' => now()->setTime(22, 0),
                    'capacity' => $facilityData['capacity'],
                    'is_active' => true,
                ]);

                $totalFacilities++;

                // Create bookings for this facility
                $bookingCount = rand(10, 20);
                for ($i = 0; $i < $bookingCount; $i++) {
                    $student = $students->random();
                    $startAt = now()->addDays(rand(-7, 7))->setTime(rand(6, 20), 0);
                    $endAt = $startAt->copy()->addHours(rand(1, 2));
                    $status = $startAt->isPast() ? (rand(1, 10) > 2 ? 'completed' : 'cancelled') : 'active';

                    FacilityBooking::create([
                        'facility_id' => $facility->id,
                        'student_id' => $student->id,
                        'start_at' => $startAt,
                        'end_at' => $endAt,
                        'status' => $status,
                        'purpose' => 'Recreation',
                    ]);

                    $totalBookings++;
                }
            }

            // Create sports events
            $eventCount = rand(10, 20);
            $sports = ['badminton', 'cricket', 'basketball', 'tennis', 'volleyball'];
            $statusDistribution = ['scheduled' => 0.40, 'in_progress' => 0.10, 'completed' => 0.50];

            for ($i = 0; $i < $eventCount; $i++) {
                $rand = rand(1, 100);
                $status = 'scheduled';
                $cumulative = 0;
                foreach ($statusDistribution as $stat => $prob) {
                    $cumulative += $prob * 100;
                    if ($rand <= $cumulative) {
                        $status = $stat;
                        break;
                    }
                }

                $scheduledAt = now()->addDays(rand(-7, 14))->setTime(rand(8, 18), 0);
                $endTime = $scheduledAt->copy()->addHours(rand(1, 3));
                $sport = $sports[array_rand($sports)];

                $event = SportsEvent::create([
                    'campus_id' => $campus->id,
                    'hostel_id' => $hostel->id,
                    'sport' => $sport,
                    'name' => ucfirst($sport) . ' Tournament',
                    'description' => 'Inter-hostel ' . $sport . ' tournament',
                    'scheduled_at' => $scheduledAt,
                    'end_time' => $endTime,
                    'venue' => 'Sports Complex',
                    'status' => SportsEventStatus::from($status),
                    'capacity' => rand(10, 30),
                    'registration_deadline' => $scheduledAt->copy()->subDays(2),
                ]);

                $totalEvents++;

                // Create enrollments
                $enrollmentCount = rand(5, min($event->capacity, 15));
                $enrolledStudents = $students->random($enrollmentCount);
                
                foreach ($enrolledStudents as $student) {
                    SportsEnrollment::create([
                        'sports_event_id' => $event->id,
                        'student_id' => $student->id,
                        'status' => rand(1, 10) > 2 ? 'registered' : 'waitlisted',
                        'enrolled_at' => now()->subDays(rand(0, 5)),
                    ]);
                }
            }

            $this->command->info("  ✅ Created sports data for {$tenant->name}");
        }

        $this->command->info("\n✅ Production sports seeding complete!");
        $this->command->info("Total facilities created: {$totalFacilities}");
        $this->command->info("Total events created: {$totalEvents}");
        $this->command->info("Total bookings created: {$totalBookings}");
    }
}

