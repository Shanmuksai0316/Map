<?php

namespace Database\Seeders;

use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Production Rooms Seeder
 * 
 * Creates rooms and beds for each hostel with Indian naming conventions.
 * Uses block codes (A, B, C) and floor codes (G, 1, 2, 3, etc.)
 */
class ProductionRoomsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('🛏️  Creating rooms and beds for each hostel...');

        $tenants = Tenant::all();
        $totalRooms = 0;
        $totalBeds = 0;

        foreach ($tenants as $tenant) {
            $hostels = Hostel::where('tenant_id', $tenant->id)->get();

            foreach ($hostels as $hostel) {
                $campus = $hostel->campus;
                $capacity = $hostel->settings['capacity'] ?? 100;
                
                // Calculate rooms needed (average 2.5 beds per room)
                $roomsNeeded = (int) ceil($capacity / 2.5);
                $floors = $this->getFloorCount($capacity);
                $roomsPerFloor = (int) ceil($roomsNeeded / $floors);
                
                $blockCodes = ['A', 'B', 'C'];
                $blockIndex = 0;

                for ($floor = 0; $floor < $floors; $floor++) {
                    $floorCode = $floor === 0 ? 'G' : (string) $floor;
                    $blockCode = $blockCodes[$blockIndex % count($blockCodes)];

                    for ($roomNum = 1; $roomNum <= $roomsPerFloor && $totalRooms < $roomsNeeded; $roomNum++) {
                        $roomNumber = str_pad((string) $roomNum, 3, '0', STR_PAD_LEFT);
                        
                        $existing = Room::where('tenant_id', $tenant->id)
                            ->where('hostel_id', $hostel->id)
                            ->where('block_code', $blockCode)
                            ->where('floor_code', $floorCode)
                            ->where('number', $roomNumber)
                            ->first();

                        if (!$existing) {
                            $roomCapacity = $this->getRoomCapacity($roomNum, $capacity);
                            
                            $room = Room::create([
                                'tenant_id' => $tenant->id,
                                'campus_id' => $campus->id,
                                'hostel_id' => $hostel->id,
                                'block_code' => $blockCode,
                                'floor_code' => $floorCode,
                                'number' => $roomNumber,
                                'capacity' => $roomCapacity,
                                'room_type' => $this->getRoomType($roomCapacity),
                                'is_active' => true,
                            ]);

                            // Create beds for this room
                            for ($bedNum = 1; $bedNum <= $roomCapacity; $bedNum++) {
                                $bedCode = "{$blockCode}-{$floorCode}-{$roomNumber}-{$bedNum}";
                                
                                RoomBed::create([
                                    'room_id' => $room->id,
                                    'hostel_id' => $hostel->id,
                                    'code' => $bedCode,
                                    'status' => 'available',
                                ]);
                                $totalBeds++;
                            }

                            $totalRooms++;
                        }
                    }

                    // Switch block every 2 floors
                    if ($floor > 0 && $floor % 2 === 0) {
                        $blockIndex++;
                    }
                }
            }
        }

        $this->command->info("\n✅ Production rooms seeding complete!");
        $this->command->info("Total rooms created: {$totalRooms}");
        $this->command->info("Total beds created: {$totalBeds}");
    }

    /**
     * Get number of floors based on capacity
     */
    private function getFloorCount(int $capacity): int
    {
        if ($capacity >= 120) {
            return 5;
        } elseif ($capacity >= 80) {
            return 4;
        } elseif ($capacity >= 50) {
            return 3;
        }
        
        return 3;
    }

    /**
     * Get room capacity (beds) with realistic distribution
     */
    private function getRoomCapacity(int $roomNum, int $hostelCapacity): int
    {
        // First 2 rooms on each floor are singles (VIP/staff)
        if ($roomNum <= 2) {
            return 1;
        }
        
        // Next rooms are mostly doubles
        if ($roomNum <= 8) {
            return 2;
        }
        
        // Some triples for larger hostels
        if ($roomNum <= 12 && $hostelCapacity >= 100) {
            return rand(2, 3) === 3 ? 3 : 2;
        }
        
        // Rest are doubles (most common)
        return 2;
    }

    /**
     * Get room type based on capacity
     */
    private function getRoomType(int $capacity): string
    {
        return match($capacity) {
            1 => 'single',
            2 => 'double',
            3 => 'triple',
            4 => 'quad',
            default => 'double',
        };
    }
}

