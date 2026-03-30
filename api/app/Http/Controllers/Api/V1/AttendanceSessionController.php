<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Attendance\StoreAttendanceLogRequest;
use App\Http\Requests\Attendance\EditAttendanceLogRequest;
use App\Http\Requests\Attendance\StoreAttendanceSessionRequest;
use App\Http\Resources\AttendanceSessionResource;
use App\Models\AttendanceLog;
use App\Models\AttendanceSession;
use App\Services\AuditLogger;
use App\Support\Feature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class AttendanceSessionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('attendance_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('viewAny', AttendanceSession::class);

        $sessions = AttendanceSession::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('kind'), fn ($query) => $query->where('kind', $request->string('kind')))
            ->latest('scheduled_at')
            ->paginate($request->integer('per_page', 25));

        return AttendanceSessionResource::collection($sessions)->response();
    }

    public function store(StoreAttendanceSessionRequest $request): JsonResponse
    {
        abort_unless(Feature::isEnabled('attendance_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('create', AttendanceSession::class);

        $session = AttendanceSession::query()->create([
            'tenant_id' => Auth::user()->tenant_id,
            'campus_id' => $request->integer('campus_id') ?: null,
            'hostel_id' => $request->integer('hostel_id') ?: null,
            'name' => $request->string('name'),
            'kind' => $request->string('kind'),
            'scheduled_at' => $request->date('scheduled_at'),
            'metadata' => $request->input('metadata', []),
        ]);

        return AttendanceSessionResource::make($session)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function mark(AttendanceSession $session, StoreAttendanceLogRequest $request, int $studentId): JsonResponse
    {
        abort_unless(Feature::isEnabled('attendance_module'), Response::HTTP_NOT_FOUND);

        $this->authorize('mark', $session);

        // Check if this is an edit (existing mark) and session is closed
        $existingLog = AttendanceLog::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('attendance_session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        if ($existingLog && $session->status === AttendanceSession::STATUS_CLOSED) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Session Closed',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Cannot edit attendance marks after session is closed. Use the edit endpoint with a reason.',
            ], Response::HTTP_FORBIDDEN);
        }

        $isEdit = $existingLog !== null;
        $originalStatus = $existingLog?->status;

        DB::transaction(function () use ($session, $request, $studentId, $isEdit, $originalStatus): void {
            $log = AttendanceLog::query()->updateOrCreate(
                [
                    'tenant_id' => $session->tenant_id,
                    'attendance_session_id' => $session->id,
                    'student_id' => $studentId,
                ],
                [
                    'status' => $request->string('status'),
                    'marked_at' => $request->date('marked_at'),
                    'marked_by' => Auth::id(),
                    'note' => $request->string('note'),
                    'metadata' => $request->input('metadata', []),
                ]
            );

            // Log the action
            if ($isEdit) {
                $this->auditLogger->log('attendance.mark_updated', $session, [
                    'student_id' => $studentId,
                    'original_status' => $originalStatus,
                    'new_status' => $request->string('status'),
                    'updated_by' => Auth::id(),
                ]);
            } else {
                $this->auditLogger->log('attendance.mark_created', $session, [
                    'student_id' => $studentId,
                    'status' => $request->string('status'),
                    'marked_by' => Auth::id(),
                ]);
            }

            // Update session counts
            $session->recomputeCounts();
        });

        return response()->json(status: Response::HTTP_CREATED);
    }

    public function editMark(AttendanceSession $session, EditAttendanceLogRequest $request, int $studentId): JsonResponse
    {
        abort_unless(Feature::isEnabled('attendance_module'), Response::HTTP_NOT_FOUND);

        // Check if user can edit marks for this session
        $this->authorize('editMark', $session);

        // Find the existing attendance log
        $existingLog = AttendanceLog::query()
            ->where('tenant_id', $session->tenant_id)
            ->where('attendance_session_id', $session->id)
            ->where('student_id', $studentId)
            ->first();

        if (!$existingLog) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Attendance Mark Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'No attendance mark found for this student in this session.',
            ], Response::HTTP_NOT_FOUND);
        }

        // Store the original values for audit logging
        $originalStatus = $existingLog->status;
        $originalNote = $existingLog->note;

        DB::transaction(function () use ($session, $request, $studentId, $existingLog, $originalStatus, $originalNote): void {
            // Update the attendance log
            $existingLog->update([
                'status' => $request->string('status'),
                'marked_at' => $request->date('marked_at', $existingLog->marked_at),
                'marked_by' => Auth::id(),
                'note' => $request->string('note', $existingLog->note),
                'metadata' => array_merge($existingLog->metadata ?? [], [
                    'edit_reason' => $request->string('reason'),
                    'edited_at' => now()->toISOString(),
                    'edited_by' => Auth::id(),
                    'original_status' => $originalStatus,
                    'original_note' => $originalNote,
                ]),
            ]);

            // Log the edit action
            $this->auditLogger->log('attendance.mark_edited', $session, [
                'student_id' => $studentId,
                'original_status' => $originalStatus,
                'new_status' => $request->string('status'),
                'reason' => $request->string('reason'),
                'edited_by' => Auth::id(),
                'original_note' => $originalNote,
                'new_note' => $request->string('note', $originalNote),
            ]);

            // Update session counts if needed
            $session->recomputeCounts();
        });

        return response()->json([
            'message' => 'Attendance mark updated successfully',
            'data' => [
                'student_id' => $studentId,
                'status' => $existingLog->fresh()->status,
                'reason' => $request->string('reason'),
                'edited_at' => $existingLog->fresh()->metadata['edited_at'] ?? null,
            ],
        ], Response::HTTP_OK);
    }
}
