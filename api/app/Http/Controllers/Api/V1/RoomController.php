<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Http\Resources\RoomResource;
use App\Models\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoomController extends Controller
{
    public function index(): JsonResponse
    {
        $this->authorize('viewAny', Room::class);

        $rooms = Room::query()
            ->with(['beds'])
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when(request('hostel_id'), fn (Builder $query, $hostelId) => $query->where('hostel_id', $hostelId))
            ->orderBy('number')
            ->paginate(request()->integer('per_page', 25));

        return RoomResource::collection($rooms)->response();
    }

    public function store(StoreRoomRequest $request): JsonResponse
    {
        $data = $request->validated();

        $room = Room::query()->create([
            'tenant_id' => Auth::user()->tenant_id,
            'campus_id' => $data['campus_id'],
            'hostel_id' => $data['hostel_id'],
            'block_code' => $data['block_code'] ?? null,
            'floor_code' => $data['floor_code'] ?? null,
            'number' => $data['number'],
            'capacity' => $data['capacity'],
            'is_active' => $data['is_active'] ?? true,
        ]);

        $this->syncBeds($room, $data['beds']);

        return (new RoomResource($room->load('beds')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Room $room): RoomResource
    {
        $this->authorize('view', $room);

        return RoomResource::make($room->load('beds'));
    }

    public function update(UpdateRoomRequest $request, Room $room): RoomResource
    {
        $this->authorize('update', $room);

        $data = $request->validated();

        $room->fill([
            'block_code' => $data['block_code'] ?? $room->block_code,
            'floor_code' => $data['floor_code'] ?? $room->floor_code,
            'number' => $data['number'] ?? $room->number,
            'capacity' => $data['capacity'] ?? $room->capacity,
            'is_active' => $data['is_active'] ?? $room->is_active,
        ])->save();

        if (array_key_exists('beds', $data)) {
            $this->syncBeds($room, $data['beds']);
        }

        return RoomResource::make($room->load('beds'));
    }

    public function destroy(Room $room): JsonResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    private function syncBeds(Room $room, array $beds): void
    {
        $existing = $room->beds->keyBy('id');
        $processedIds = [];

        foreach ($beds as $bedData) {
            if (isset($bedData['id']) && $existing->has($bedData['id'])) {
                $bed = $existing[$bedData['id']];
                $bed->forceFill([
                    'code' => $bedData['code'],
                    'status' => $bedData['status'] ?? 'available',
                ])->save();

                $processedIds[] = $bed->id;
            } else {
                $created = $room->beds()->create([
                    'tenant_id' => $room->tenant_id,
                    'hostel_id' => $room->hostel_id,
                    'code' => $bedData['code'],
                    'status' => $bedData['status'] ?? 'available',
                ]);

                $processedIds[] = $created->id;
            }
        }

        $room->beds()->whereNotIn('id', $processedIds)->delete();
    }
}
