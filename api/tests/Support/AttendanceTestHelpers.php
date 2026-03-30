<?php

namespace Tests\Support;

use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Collection;

class AttendanceTestHelpers
{
    public static function createRoomWithBedsAndAllocations(
        Tenant $tenant,
        Hostel $hostel,
        Room $room,
        Collection $users
    ): array {
        $beds = [];
        $allocations = [];

        $bedCodes = ['A', 'B', 'C', 'D'];
        
        foreach ($users as $index => $user) {
            $bedCode = $bedCodes[$index] ?? 'B' . uniqid();
            
            $bed = RoomBed::create([
                'room_id' => $room->id,
                'tenant_id' => $tenant->id,
                'hostel_id' => $hostel->id,
                'code' => $bedCode,
                'status' => 'available',
                'meta' => [],
            ]);
            
            // Create Student record for the User
            $student = \App\Models\Student::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'hostel_id' => $hostel->id,
                'map_student_id' => 'STD-' . strtoupper(uniqid()),
                'student_uid' => uniqid(),
                'roll_no' => 'RN' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
            ]);
            
            $allocation = RoomAllocation::create([
                'room_bed_id' => $bed->id,
                'student_id' => $student->id, // Use student.id, not user.id
                'hostel_id' => $hostel->id,
                'tenant_id' => $tenant->id,
                'effective_from' => now(),
                'is_active' => true,
            ]);
            
            $beds[] = $bed;
            $allocations[] = $allocation;
        }

        return [
            'beds' => $beds,
            'allocations' => $allocations,
            'students' => collect($allocations)->map(fn($allocation) => $allocation->student),
        ];
    }
}
