<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Http\Controllers\Controller;
use App\Http\Requests\AutoAllocation\AutoAllocationCommitRequest;
use App\Http\Requests\AutoAllocation\AutoAllocationPreviewRequest;
use App\Http\Resources\RoomAllocationResource;
use App\Services\Rooms\AutoAllocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class AutoAllocationController extends Controller
{
    public function __construct(private readonly AutoAllocationService $service) {}

    public function downloadTemplate(Request $request, string $mode)
    {
        $this->authorize('create', \App\Models\RoomAllocation::class);

        if (! in_array($mode, ['pre-assignment', 'auto-allocation'], true)) {
            abort(404);
        }

        $path = $this->service->generateTemplate($mode, $request->input('hostel_id'));

        return Response::download($path, basename($path))->deleteFileAfterSend(true);
    }

    public function preview(AutoAllocationPreviewRequest $request)
    {
        $this->authorize('create', \App\Models\RoomAllocation::class);

        $data = $request->validated();

        $suggestions = $this->service->preview(
            $data['hostel_id'] ?? null,
            $data['limit'] ?? 50
        );

        return response()->json([
            'data' => $suggestions,
        ]);
    }

    public function commit(AutoAllocationCommitRequest $request)
    {
        $this->authorize('create', \App\Models\RoomAllocation::class);

        $data = $request->validated();

        $results = $this->service->commit(
            $data['allocations'],
            $data['note'] ?? null,
            $data['effective_from'] ? now()->parse($data['effective_from']) : null
        );

        return RoomAllocationResource::collection(collect($results))
            ->additional(['status' => 'success'])
            ->response()
            ->setStatusCode(201);
    }
}

