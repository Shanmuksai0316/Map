<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Hostel;
use App\Models\Parcel;
use App\Models\Student;
use App\Services\Notify\PushNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ParcelController extends Controller
{
    public function __construct(
        private readonly PushNotifier $pushNotifier
    ) {}

    /**
     * Warden: List parcels for their hostel(s) – pending receive (informed) or all.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();
        if (empty($hostelIds)) {
            return response()->json(['data' => []], Response::HTTP_OK);
        }

        $status = $request->query('status', 'informed'); // informed = pending receive
        $limit = (int) $request->query('limit', 100);
        $limit = max(1, min($limit, 200));
        $query = Parcel::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereIn('hostel_id', $hostelIds)
            ->with(['student.user', 'hostel', 'receivedBy']);

        if ($status === 'informed') {
            $query->where('status', Parcel::STATUS_INFORMED);
        }

        $parcels = $query->orderByDesc('created_at')->limit($limit)->get();

        $data = $parcels->map(function (Parcel $p) {
            return [
                'id' => (string) $p->id,
                'student_id' => (string) $p->student_id,
                'student_name' => $p->student?->user?->name ?? 'Unknown',
                'room_number' => $p->room_number,
                'code' => null, // Code should be entered from student, not shown to warden
                'status' => $p->status,
                'notes' => $p->notes,
                'informed_at' => $p->informed_at?->toIso8601String(),
                'received_at' => $p->received_at?->toIso8601String(),
                'hostel_name' => $p->hostel?->name,
            ];
        });

        return response()->json(['data' => $data], Response::HTTP_OK);
    }

    /**
     * Warden: Create parcel and inform student (generate 4-digit code, send push).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
            'room_number' => ['nullable', 'string', 'max:32'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();
        if (! in_array((int) $validated['hostel_id'], $hostelIds, true)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Hostel not assigned to you.',
            ], Response::HTTP_FORBIDDEN);
        }

        $student = Student::where('tenant_id', $user->tenant_id)
            ->where('id', $validated['student_id'])
            ->where('hostel_id', $validated['hostel_id'])
            ->with('user')
            ->firstOrFail();

        $code = sprintf('%04d', random_int(0, 9999));

        $parcel = Parcel::create([
            'tenant_id' => $user->tenant_id,
            'hostel_id' => $validated['hostel_id'],
            'student_id' => $student->id,
            'received_by_user_id' => $user->id,
            'status' => Parcel::STATUS_INFORMED,
            'code' => $code,
            'code_expires_at' => now()->addHours(Parcel::CODE_TTL_HOURS),
            'room_number' => $validated['room_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'informed_at' => now(),
        ]);

        // Notify student
        if ($student->user_id) {
            try {
                $this->pushNotifier->toUserTemplate(
                    $student->user_id,
                    'student.parcel_informed',
                    [
                        'parcel_id' => (string) $parcel->id,
                        'room_number' => $parcel->room_number ?? 'N/A',
                        'code' => $code,
                    ],
                    [
                        'type' => 'parcel_informed',
                        'parcel_id' => (string) $parcel->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Parcel push notification failed', [
                    'parcel_id' => $parcel->id,
                    'student_user_id' => $student->user_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'data' => [
                'id' => (string) $parcel->id,
                'student_id' => (string) $parcel->student_id,
                'student_name' => $student->user?->name ?? 'Unknown',
                'status' => $parcel->status,
                'code' => $parcel->code,
                'room_number' => $parcel->room_number,
                'notes' => $parcel->notes,
                'informed_at' => $parcel->informed_at?->toIso8601String(),
            ],
            'message' => 'Student informed. Share the code with the student for handover.',
        ], Response::HTTP_CREATED);
    }

    /**
     * Warden: Mark parcel as received by verifying 4-digit code.
     */
    public function receive(Request $request, Parcel $parcel): JsonResponse
    {
        $user = $request->user();

        if (! $user->hasRole('Warden')) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only wardens can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'code' => ['required', 'string', 'size:4'],
        ]);

        if ($parcel->tenant_id !== $user->tenant_id) {
            return response()->json(['error' => 'Unauthorized'], Response::HTTP_FORBIDDEN);
        }

        $hostelIds = $user->staffHostels()->pluck('hostels.id')->toArray();
        if (! in_array($parcel->hostel_id, $hostelIds, true)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'detail' => 'Parcel not in your hostel.',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $parcel->isPendingReceive()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_state',
                'title' => 'Already received',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'This parcel has already been marked as received.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($parcel->isCodeExpired()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/code_expired',
                'title' => 'Code expired',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'The pickup code has expired. Please ask the warden to re-inform the student.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($parcel->isRateLimited()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/too_many_attempts',
                'title' => 'Too many attempts',
                'status' => Response::HTTP_TOO_MANY_REQUESTS,
                'detail' => 'Too many invalid attempts. Please wait and try again later.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
        }

        if (! $parcel->verifyCode($validated['code'])) {
            $attempts = $parcel->code_attempts ?? 0;
            $lastAttempt = $parcel->code_last_attempt_at;
            if ($lastAttempt && $lastAttempt->lt(now()->subMinutes(Parcel::CODE_ATTEMPT_WINDOW_MINUTES))) {
                $attempts = 0;
            }

            $parcel->update([
                'code_attempts' => min($attempts + 1, Parcel::CODE_MAX_ATTEMPTS),
                'code_last_attempt_at' => now(),
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_code',
                'title' => 'Invalid code',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'The code does not match. Please ask the student to show the code from their app.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $parcel->update([
            'status' => Parcel::STATUS_RECEIVED,
            'received_at' => now(),
            'received_verified_by_user_id' => $user->id,
            'code' => null,
            'code_expires_at' => null,
            'code_attempts' => 0,
            'code_last_attempt_at' => null,
        ]);

        $parcel->load(['student.user', 'hostel']);

        // Notify student
        if ($parcel->student?->user_id) {
            try {
                $this->pushNotifier->toUserTemplate(
                    $parcel->student->user_id,
                    'student.parcel_received',
                    [
                        'parcel_id' => (string) $parcel->id,
                    ],
                    [
                        'type' => 'parcel_received',
                        'parcel_id' => (string) $parcel->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Parcel received push to student failed', [
                    'parcel_id' => $parcel->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify warden who created the parcel (optional – same user might be receiving)
        if ($parcel->received_by_user_id && $parcel->received_by_user_id !== $user->id) {
            try {
                $this->pushNotifier->toUserTemplate(
                    $parcel->received_by_user_id,
                    'warden.parcel_received',
                    [
                        'parcel_id' => (string) $parcel->id,
                        'student_name' => $parcel->student?->user?->name ?? 'Student',
                    ],
                    [
                        'type' => 'parcel_received',
                        'parcel_id' => (string) $parcel->id,
                    ]
                );
            } catch (\Throwable $e) {
                Log::warning('Parcel received push to warden failed', ['parcel_id' => $parcel->id, 'error' => $e->getMessage()]);
            }
        }

        return response()->json([
            'data' => [
                'id' => (string) $parcel->id,
                'status' => $parcel->status,
                'received_at' => $parcel->received_at?->toIso8601String(),
            ],
            'message' => 'Parcel received successfully. Student has been notified.',
        ], Response::HTTP_OK);
    }

    /**
     * Student: List my parcels.
     */
    public function myParcels(Request $request): JsonResponse
    {
        $user = $request->user();

        $student = $user->student;
        if (! $student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Student record not found.',
            ], Response::HTTP_FORBIDDEN);
        }

        $parcels = Parcel::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('student_id', $student->id)
            ->with(['hostel'])
            ->orderByDesc('created_at')
            ->limit(max(1, min((int) $request->query('limit', 100), 200)))
            ->get();

        $data = $parcels->map(function (Parcel $p) {
            $code = $p->isPendingReceive() && ! $p->isCodeExpired()
                ? $p->code
                : null;

            return [
                'id' => (string) $p->id,
                'status' => $p->status,
                'code' => $code,
                'room_number' => $p->room_number,
                'notes' => $p->notes,
                'informed_at' => $p->informed_at?->toIso8601String(),
                'received_at' => $p->received_at?->toIso8601String(),
                'hostel_name' => $p->hostel?->name,
            ];
        });

        return response()->json(['data' => $data], Response::HTTP_OK);
    }
}
