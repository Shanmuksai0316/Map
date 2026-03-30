<?php

namespace App\Http\Controllers\Api\V1\CampusManager;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Models\SickLeave;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Emergency Controller for Campus Manager
 * 
 * Handles medical emergencies and security incidents
 */
class EmergencyController extends Controller
{
    /**
     * Get list of medical emergencies (sick leaves requiring attention).
     * Query param: acknowledged=0 (unacknowledged only), 1 (acknowledged only), or omit (all).
     */
    public function medicalList(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = SickLeave::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('requires_medical_attention', true)
            ->with(['student.user', 'hostel']);

        if ($request->has('acknowledged')) {
            if ($request->boolean('acknowledged') || $request->input('acknowledged') === '1') {
                $query->whereNotNull('acknowledged_at');
            } else {
                $query->whereNull('acknowledged_at');
            }
        }

        $emergencies = $query->latest()->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $emergencies->map(fn ($sick) => [
                'id' => $sick->id,
                'student_name' => $sick->student?->user?->name,
                'student_phone' => $sick->student?->user?->phone,
                'hostel' => $sick->hostel?->name,
                'room' => $sick->room_number,
                'symptoms' => $sick->symptoms,
                'status' => $sick->status,
                'acknowledged' => $sick->acknowledged_at !== null,
                'acknowledged_at' => $sick->acknowledged_at,
                'acknowledged_by' => $sick->acknowledgedBy?->name,
                'created_at' => $sick->created_at,
            ]),
            'meta' => [
                'current_page' => $emergencies->currentPage(),
                'per_page' => $emergencies->perPage(),
                'total' => $emergencies->total(),
            ],
        ]);
    }

    /**
     * Get single medical emergency details
     */
    public function medicalShow(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        
        $sickLeave = SickLeave::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['student.user', 'hostel', 'acknowledgedBy'])
            ->findOrFail($id);

        return response()->json([
            'data' => [
                'id' => $sickLeave->id,
                'student' => [
                    'id' => $sickLeave->student?->id,
                    'name' => $sickLeave->student?->user?->name,
                    'phone' => $sickLeave->student?->user?->phone,
                    'email' => $sickLeave->student?->user?->email,
                    'map_student_id' => $sickLeave->student?->map_student_id,
                ],
                'hostel' => [
                    'id' => $sickLeave->hostel?->id,
                    'name' => $sickLeave->hostel?->name,
                ],
                'room' => $sickLeave->room_number,
                'symptoms' => $sickLeave->symptoms,
                'notes' => $sickLeave->notes,
                'status' => $sickLeave->status,
                'requires_medical_attention' => $sickLeave->requires_medical_attention,
                'acknowledged_at' => $sickLeave->acknowledged_at,
                'acknowledged_by' => $sickLeave->acknowledgedBy ? [
                    'id' => $sickLeave->acknowledgedBy->id,
                    'name' => $sickLeave->acknowledgedBy->name,
                ] : null,
                'created_at' => $sickLeave->created_at,
            ],
        ]);
    }

    /**
     * Acknowledge a medical emergency. Warden only.
     */
    public function acknowledgeMedical(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('Warden')) {
            return response()->json(['error' => 'Only Warden can acknowledge medical emergencies'], Response::HTTP_FORBIDDEN);
        }

        $sickLeave = SickLeave::query()
            ->where('tenant_id', $user->tenant_id)
            ->findOrFail($id);

        if ($sickLeave->acknowledged_at !== null) {
            return response()->json([
                'error' => 'Already acknowledged',
            ], Response::HTTP_CONFLICT);
        }

        $sickLeave->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Medical emergency acknowledged',
            'data' => [
                'id' => $sickLeave->id,
                'acknowledged_at' => $sickLeave->acknowledged_at,
            ],
        ]);
    }

    /**
     * Get list of security incidents
     */
    public function incidentList(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = Incident::query()
            ->where('tenant_id', $user->tenant_id)
            ->with(['hostel', 'student.user', 'opener', 'acknowledgedBy'])
            ->latest('opened_at');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        // Filter by type
        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        // Filter by acknowledged status
        if ($request->has('acknowledged')) {
            if ($request->boolean('acknowledged')) {
                $query->whereNotNull('acknowledged_at');
            } else {
                $query->whereNull('acknowledged_at');
            }
        }

        $incidents = $query->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $incidents->map(fn ($incident) => [
                'id' => $incident->id,
                'type' => $incident->type,
                'status' => $incident->status,
                'hostel' => [
                    'id' => $incident->hostel?->id,
                    'name' => $incident->hostel?->name,
                ],
                'student' => $incident->student ? [
                    'id' => $incident->student->id,
                    'name' => $incident->student->user?->name,
                ] : null,
                'note' => $incident->note,
                'opened_by' => [
                    'id' => $incident->opener?->id,
                    'name' => $incident->opener?->name,
                ],
                'opened_at' => $incident->opened_at,
                'acknowledged' => $incident->acknowledged_at !== null,
                'acknowledged_at' => $incident->acknowledged_at,
                'acknowledged_by' => $incident->acknowledgedBy ? [
                    'id' => $incident->acknowledgedBy->id,
                    'name' => $incident->acknowledgedBy->name,
                ] : null,
            ]),
            'meta' => [
                'current_page' => $incidents->currentPage(),
                'per_page' => $incidents->perPage(),
                'total' => $incidents->total(),
            ],
        ]);
    }

    /**
     * Get unread (unacknowledged) incidents count
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $count = Incident::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereNull('acknowledged_at')
            ->where('status', 'Open')
            ->count();

        return response()->json([
            'data' => [
                'unread_count' => $count,
            ],
        ]);
    }

    /**
     * Acknowledge an incident. Warden only.
     */
    public function acknowledgeIncident(Request $request, Incident $incident): JsonResponse
    {
        $user = $request->user();
        if (!$user->hasRole('Warden')) {
            return response()->json(['error' => 'Only Warden can acknowledge incidents'], Response::HTTP_FORBIDDEN);
        }

        if ($incident->tenant_id !== $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        if ($incident->acknowledged_at !== null) {
            return response()->json([
                'error' => 'Already acknowledged',
            ], Response::HTTP_CONFLICT);
        }

        $incident->update([
            'acknowledged_at' => now(),
            'acknowledged_by' => $user->id,
        ]);

        return response()->json([
            'message' => 'Incident acknowledged',
            'data' => [
                'id' => $incident->id,
                'acknowledged_at' => $incident->acknowledged_at,
            ],
        ]);
    }
}

