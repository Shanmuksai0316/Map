<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\VisitorPreRegistration;
use App\Models\VisitorLog;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class VisitorController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    /**
     * List pre-registrations
     */
    public function indexPreRegistrations(Request $request): JsonResponse
    {
        $this->authorize('viewAny', VisitorPreRegistration::class);

        $query = VisitorPreRegistration::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['hostel', 'student.user']);

        // Students see only their own
        if ($request->user()->hasRole('Student') && $request->user()->student) {
            $query->where('student_id', $request->user()->student->id);
        }

        // Warden sees only their hostels
        if ($request->user()->hasRole('Warden')) {
            $hostelIds = $request->user()->staffHostels->pluck('id');
            $query->whereIn('hostel_id', $hostelIds);
        }

        // Filters
        if ($request->filled('hostel_id')) {
            $query->where('hostel_id', $request->integer('hostel_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('visiting_date')) {
            $query->whereDate('visiting_date', $request->string('visiting_date'));
        }

        $preRegistrations = $query->latest()
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $preRegistrations->map(fn (VisitorPreRegistration $pr) => [
                'id' => $pr->id,
                'guest_name' => $pr->guest_name,
                'guest_phone' => $pr->guest_phone,
                'person_to_meet' => $pr->person_to_meet,
                'visiting_date' => $pr->visiting_date?->toDateString(),
                'status' => $pr->status,
                'hostel' => [
                    'id' => $pr->hostel->id,
                    'name' => $pr->hostel->name,
                ],
                'student' => [
                    'id' => $pr->student->id,
                    'name' => $pr->student->user->name,
                    'map_student_id' => $pr->student->map_student_id,
                ],
                'created_at' => $pr->created_at,
            ]),
            'meta' => [
                'current_page' => $preRegistrations->currentPage(),
                'per_page' => $preRegistrations->perPage(),
                'total' => $preRegistrations->total(),
            ],
        ]);
    }

    /**
     * Create a pre-registration
     */
    public function storePreRegistration(Request $request): JsonResponse
    {
        $this->authorize('create', VisitorPreRegistration::class);

        $validated = $request->validate([
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'required|string|max:20',
            'person_to_meet' => 'nullable|string|max:255',
            'visiting_date' => 'nullable|date|after_or_equal:today',
        ]);

        $student = $request->user()->student;
        if (!$student) {
            return response()->json(['error' => 'Student profile not found'], Response::HTTP_FORBIDDEN);
        }

        $preRegistration = DB::transaction(function () use ($request, $student, $validated) {
            $preRegistration = VisitorPreRegistration::create([
                'tenant_id' => $request->user()->tenant_id,
                'hostel_id' => $student->hostel_id,
                'student_id' => $student->id,
                'guest_name' => $validated['guest_name'],
                'guest_phone' => $validated['guest_phone'],
                'person_to_meet' => $validated['person_to_meet'] ?? $student->user->name,
                'visiting_date' => $validated['visiting_date'] ?? now()->toDateString(),
                'status' => 'Pending',
            ]);

            $this->auditLogger->log(
                'visitor.pre_register',
                $preRegistration,
                ['guest_name' => $validated['guest_name']]
            );

            return $preRegistration;
        });

        return response()->json([
            'message' => 'Visitor pre-registered successfully',
            'data' => [
                'id' => $preRegistration->id,
                'status' => $preRegistration->status,
            ],
        ], Response::HTTP_CREATED);
    }

    /**
     * Approve a pre-registration
     */
    public function approvePreRegistration(Request $request, VisitorPreRegistration $preRegistration): JsonResponse
    {
        $this->authorize('approve', $preRegistration);

        DB::transaction(function () use ($preRegistration) {
            $preRegistration->update(['status' => 'Approved']);

            $this->auditLogger->log(
                'visitor.pre_registration_approve',
                $preRegistration,
                ['guest_name' => $preRegistration->guest_name]
            );
        });

        return response()->json([
            'message' => 'Visitor pre-registration approved',
            'data' => ['id' => $preRegistration->id, 'status' => 'Approved'],
        ]);
    }

    /**
     * Decline a pre-registration
     */
    public function declinePreRegistration(Request $request, VisitorPreRegistration $preRegistration): JsonResponse
    {
        $this->authorize('decline', $preRegistration);

        DB::transaction(function () use ($preRegistration) {
            $preRegistration->update(['status' => 'Declined']);

            $this->auditLogger->log(
                'visitor.pre_registration_decline',
                $preRegistration,
                ['guest_name' => $preRegistration->guest_name]
            );
        });

        return response()->json([
            'message' => 'Visitor pre-registration declined',
            'data' => ['id' => $preRegistration->id, 'status' => 'Declined'],
        ]);
    }

    /**
     * Cancel a pre-registration (by student)
     */
    public function cancelPreRegistration(Request $request, VisitorPreRegistration $preRegistration): JsonResponse
    {
        $this->authorize('cancel', $preRegistration);

        $preRegistration->update(['status' => 'Cancelled']);

        return response()->json([
            'message' => 'Visitor pre-registration cancelled',
            'data' => ['id' => $preRegistration->id, 'status' => 'Cancelled'],
        ]);
    }

    /**
     * List visitor logs (Guard: record who entered)
     */
    public function indexLogs(Request $request): JsonResponse
    {
        $query = VisitorLog::query()
            ->where('tenant_id', $request->user()->tenant_id)
            ->with(['hostel', 'preRegistration', 'guard']);

        // Warden/Guard see only their hostels
        if ($request->user()->hasAnyRole(['Warden', 'Guard'])) {
            $hostelIds = $request->user()->staffHostels->pluck('id');
            $query->whereIn('hostel_id', $hostelIds);
        }

        // Filters
        if ($request->filled('hostel_id')) {
            $query->where('hostel_id', $request->integer('hostel_id'));
        }

        if ($request->filled('decision')) {
            $query->where('decision', $request->string('decision'));
        }

        $logs = $query->latest('occurred_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $logs->map(fn (VisitorLog $log) => [
                'id' => $log->id,
                'guest_name' => $log->guest_name,
                'guest_phone' => $log->guest_phone,
                'decision' => $log->decision,
                'reason' => $log->reason,
                'occurred_at' => $log->occurred_at,
                'hostel' => [
                    'id' => $log->hostel->id,
                    'name' => $log->hostel->name,
                ],
                'guard' => $log->guard ? [
                    'id' => $log->guard->id,
                    'name' => $log->guard->name,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Log visitor entry (Guard action)
     */
    public function storeLog(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'hostel_id' => 'required|exists:hostels,id',
            'visitor_pre_registration_id' => 'nullable|exists:visitor_pre_registrations,id',
            'guest_name' => 'required|string|max:255',
            'guest_phone' => 'required|string|max:20',
            'decision' => 'required|in:Allowed,Denied,EmergencyEntry',
            'reason' => 'nullable|string|max:500',
            'metadata' => 'nullable|array',
        ]);

        $log = DB::transaction(function () use ($request, $validated) {
            $log = VisitorLog::create([
                'tenant_id' => $request->user()->tenant_id,
                'hostel_id' => $validated['hostel_id'],
                'visitor_pre_registration_id' => $validated['visitor_pre_registration_id'] ?? null,
                'guest_name' => $validated['guest_name'],
                'guest_phone' => $validated['guest_phone'],
                'decision' => $validated['decision'],
                'reason' => $validated['reason'] ?? null,
                'occurred_at' => now(),
                'guard_id' => $request->user()->id,
                'metadata' => $validated['metadata'] ?? [],
            ]);

            $this->auditLogger->log(
                'visitor.log_entry',
                $log,
                [
                    'guest_name' => $validated['guest_name'],
                    'decision' => $validated['decision'],
                ]
            );

            return $log;
        });

        return response()->json([
            'message' => 'Visitor entry logged',
            'data' => ['id' => $log->id],
        ], Response::HTTP_CREATED);
    }
}

