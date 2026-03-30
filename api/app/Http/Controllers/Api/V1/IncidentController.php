<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class IncidentController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Incident::class);

        $query = Incident::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['hostel', 'student', 'opener', 'closer']);

        // Filter by hostel if user is Warden
        if ($request->user()->hasRole('Warden')) {
            $hostelIds = $request->user()->staffHostels->pluck('id');
            $query->whereIn('hostel_id', $hostelIds);
        }

        // Filters
        if ($request->filled('hostel_id')) {
            $query->where('hostel_id', $request->integer('hostel_id'));
        }

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->integer('student_id'));
        }

        $incidents = $query->latest('opened_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $incidents->map(fn (Incident $incident) => [
                'id' => $incident->id,
                'type' => $incident->type,
                'status' => $incident->status,
                'hostel' => [
                    'id' => $incident->hostel->id,
                    'name' => $incident->hostel->name,
                ],
                'student' => $incident->student ? [
                    'id' => $incident->student->id,
                    'name' => $incident->student->user->name,
                    'map_student_id' => $incident->student->map_student_id,
                ] : null,
                'note' => $incident->note,
                'opened_by' => [
                    'id' => $incident->opener->id,
                    'name' => $incident->opener->name,
                ],
                'opened_at' => $incident->opened_at,
                'closed_by' => $incident->closer ? [
                    'id' => $incident->closer->id,
                    'name' => $incident->closer->name,
                ] : null,
                'closed_at' => $incident->closed_at,
                'closure_note' => $incident->closure_note,
                'metadata' => $incident->metadata,
            ]),
            'meta' => [
                'current_page' => $incidents->currentPage(),
                'per_page' => $incidents->perPage(),
                'total' => $incidents->total(),
            ],
        ]);
    }

    public function show(Incident $incident): JsonResponse
    {
        $this->authorize('view', $incident);

        $incident->load(['hostel', 'student.user', 'opener', 'closer']);

        return response()->json([
            'data' => [
                'id' => $incident->id,
                'type' => $incident->type,
                'status' => $incident->status,
                'hostel' => [
                    'id' => $incident->hostel->id,
                    'name' => $incident->hostel->name,
                    'code' => $incident->hostel->code,
                ],
                'student' => $incident->student ? [
                    'id' => $incident->student->id,
                    'name' => $incident->student->user->name,
                    'map_student_id' => $incident->student->map_student_id,
                    'roll_no' => $incident->student->roll_no,
                ] : null,
                'note' => $incident->note,
                'opened_by' => [
                    'id' => $incident->opener->id,
                    'name' => $incident->opener->name,
                ],
                'opened_at' => $incident->opened_at,
                'closed_by' => $incident->closer ? [
                    'id' => $incident->closer->id,
                    'name' => $incident->closer->name,
                ] : null,
                'closed_at' => $incident->closed_at,
                'closure_note' => $incident->closure_note,
                'metadata' => $incident->metadata,
                'created_at' => $incident->created_at,
                'updated_at' => $incident->updated_at,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Incident::class);

        $validated = $request->validate([
            'hostel_id' => 'required|exists:hostels,id',
            'type' => 'required|in:LateReturn,MissedAttendance,EmergencyExit,Security',
            'student_id' => 'nullable|exists:students,id',
            'note' => 'required|string|max:1000',
            'metadata' => 'nullable|array',
        ]);

        $incident = DB::transaction(function () use ($request, $validated) {
            $incident = Incident::create([
                'tenant_id' => $request->user()->tenant_id,
                'hostel_id' => $validated['hostel_id'],
                'type' => $validated['type'],
                'student_id' => $validated['student_id'] ?? null,
                'note' => $validated['note'],
                'status' => 'Open',
                'opened_by' => $request->user()->id,
                'opened_at' => now(),
                'metadata' => $validated['metadata'] ?? [],
            ]);

            // Log the action
            $this->auditLogger->log(
                AuditLog::ACTION_INCIDENT_CREATE,
                $incident,
                [
                    'type' => $incident->type,
                    'hostel_id' => $incident->hostel_id,
                    'student_id' => $incident->student_id,
                ]
            );

            return $incident;
        });

        $incident->load(['hostel', 'student', 'opener']);

        return response()->json([
            'data' => [
                'id' => $incident->id,
                'type' => $incident->type,
                'status' => $incident->status,
                'hostel' => [
                    'id' => $incident->hostel->id,
                    'name' => $incident->hostel->name,
                ],
                'opened_at' => $incident->opened_at,
            ],
        ], Response::HTTP_CREATED);
    }

    public function close(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('close', $incident);

        $validated = $request->validate([
            'closure_note' => 'required|string|max:1000',
        ]);

        DB::transaction(function () use ($request, $incident, $validated) {
            $incident->close(
                $request->user()->id,
                $validated['closure_note']
            );

            // Log the action
            $this->auditLogger->log(
                \App\Models\AuditLog::ACTION_INCIDENT_CLOSE,
                $incident,
                [
                    'type' => $incident->type,
                    'closure_note' => $validated['closure_note'],
                ]
            );
        });

        return response()->json([
            'message' => 'Incident closed successfully',
            'data' => [
                'id' => $incident->id,
                'status' => $incident->status,
                'closed_at' => $incident->closed_at,
            ],
        ]);
    }

    public function update(Request $request, Incident $incident): JsonResponse
    {
        $this->authorize('update', $incident);

        $validated = $request->validate([
            'note' => 'sometimes|string|max:1000',
            'metadata' => 'sometimes|array',
        ]);

        $incident->update($validated);

        return response()->json([
            'message' => 'Incident updated successfully',
            'data' => [
                'id' => $incident->id,
                'note' => $incident->note,
                'metadata' => $incident->metadata,
            ],
        ]);
    }
}

