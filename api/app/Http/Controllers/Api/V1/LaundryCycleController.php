<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Laundry\StoreLaundryCycleRequest;
use App\Http\Resources\LaundryCycleResource;
use App\Models\LaundryCycle;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class LaundryCycleController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', LaundryCycle::class);

        $cycles = LaundryCycle::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest('created_at')
            ->paginate($request->integer('per_page', 25));

        return response()->json([
            'data' => LaundryCycleResource::collection($cycles)
        ]);
    }

    public function store(StoreLaundryCycleRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', LaundryCycle::class);

        $cycle = LaundryCycle::query()->create([
            'tenant_id' => Auth::user()->tenant_id,
            'hostel_id' => $request->integer('hostel_id') ?: null,
            'machine_label' => $request->string('machine_label'),
            'status' => 'scheduled',
            'metadata' => $request->input('metadata', []),
        ]);

        return response()->json([
            'data' => LaundryCycleResource::make($cycle)
        ], Response::HTTP_CREATED);
    }

    public function show(LaundryCycle $cycle): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('view', $cycle);

        return response()->json([
            'data' => LaundryCycleResource::make($cycle)
        ]);
    }

    public function updateStatus(Request $request, LaundryCycle $cycle): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('update', $cycle);

        $validated = $request->validate([
            'status' => 'required|in:ready,manual_verify,completed,cancelled',
            'note' => 'required_if:status,manual_verify|string|max:500',
        ]);

        $cycle->update([
            'status' => $validated['status'],
            'metadata' => array_merge($cycle->metadata ?? [], [
                'status_note' => $validated['note'] ?? null,
                'status_updated_at' => now()->toISOString(),
                'status_updated_by' => Auth::id(),
            ])
        ]);

        return response()->json([
            'data' => LaundryCycleResource::make($cycle)
        ]);
    }

    public function destroy(LaundryCycle $cycle): JsonResponse
    {
        abort_unless(Feature::isEnabled('laundry_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('delete', $cycle);

        $cycle->delete();

        return response()->json(['message' => 'Laundry cycle deleted successfully']);
    }
}
