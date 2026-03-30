<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\Room;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoRoomsSeeder extends Seeder
{
    /**
     * Seed demo rooms for each hostel.
     * Creates realistic room distributions across floors.
     */
    public function run(): void
    {
        $tenants = Tenant::all();
        $totalCreated = 0;

        foreach ($tenants as $tenant) {
            // Switch to tenant database context
            $tenant->run(function() use ($tenant, &$totalCreated) {
                $hostels = Hostel::all();

                foreach ($hostels as $hostel) {
                    $roomsCreated = 0;
                    $floors = $this->getFloorCount($hostel);
                    $roomsPerFloor = $this->getRoomsPerFloor($hostel);

                    for ($floor = 1; $floor <= $floors; $floor++) {
                        for ($roomNum = 1; $roomNum <= $roomsPerFloor; $roomNum++) {
                            $roomNumber = ($floor * 100) + $roomNum; // e.g., 101, 102, 201, 202
                            
                            $existing = Room::where('hostel_id', $hostel->id)
                                ->where('room_number', (string)$roomNumber)
                                ->first();

                            if (!$existing) {
                                $capacity = $this->getRoomCapacity($hostel, $roomNum);
                                
                                Room::create([
                                    'hostel_id' => $hostel->id,
                                    'floor' => $floor,
                                    'room_number' => (string)$roomNumber,
                                    'capacity' => $capacity,
                                    'status' => 'available',
                                    'amenities' => $this->getRoomAmenities($floor, $roomNum),
                                ]);

                                $roomsCreated++;
                                $totalCreated++;
                            }
                        }
                    }

                    if ($roomsCreated > 0) {
                        $this->command->info("✅ Created {$roomsCreated} rooms for {$hostel->name}");
                    } else {
                        $this->command->warn("⚠️  All rooms for {$hostel->name} already exist, skipping...");
                    }
                }
            });
        }

        $this->command->info("\n✅ Demo rooms seeding complete!");
        $this->command->info("Total rooms created: {$totalCreated}");
    }

    /**
     * Get number of floors based on hostel capacity
     */
    private function getFloorCount(Hostel $hostel): int
    {
        if ($hostel->capacity >= 100) {
            return 5;
        } elseif ($hostel->capacity >= 80) {
            return 4;
        } elseif ($hostel->capacity >= 50) {
            return 3;
        }
        
        return 3;
    }

    /**
     * Get rooms per floor based on hostel capacity
     */
    private function getRoomsPerFloor(Hostel $hostel): int
    {
        $floors = $this->getFloorCount($hostel);
        $averageCapacityPerRoom = 2.5; // Average between single, double, triple rooms
        
        return (int) ceil($hostel->capacity / ($floors * $averageCapacityPerRoom));
    }

    /**
     * Get room capacity (beds) with realistic distribution
     */
    private function getRoomCapacity(Hostel $hostel, int $roomNum): int
    {
        // First 2 rooms on each floor are singles (VIP/staff)
        if ($roomNum <= 2) {
            return 1;
        }
        
        // Next rooms are mostly doubles
        if ($roomNum <= 6) {
            return 2;
        }
        
        // Some triples
        if ($roomNum <= 10 && $hostel->capacity >= 80) {
            return 3;
        }
        
        // Rest are doubles (most common)
        return 2;
    }

    /**
     * Get room amenities based on floor and room number
     */
    private function getRoomAmenities(int $floor, int $roomNum): array
    {
        $baseAmenities = [
            'bed' => true,
            'study_table' => true,
            'chair' => true,
            'wardrobe' => true,
            'fan' => true,
        ];

        // Upper floors and premium rooms get AC
        if ($floor >= 3 || $roomNum <= 2) {
            $baseAmenities['ac'] = true;
        }

        // All rooms have attached bathroom
        $baseAmenities['attached_bathroom'] = true;

        // Premium rooms get additional amenities
        if ($roomNum <= 2) {
            $baseAmenities['geyser'] = true;
            $baseAmenities['tv'] = false; // Can be enabled later
        }

        return $baseAmenities;
    }
}

