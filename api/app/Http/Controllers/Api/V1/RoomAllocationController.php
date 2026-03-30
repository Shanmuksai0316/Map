<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\RoomAllocation\StoreRoomAllocationRequest;
use App\Http\Requests\RoomAllocation\UpdateRoomAllocationRequest;
use App\Http\Resources\RoomAllocationResource;
use App\Models\RoomAllocation;
use App\Models\RoomBed;
use App\Models\Student;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoomAllocationController extends Controller
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * @return AnonymousResourceCollection<RoomAllocation>
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RoomAllocation::class);

        $query = RoomAllocation::query()
            ->with(['student', 'roomBed.room', 'roomBed.blockedPeriods' => fn ($blocked) => $blocked->whereNull('effective_to')])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->boolean('active', true), fn ($builder) => $builder->where('is_active', true))
            ->when($hostelId = $request->input('hostel_id'), fn ($builder) => $builder->where('hostel_id', $hostelId))
            ->when($roomId = $request->input('room_id'), fn ($builder) => $builder->whereHas('roomBed', fn ($bedQuery) => $bedQuery->where('room_id', $roomId)))
            ->when($studentId = $request->input('student_id'), fn ($builder) => $builder->where('student_id', $studentId))
            ->orderByDesc('effective_from');

        $allocations = $query->paginate($request->integer('per_page', 25));

        return RoomAllocationResource::collection($allocations);
    }

    public function store(StoreRoomAllocationRequest $request): RoomAllocationResource
    {
        $this->authorize('create', RoomAllocation::class);

        $data = $request->validated();

        $student = Student::query()
            ->whereKey($data['student_id'])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        $bed = RoomBed::query()
            ->with(['blockedPeriods'])
            ->whereKey($data['room_bed_id'])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->firstOrFail();

        // Gender-hostel validation: prevent cross-gender allocation
        if ($bed->hostel_id && $student->gender) {
            if (! \App\Rules\GenderHostelMatch::check($bed->hostel_id, $student->gender)) {
                $hostel = \App\Models\Hostel::find($bed->hostel_id);
                abort(422, "Cannot allocate {$student->gender} student to {$hostel?->gender_mode} hostel ({$hostel?->name}). Gender mismatch.");
            }
        }

        $allocation = $this->db->transaction(function () use ($data, $student, $bed) {
            $effectiveFrom = Carbon::parse($data['effective_from']);

            // Check for concurrent allocation conflicts first
            $this->checkConcurrentAllocationConflicts($bed, $effectiveFrom);

            RoomAllocation::query()
                ->where('tenant_id', $student->tenant_id)
                ->where('student_id', $student->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->get()
                ->each(function (RoomAllocation $allocation) use ($effectiveFrom): void {
                    $allocation->forceFill([
                        'is_active' => false,
                        'effective_to' => $effectiveFrom,
                    ])->save();

                    $allocation->roomBed->forceFill([
                        'status' => 'available',
                        'released_at' => $effectiveFrom,
                    ])->save();
                });

            // Refresh bed data to get latest status
            $bed = $bed->fresh(['blockedPeriods']);
            $this->ensureBedIsAllocatable($bed, $effectiveFrom);

            $periodMonths = config('checkouts.default_period_months', 18);
            $expectedCheckoutAt = $effectiveFrom->copy()->addMonths($periodMonths);

            $allocation = RoomAllocation::query()->create([
                'tenant_id' => $student->tenant_id,
                'student_id' => $student->id,
                'room_bed_id' => $bed->id,
                'hostel_id' => $bed->hostel_id,
                'effective_from' => $effectiveFrom,
                'note' => Arr::get($data, 'note'),
                'is_active' => true,
                'expected_checkout_at' => $expectedCheckoutAt,
                'checkout_status' => 'pending',
            ]);

            $bed->forceFill([
                'status' => 'occupied',
                'occupied_at' => $effectiveFrom,
                'released_at' => null,
            ])->save();

            return $allocation;
        });

        return RoomAllocationResource::make($allocation->load(['student', 'roomBed.room']));
    }

    public function update(UpdateRoomAllocationRequest $request, RoomAllocation $roomAllocation): RoomAllocationResource
    {
        $this->authorize('update', $roomAllocation);

        $data = $request->validated();

        $allocation = $this->db->transaction(function () use ($roomAllocation, $data) {
            if (isset($data['room_bed_id']) && $data['room_bed_id'] !== $roomAllocation->room_bed_id) {
                $newBed = RoomBed::query()
                    ->with(['blockedPeriods'])
                    ->whereKey($data['room_bed_id'])
                    ->where('tenant_id', $roomAllocation->tenant_id)
                    ->firstOrFail();

                // Check for conflicts on the new bed
                $this->checkConcurrentAllocationConflicts($newBed, $roomAllocation->effective_from ?? now());
                $this->ensureBedIsAllocatable($newBed, $roomAllocation->effective_from ?? now());

                $roomAllocation->roomBed->forceFill([
                    'status' => 'available',
                    'released_at' => now(),
                ])->save();

                $roomAllocation->forceFill([
                    'room_bed_id' => $newBed->id,
                    'hostel_id' => $newBed->hostel_id,
                ]);

                $newBed->forceFill([
                    'status' => 'occupied',
                    'occupied_at' => $roomAllocation->effective_from,
                    'released_at' => null,
                ])->save();
            }

            if (isset($data['effective_to'])) {
                $effectiveTo = Carbon::parse($data['effective_to']);
                if ($effectiveTo->lt($roomAllocation->effective_from)) {
                    $this->throwValidationError('effective_to', 'Checkout must be after move-in time.');
                }
                $roomAllocation->forceFill([
                    'effective_to' => $effectiveTo,
                    'is_active' => false,
                ]);
                $roomAllocation->roomBed->forceFill([
                    'status' => 'available',
                    'released_at' => $effectiveTo,
                ])->save();
            }

            if (isset($data['note'])) {
                $roomAllocation->note = $data['note'];
            }

            $roomAllocation->save();

            return $roomAllocation;
        });

        return RoomAllocationResource::make($allocation->load(['student', 'roomBed.room']));
    }

    public function destroy(RoomAllocation $roomAllocation): JsonResponse
    {
        $this->authorize('delete', $roomAllocation);

        $this->db->transaction(function () use ($roomAllocation): void {
            $checkoutAt = now();
            $roomAllocation->forceFill([
                'is_active' => false,
                'effective_to' => $checkoutAt,
            ])->save();

            $roomAllocation->roomBed->forceFill([
                'status' => 'available',
                'released_at' => $checkoutAt,
            ])->save();
        });

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    private function ensureBedIsAllocatable(RoomBed $bed, Carbon $effectiveFrom): void
    {
        if ($bed->status === 'blocked') {
            $this->throwValidationError('bed', 'Bed is blocked.');
        }

        if ($bed->status === 'occupied') {
            $this->throwConflictError('bed', 'Bed is already occupied.', [
                'conflict_type' => 'bed_occupied',
                'bed_id' => $bed->id,
                'bed_code' => $bed->code,
                'current_status' => $bed->status,
                'suggestions' => [
                    'Check if the current allocation can be ended',
                    'Select a different bed',
                    'Contact the current occupant'
                ]
            ]);
        }

        $isBlocked = $bed->blockedPeriods
            ->filter(fn ($blocked) => $blocked->effective_from <= $effectiveFrom && (! $blocked->effective_to || $blocked->effective_to >= $effectiveFrom))
            ->isNotEmpty();

        if ($isBlocked) {
            $this->throwConflictError('bed', 'Bed is blocked for the selected period.', [
                'conflict_type' => 'bed_blocked',
                'bed_id' => $bed->id,
                'bed_code' => $bed->code,
                'blocked_periods' => $bed->blockedPeriods->map(fn ($period) => [
                    'from' => $period->effective_from,
                    'to' => $period->effective_to,
                    'reason' => $period->reason ?? 'Maintenance'
                ])->toArray(),
                'suggestions' => [
                    'Select a different bed',
                    'Choose a different date',
                    'Contact maintenance for unblocking'
                ]
            ]);
        }
    }

    private function throwValidationError(string $field, string $message): void
    {
        throw new HttpResponseException(response()->json([
            'type' => 'https://map-hms.dev/errors/validation',
            'title' => 'Validation Error',
            'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
            'detail' => $message,
            'errors' => [
                $field => [$message],
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }

    private function throwConflictError(string $field, string $message, array $details = []): void
    {
        throw new HttpResponseException(response()->json([
            'type' => 'https://map-hms.dev/errors/conflict',
            'title' => 'Resource Conflict',
            'status' => Response::HTTP_CONFLICT,
            'detail' => $message,
            'conflict_details' => $details,
        ], Response::HTTP_CONFLICT));
    }

    private function checkConcurrentAllocationConflicts(RoomBed $bed, Carbon $effectiveFrom): void
    {
        // Check for existing active allocations on this bed
        $existingAllocation = RoomAllocation::query()
            ->where('tenant_id', $bed->tenant_id)
            ->where('room_bed_id', $bed->id)
            ->where('is_active', true)
            ->first();

        if ($existingAllocation) {
            $this->throwConflictError('bed', 'Bed is currently allocated to another student.', [
                'conflict_type' => 'bed_concurrently_allocated',
                'bed_id' => $bed->id,
                'bed_code' => $bed->code,
                'existing_allocation' => [
                    'allocation_id' => $existingAllocation->id,
                    'student_id' => $existingAllocation->student_id,
                    'student_name' => $existingAllocation->student->name ?? 'Unknown',
                    'effective_from' => $existingAllocation->effective_from,
                    'effective_to' => $existingAllocation->effective_to,
                ],
                'suggestions' => [
                    'End the current allocation first',
                    'Select a different bed',
                    'Contact the current occupant',
                    'Check if the allocation can be transferred'
                ]
            ]);
        }

        // Check for overlapping allocation periods
        $overlappingAllocation = RoomAllocation::query()
            ->where('tenant_id', $bed->tenant_id)
            ->where('room_bed_id', $bed->id)
            ->where(function ($query) use ($effectiveFrom) {
                $query->where(function ($q) use ($effectiveFrom) {
                    // Allocation starts before our period and ends after our start
                    $q->where('effective_from', '<=', $effectiveFrom)
                      ->where(function ($subQ) use ($effectiveFrom) {
                          $subQ->whereNull('effective_to')
                               ->orWhere('effective_to', '>', $effectiveFrom);
                      });
                });
            })
            ->first();

        if ($overlappingAllocation) {
            $this->throwConflictError('bed', 'Bed has an overlapping allocation period.', [
                'conflict_type' => 'bed_period_overlap',
                'bed_id' => $bed->id,
                'bed_code' => $bed->code,
                'overlapping_allocation' => [
                    'allocation_id' => $overlappingAllocation->id,
                    'student_id' => $overlappingAllocation->student_id,
                    'student_name' => $overlappingAllocation->student->name ?? 'Unknown',
                    'effective_from' => $overlappingAllocation->effective_from,
                    'effective_to' => $overlappingAllocation->effective_to,
                ],
                'requested_date' => $effectiveFrom->toISOString(),
                'suggestions' => [
                    'Choose a date after the current allocation ends',
                    'Select a different bed',
                    'Modify the existing allocation period'
                ]
            ]);
        }
    }
}
