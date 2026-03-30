<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Gate\StoreGateEntryRequest;
use App\Http\Resources\GateEntryResource;
use App\Models\GateEntry;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class GateEntryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('gate_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', GateEntry::class);

        $entries = GateEntry::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('campus_id'), fn ($query) => $query->where('campus_id', $request->integer('campus_id')))
            ->when($request->filled('hostel_id'), fn ($query) => $query->where('hostel_id', $request->integer('hostel_id')))
            ->when($request->filled('student_id'), fn ($query) => $query->where('student_id', $request->integer('student_id')))
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')))
            ->when($request->filled('from'), fn ($query) => $query->where('occurred_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->where('occurred_at', '<=', $request->date('to')))
            ->latest('occurred_at')
            ->paginate($request->integer('per_page', 25));

        return GateEntryResource::collection($entries)->response();
    }

    public function store(StoreGateEntryRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('gate_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', GateEntry::class);

        $entry = GateEntry::query()->create([
            'tenant_id' => Auth::user()->tenant_id,
            'campus_id' => $request->integer('campus_id') ?: null,
            'hostel_id' => $request->integer('hostel_id') ?: null,
            'guard_id' => Auth::id(),
            'student_id' => $request->integer('student_id') ?: null,
            'event' => $request->string('event'),
            'occurred_at' => $request->date('occurred_at'),
            'source' => $request->string('source', 'mobile'),
            'was_offline' => $request->boolean('was_offline', false),
            'synced_at' => $request->date('synced_at'),
            'notes' => $request->string('notes'),
            'metadata' => $request->input('metadata', []),
            'client_reference' => $request->string('client_reference'),
        ]);

        return GateEntryResource::make($entry)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function sync(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('gate_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', GateEntry::class);

        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.client_reference' => ['required', 'string', 'max:64'],
            'entries.*.event' => ['required', 'in:entry,exit,emergency_exit,manual_override'],
            'entries.*.occurred_at' => ['required', 'date'],
            'entries.*.campus_id' => ['nullable', 'integer', 'exists:campuses,id'],
            'entries.*.hostel_id' => ['nullable', 'integer', 'exists:hostels,id'],
            'entries.*.student_id' => ['nullable', 'integer', 'exists:students,id'],
            'entries.*.metadata' => ['nullable', 'array'],
        ]);

        $tenantId = Auth::user()->tenant_id;

        DB::transaction(function () use ($validated, $tenantId): void {
            foreach ($validated['entries'] as $entry) {
                GateEntry::query()->firstOrCreate(
                    [
                        'tenant_id' => $tenantId,
                        'client_reference' => $entry['client_reference'],
                    ],
                    [
                        'campus_id' => $entry['campus_id'] ?? null,
                        'hostel_id' => $entry['hostel_id'] ?? null,
                        'guard_id' => Auth::id(),
                        'student_id' => $entry['student_id'] ?? null,
                        'event' => $entry['event'],
                        'occurred_at' => $entry['occurred_at'],
                        'source' => $entry['metadata']['source'] ?? 'mobile',
                        'was_offline' => true,
                        'synced_at' => now(),
                        'notes' => $entry['metadata']['notes'] ?? null,
                        'metadata' => $entry['metadata'] ?? [],
                    ]
                );
            }
        });

        return response()->json(status: Response::HTTP_CREATED);
    }
}
