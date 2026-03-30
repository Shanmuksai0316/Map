<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\Checkouts\CompleteCheckoutRequest;
use App\Http\Requests\Checkouts\StartCheckoutRequest;
use App\Http\Resources\RoomAllocationResource;
use App\Models\RoomAllocation;
use App\Services\Checkouts\CheckoutWorkflowService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CheckoutController extends Controller
{
    public function __construct(private readonly CheckoutWorkflowService $service) {}

    public function upcoming(): AnonymousResourceCollection
    {
        $query = RoomAllocation::query()
            ->with(['student.user', 'roomBed.room'])
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->when(request('hostel_id'), fn ($q, $hostelId) => $q->where('hostel_id', $hostelId))
            ->orderBy('expected_checkout_at');

        return RoomAllocationResource::collection(
            $query->paginate(request('per_page', 25))
        );
    }

    public function start(StartCheckoutRequest $request, RoomAllocation $roomAllocation)
    {
        $this->authorize('update', $roomAllocation);

        $checklist = $this->service->start($roomAllocation, $request->validated());

        return response()->json([
            'data' => $checklist,
        ]);
    }

    public function complete(CompleteCheckoutRequest $request, RoomAllocation $roomAllocation)
    {
        $this->authorize('update', $roomAllocation);

        $checklist = $this->service->complete($roomAllocation, $request->validated());

        return response()->json([
            'data' => $checklist,
        ]);
    }

    /**
     * Extend expected checkout by the configured period (default 1.5 years).
     */
    public function extend(RoomAllocation $roomAllocation)
    {
        $this->authorize('update', $roomAllocation);

        $allocation = $this->service->extend($roomAllocation);

        return response()->json([
            'data' => RoomAllocationResource::make($allocation->load(['student.user', 'roomBed.room'])),
            'message' => 'Checkout extended by ' . (config('checkouts.default_period_months', 18) / 12) . ' year(s).',
        ]);
    }
}
