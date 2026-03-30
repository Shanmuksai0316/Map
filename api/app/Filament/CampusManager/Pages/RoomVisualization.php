<?php

namespace App\Filament\CampusManager\Pages;

use App\Models\Hostel;
use App\Models\Room;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class RoomVisualization extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Room Overview';

    protected static ?string $navigationGroup = 'Rooms & Allocation';

    protected static ?string $slug = 'room-visualization';

    protected static ?string $title = 'Room Overview';

    protected static string $view = 'filament.campus-manager.pages.room-visualization';

    public bool $isRoomModalOpen = false;

    public array $roomModalData = [];

    /** Filter properties — bound to the Blade view */
    public ?string $filterBlock = null;

    public ?string $filterFloor = null;

    /**
     * Get available block codes for the filter dropdown.
     */
    public function getBlockOptions(): array
    {
        $tenantId = $this->getTenantId();
        if (! $tenantId) {
            return [];
        }

        $hostelId = session('active_hostel_id') ?? $this->getPrimaryHostelId($tenantId);

        return Room::query()
            ->where('tenant_id', $tenantId)
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->whereNotNull('block_code')
            ->distinct()
            ->orderBy('block_code')
            ->pluck('block_code', 'block_code')
            ->toArray();
    }

    /**
     * Get available floor codes for the filter dropdown.
     */
    public function getFloorOptions(): array
    {
        $tenantId = $this->getTenantId();
        if (! $tenantId) {
            return [];
        }

        $hostelId = session('active_hostel_id') ?? $this->getPrimaryHostelId($tenantId);

        return Room::query()
            ->where('tenant_id', $tenantId)
            ->when($hostelId, fn ($q) => $q->where('hostel_id', $hostelId))
            ->whereNotNull('floor_code')
            ->distinct()
            ->orderBy('floor_code')
            ->pluck('floor_code', 'floor_code')
            ->toArray();
    }

    public function updatedFilterBlock(): void
    {
        // Livewire re-renders automatically
    }

    public function updatedFilterFloor(): void
    {
        // Livewire re-renders automatically
    }

    public function getViewData(): array
    {
        try {
            return [
                'hostels' => $this->getHostelsWithRooms(),
            ];
        } catch (\Exception $e) {
            \Log::error('RoomVisualization: Error in getViewData', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return [
                'hostels' => collect(),
            ];
        }
    }

    protected function getHostelsWithRooms(): Collection
    {
        try {
            $tenantId = $this->getTenantId();

            if (! $tenantId) {
                \Log::warning('RoomVisualization: No tenant_id available', [
                    'user_id' => auth()->id(),
                    'user_tenant_id' => auth()->user()?->tenant_id,
                ]);
                return collect();
            }

            // Use hostel switcher session, fallback to primary hostel
            $hostelId = session('active_hostel_id') ?? $this->getPrimaryHostelId($tenantId);

            if (! $hostelId && ! session('active_hostel_id')) {
                \Log::info('RoomVisualization: No hostel found for tenant', [
                    'tenant_id' => $tenantId,
                ]);
                return collect();
            }

            return Hostel::query()
                ->where('tenant_id', $tenantId)
                ->when($hostelId, fn ($q) => $q->whereKey($hostelId))
                ->with(['rooms' => fn ($query) => $query
                    ->when($this->filterBlock, fn ($q) => $q->where('block_code', $this->filterBlock))
                    ->when($this->filterFloor, fn ($q) => $q->where('floor_code', $this->filterFloor))
                    ->orderBy('floor_code')
                    ->orderBy('number'),
                ])
                ->orderBy('name')
                ->get()
                ->map(function (Hostel $hostel) use ($tenantId) {
                    $hostel->rooms->transform(function ($room) use ($hostel, $tenantId) {
                        try {
                            $bedsQuery = RoomBed::query()
                                ->withoutGlobalScopes()
                                ->where('room_id', $room->id)
                                ->where('hostel_id', $hostel->id)
                                ->where(function ($query) use ($tenantId) {
                                    $query->where('tenant_id', $tenantId)
                                        ->orWhereNull('tenant_id');
                                });

                            $room->beds_total_count = (clone $bedsQuery)->count();
                            $room->beds_available_count = (clone $bedsQuery)->where('status', 'available')->count();
                            $room->beds_occupied_count = $room->beds_total_count - $room->beds_available_count;
                            $room->hostel_name = $hostel->name;
                        } catch (\Exception $e) {
                            \Log::error('RoomVisualization: Error processing room', [
                                'room_id' => $room->id,
                                'error' => $e->getMessage(),
                            ]);
                            $room->beds_total_count = 0;
                            $room->beds_available_count = 0;
                            $room->beds_occupied_count = 0;
                            $room->hostel_name = $hostel->name;
                        }

                        return $room;
                    });

                    return $hostel;
                });
        } catch (\Exception $e) {
            \Log::error('RoomVisualization: Error in getHostelsWithRooms', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return collect();
        }
    }

    protected function getTenantId(): ?string
    {
        try {
            // Prioritize tenant context from subdomain
            if (function_exists('tenant') && tenant()) {
                return tenant()->id;
            }
        } catch (\Exception $e) {
            // tenant() might not be available or throw error
        }

        // Fallback to user's tenant_id
        if (auth()->check() && auth()->user()?->tenant_id) {
            return auth()->user()->tenant_id;
        }

        return null;
    }

    protected function getPrimaryHostelId(string $tenantId): ?int
    {
        $assignedHostelIds = $this->getUserAssignedHostelIds();

        $baseQuery = Hostel::query()
            ->where('tenant_id', $tenantId);

        if ($assignedHostelIds->isNotEmpty()) {
            $assignedHostelId = (clone $baseQuery)
                ->whereIn('id', $assignedHostelIds)
                ->orderBy('name')
                ->value('id');

            if ($assignedHostelId) {
                return $assignedHostelId;
            }
        }

        return $baseQuery
            ->orderByDesc('id')
            ->value('id');
    }

    protected function getUserAssignedHostelIds(): \Illuminate\Support\Collection
    {
        $userId = auth()->id();

        if (! $userId) {
            return collect();
        }

        return DB::table('staff_assignments')
            ->where('user_id', $userId)
            ->whereNull('revoked_at')
            ->pluck('hostel_id');
    }

    public function showRoomDetails(int $roomId): void
    {
        $tenantId = $this->getTenantId();

        if (! $tenantId) {
            return;
        }

        $room = Room::query()
            ->where('tenant_id', $tenantId)
            ->with(['hostel:id,name'])
            ->find($roomId);

        if (! $room) {
            return;
        }

        $beds = RoomBed::query()
            ->withoutGlobalScopes()
            ->where('room_id', $room->id)
            ->where('hostel_id', $room->hostel_id)
            ->where(function ($query) use ($tenantId) {
                $query->where('tenant_id', $tenantId)
                    ->orWhereNull('tenant_id');
            })
            ->orderBy('code')
            ->get();

        $this->roomModalData = [
            'room_id' => $room->id,
            'room_number' => $room->number,
            'hostel_name' => $room->hostel?->name,
            'block_code' => $room->block_code,
            'floor_code' => $room->floor_code,
            'beds' => $beds->map(function ($bed) use ($room, $tenantId) {
                $allocation = RoomAllocation::query()
                    ->withoutGlobalScopes()
                    ->where('room_bed_id', $bed->id)
                    ->where(function ($query) use ($tenantId) {
                        $query->where('tenant_id', $tenantId)
                            ->orWhereNull('tenant_id');
                    })
                    ->where('is_active', true)
                    ->orderByDesc('effective_from')
                    ->first();

                // Manually load student and user if allocation exists
                $student = null;
                $user = null;
                if ($allocation && $allocation->student_id) {
                    $student = \App\Models\Student::query()
                        ->withoutGlobalScopes()
                        ->where('id', $allocation->student_id)
                        ->where(function ($query) use ($tenantId) {
                            $query->where('tenant_id', $tenantId)
                                ->orWhereNull('tenant_id');
                        })
                        ->first();
                    
                    if ($student && $student->user_id) {
                        // Use default connection (tenant context handles the connection)
                        try {
                            $user = \App\Models\User::find($student->user_id);
                        } catch (\Exception $e) {
                            \Log::warning('RoomVisualization: Failed to load user', [
                                'user_id' => $student->user_id,
                                'error' => $e->getMessage(),
                            ]);
                            $user = null;
                        }
                    }
                }

                return [
                    'id' => $bed->id,
                    'code' => $bed->code,
                    'status' => $bed->status,
                    'allocation' => $allocation ? [
                        'id' => $allocation->id,
                        'student_id' => $student?->id ?? $allocation->student_id,
                        'student_name' => $user?->name ?? 'Unknown',
                        'student_uid' => $student?->student_uid ?? '—',
                        'roll_no' => $student?->roll_no ?? '—',
                        'program' => $student?->program ?? '—',
                        'year_of_study' => $student?->year_of_study ?? null,
                        'phone' => $user?->phone ?? '—',
                        'email' => $user?->email ?? '—',
                        'effective_from' => optional($allocation->effective_from)->format('d M Y'),
                        'effective_from_full' => optional($allocation->effective_from)->format('d M Y, h:i A'),
                        'note' => $allocation->note,
                    ] : null,
                ];
            })->toArray(),
        ];

        $this->isRoomModalOpen = true;
    }

    public function closeRoomModal(): void
    {
        $this->isRoomModalOpen = false;
    }

    public function removeAllocation(int $allocationId, int $roomId): void
    {
        $tenantId = $this->getTenantId();

        if (! $tenantId) {
            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('Tenant ID is required.')
                ->danger()
                ->send();
            return;
        }

        try {
            $allocation = RoomAllocation::query()
                ->withoutGlobalScopes()
                ->where('id', $allocationId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if (! $allocation) {
                \Filament\Notifications\Notification::make()
                    ->title('Allocation not found')
                    ->body('The allocation could not be found or is already inactive.')
                    ->warning()
                    ->send();
                return;
            }

            $bed = $allocation->roomBed;

            // Deactivate the allocation
            $allocation->update([
                'is_active' => false,
                'effective_to' => now(),
            ]);

            // Mark bed as available
            if ($bed) {
                $bed->update([
                    'status' => 'available',
                    'released_at' => now(),
                ]);
            }

            \Filament\Notifications\Notification::make()
                ->title('Allocation removed')
                ->body('The student has been deallocated from this bed.')
                ->success()
                ->send();

            // Refresh the room details to show updated state
            $this->showRoomDetails($roomId);

        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to remove allocation', [
                'allocation_id' => $allocationId,
                'error' => $e->getMessage(),
            ]);

            \Filament\Notifications\Notification::make()
                ->title('Error')
                ->body('Failed to remove allocation. Please try again.')
                ->danger()
                ->send();
        }
    }
}

