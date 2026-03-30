<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Domain\RoomChanges\Models\RoomChange;
use App\Http\Controllers\Controller;
use App\Http\Requests\RoomChange\ApproveRoomChangeRequest;
use App\Http\Requests\RoomChange\RejectRoomChangeRequest;
use App\Http\Resources\RoomChangeResource;
use App\Models\RoomBed;
use App\Services\RoomChanges\RoomChangeApprovalService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class RoomChangeApprovalController extends Controller
{
    public function __construct(private readonly RoomChangeApprovalService $service) {}

    public function index(): AnonymousResourceCollection
    {
        $this->authorize('viewAny', RoomChange::class);

        $query = RoomChange::query()
            ->with(['student.user', 'hostel'])
            ->when(request('status'), fn ($q, $status) => $q->where('status', $status))
            ->when(request('student_id'), fn ($q, $studentId) => $q->where('student_id', $studentId))
            ->orderByDesc('submitted_at');

        return RoomChangeResource::collection(
            $query->paginate(request('per_page', 25))
        );
    }

    public function show(RoomChange $roomChange): RoomChangeResource
    {
        $this->authorize('view', $roomChange);

        return RoomChangeResource::make(
            $roomChange->load(['student.user', 'hostel'])
        );
    }

    public function approve(ApproveRoomChangeRequest $request, RoomChange $roomChange): RoomChangeResource
    {
        $effectiveFrom = $request->date('effective_from', now());
        $bed = RoomBed::findOrFail($request->input('room_bed_id'));

        $record = $this->service->approve($roomChange, $bed, Carbon::parse($effectiveFrom), $request->input('note'));

        return RoomChangeResource::make($record);
    }

    public function reject(RejectRoomChangeRequest $request, RoomChange $roomChange): RoomChangeResource
    {
        $record = $this->service->reject($roomChange, $request->input('reason'));

        return RoomChangeResource::make($record);
    }
}
