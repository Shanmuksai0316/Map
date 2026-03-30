<?php

namespace App\Services;

use App\Models\Floor;
use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomBed;
use Illuminate\Support\Facades\DB;

class RoomConfigurationService
{
    /**
     * Bed labels for room capacity.
     */
    private const BED_LABELS = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H'];

    /**
     * Room type mapping by capacity.
     */
    private const ROOM_TYPES = [
        1 => 'single',
        2 => 'double',
        3 => 'triple',
        4 => 'quad',
    ];

    /**
     * Generate floors and rooms from wizard configuration data.
     *
     * @param Hostel $hostel
     * @param array $floorConfigs Array of floor configurations
     * @return array Summary of created entities
     */
    public function generateFromWizardData(Hostel $hostel, array $floorConfigs): array
    {
        $summary = [
            'floors_created' => 0,
            'rooms_created' => 0,
            'beds_created' => 0,
        ];

        DB::transaction(function () use ($hostel, $floorConfigs, &$summary) {
            foreach ($floorConfigs as $config) {
                $floor = $this->createFloor($hostel, $config);
                $summary['floors_created']++;

                $roomsCreated = $this->generateRoomsForFloor(
                    $floor,
                    $config['room_count'] ?? 0,
                    $config['room_capacity'] ?? 1,
                    $config['numbering_mode'] ?? 'auto',
                    $config['room_prefix'] ?? null
                );

                $summary['rooms_created'] += $roomsCreated['rooms'];
                $summary['beds_created'] += $roomsCreated['beds'];
            }
        });

        return $summary;
    }

    /**
     * Create a floor for a hostel.
     */
    private function createFloor(Hostel $hostel, array $config): Floor
    {
        return Floor::create([
            'tenant_id' => $hostel->tenant_id,
            'hostel_id' => $hostel->id,
            'floor_number' => $config['floor_number'],
            'name' => $config['name'] ?? "Floor {$config['floor_number']}",
        ]);
    }

    /**
     * Generate rooms for a floor.
     */
    private function generateRoomsForFloor(
        Floor $floor,
        int $roomCount,
        int $capacity,
        string $numberingMode,
        ?string $prefix
    ): array {
        $roomsCreated = 0;
        $bedsCreated = 0;

        for ($i = 1; $i <= $roomCount; $i++) {
            $roomNo = $this->generateRoomNumber($floor->floor_number, $i, $numberingMode, $prefix);

            $room = Room::create([
                'tenant_id' => $floor->tenant_id,
                'hostel_id' => $floor->hostel_id,
                'floor_id' => $floor->id,
                'campus_id' => $floor->hostel->campus_id,
                'block_code' => 'A', // Default block
                'floor_code' => (string) $floor->floor_number,
                'room_no' => $roomNo,
                'capacity' => $capacity,
                'room_type' => $this->getRoomType($capacity),
                'is_active' => true,
            ]);

            $roomsCreated++;

            // Generate beds for the room
            $bedsCreated += $this->generateBedsForRoom($room, $capacity);
        }

        return ['rooms' => $roomsCreated, 'beds' => $bedsCreated];
    }

    /**
     * Generate a room number based on the numbering mode.
     */
    private function generateRoomNumber(int $floorNumber, int $sequence, string $mode, ?string $prefix): string
    {
        if ($mode === 'manual' && $prefix) {
            return $prefix . str_pad($sequence, 2, '0', STR_PAD_LEFT);
        }

        // Auto-generate: F{floor}R{sequence} format
        return sprintf('F%dR%02d', $floorNumber, $sequence);
    }

    /**
     * Get room type based on capacity.
     */
    private function getRoomType(int $capacity): string
    {
        return self::ROOM_TYPES[$capacity] ?? 'single';
    }

    /**
     * Generate beds for a room.
     */
    private function generateBedsForRoom(Room $room, int $capacity): int
    {
        $bedsCreated = 0;

        for ($i = 0; $i < $capacity; $i++) {
            RoomBed::create([
                'tenant_id' => $room->tenant_id,
                'room_id' => $room->id,
                'hostel_id' => $room->hostel_id,
                'bed_code' => self::BED_LABELS[$i] ?? (string) ($i + 1),
                'status' => 'available',
            ]);
            $bedsCreated++;
        }

        return $bedsCreated;
    }

    /**
     * Preview room configuration without creating records.
     */
    public function previewConfiguration(array $floorConfigs): array
    {
        $preview = [];

        foreach ($floorConfigs as $config) {
            $floorNumber = $config['floor_number'] ?? 0;
            $roomCount = $config['room_count'] ?? 0;
            $capacity = $config['room_capacity'] ?? 1;
            $numberingMode = $config['numbering_mode'] ?? 'auto';
            $prefix = $config['room_prefix'] ?? null;

            $rooms = [];
            for ($i = 1; $i <= $roomCount; $i++) {
                $roomNo = $this->generateRoomNumber($floorNumber, $i, $numberingMode, $prefix);
                $rooms[] = [
                    'room_no' => $roomNo,
                    'capacity' => $capacity,
                    'beds' => array_slice(self::BED_LABELS, 0, $capacity),
                ];
            }

            $preview[] = [
                'floor_number' => $floorNumber,
                'floor_name' => $config['name'] ?? "Floor {$floorNumber}",
                'rooms' => $rooms,
                'total_rooms' => $roomCount,
                'total_beds' => $roomCount * $capacity,
            ];
        }

        return $preview;
    }

    /**
     * Validate room configuration.
     */
    public function validateConfiguration(Hostel $hostel, array $floorConfigs): array
    {
        $errors = [];

        // Check for duplicate floor numbers
        $floorNumbers = array_column($floorConfigs, 'floor_number');
        if (count($floorNumbers) !== count(array_unique($floorNumbers))) {
            $errors[] = 'Duplicate floor numbers detected.';
        }

        // Check for existing floors
        $existingFloors = Floor::where('hostel_id', $hostel->id)
            ->whereIn('floor_number', $floorNumbers)
            ->pluck('floor_number')
            ->toArray();

        if (!empty($existingFloors)) {
            $errors[] = 'Floors already exist: ' . implode(', ', $existingFloors);
        }

        // Validate each floor config
        foreach ($floorConfigs as $index => $config) {
            if (empty($config['floor_number'])) {
                $errors[] = "Floor " . ($index + 1) . ": Floor number is required.";
            }
            if (empty($config['room_count']) || $config['room_count'] < 1) {
                $errors[] = "Floor " . ($index + 1) . ": At least 1 room is required.";
            }
            if (empty($config['room_capacity']) || $config['room_capacity'] < 1 || $config['room_capacity'] > 8) {
                $errors[] = "Floor " . ($index + 1) . ": Room capacity must be between 1 and 8.";
            }
        }

        return $errors;
    }

    /**
     * Delete all rooms and floors for a hostel (use with caution).
     */
    public function clearHostelConfiguration(Hostel $hostel): void
    {
        DB::transaction(function () use ($hostel) {
            // Delete beds first (due to foreign key constraints)
            RoomBed::where('hostel_id', $hostel->id)->delete();
            
            // Delete rooms
            Room::where('hostel_id', $hostel->id)->delete();
            
            // Delete floors
            Floor::where('hostel_id', $hostel->id)->delete();
        });
    }
}

