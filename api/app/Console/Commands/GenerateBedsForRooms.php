<?php

namespace App\Console\Commands;

use App\Models\Room;
use App\Models\RoomBed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class GenerateBedsForRooms extends Command
{
    protected $signature = 'rooms:generate-beds 
                            {--tenant= : Specific tenant ID to process}
                            {--dry-run : Show what would be created without actually creating}';

    protected $description = 'Generate beds for rooms that don\'t have any beds configured';

    public function handle(): int
    {
        $tenantId = $this->option('tenant');
        $dryRun = $this->option('dry-run');

        $this->info('🛏️  Generating beds for rooms without beds...');

        $query = Room::query()
            ->whereDoesntHave('beds')
            ->where('is_active', true);

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $rooms = $query->get();
        $totalRooms = $rooms->count();

        if ($totalRooms === 0) {
            $this->info('✅ All rooms already have beds configured.');
            return Command::SUCCESS;
        }

        $this->info("Found {$totalRooms} room(s) without beds.");

        if ($dryRun) {
            $this->warn('🔍 DRY RUN MODE - No beds will be created');
        }

        $createdBeds = 0;
        $processedRooms = 0;

        foreach ($rooms as $room) {
            $bedCount = $this->determineBedCount($room);
            
            if ($bedCount === null) {
                $this->warn("⚠️  Room {$room->number} (ID: {$room->id}) - Cannot determine bed count (no room_type or capacity)");
                continue;
            }

            $hostelName = $room->hostel ? $room->hostel->name : 'Unknown Hostel';
            $this->line("  Room {$room->number} ({$hostelName}) - Creating {$bedCount} bed(s)");

            if (!$dryRun) {
                DB::transaction(function () use ($room, $bedCount, &$createdBeds) {
                    for ($i = 1; $i <= $bedCount; $i++) {
                        RoomBed::create([
                            'tenant_id' => $room->tenant_id,
                            'room_id' => $room->id,
                            'hostel_id' => $room->hostel_id,
                            'code' => sprintf('%s-Bed-%02d', $room->number, $i),
                            'status' => 'available',
                        ]);
                        $createdBeds++;
                    }
                });
            } else {
                $createdBeds += $bedCount;
            }

            $processedRooms++;
        }

        if ($dryRun) {
            $this->info("\n🔍 DRY RUN: Would create {$createdBeds} bed(s) for {$processedRooms} room(s)");
        } else {
            $this->info("\n✅ Successfully created {$createdBeds} bed(s) for {$processedRooms} room(s)");
        }

        return Command::SUCCESS;
    }

    protected function determineBedCount(Room $room): ?int
    {
        // First, try to use room_type
        if ($room->room_type) {
            $type = strtolower($room->room_type);
            return match ($type) {
                'single' => 1,
                'double' => 2,
                'suite' => 1,
                default => null,
            };
        }

        // Fallback to capacity if set
        if ($room->capacity && $room->capacity > 0) {
            return $room->capacity;
        }

        // Default to 2 beds (Double) if nothing is set
        return 2;
    }
}

