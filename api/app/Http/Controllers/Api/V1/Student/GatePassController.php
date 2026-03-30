<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Http\Controllers\Controller;
use App\Models\Domain\OutPass\OutPass;
use App\Services\Notify\PushNotifier;
use App\Services\Notifications\NotificationRecipients;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GatePassController extends Controller
{
    /**
     * Get student's own gate passes (outpasses)
     * 
     * Returns list of all outpass requests for the authenticated student
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $passes = OutPass::where('student_id', $user->student->id)
                // Keep list endpoint minimal/robust; avoid hard dependency on histories
                // and avoid ordering by a column that may not exist in older schemas.
                ->with(['hostel'])
                ->latest()
                ->get()
                ->map(function ($pass) {
                    $reasonValue = $pass->reason instanceof OutPassType ? $pass->reason->value : ($pass->reason ?? null);
                    $reasonLabel = null;
                    if ($pass->reason instanceof OutPassType) {
                        // Be defensive: older deployments may not have label() on the enum yet.
                        $reasonLabel = method_exists($pass->reason, 'label')
                            ? $pass->reason->label()
                            : ucfirst($pass->reason->value);
                    } elseif ($pass->reason !== null) {
                        $reasonLabel = ucfirst((string) $pass->reason);
                    }

                    $statusValue = $pass->status instanceof OutPassStatus ? $pass->status->value : ($pass->status ?? null);
                    $statusLabel = null;
                    if ($pass->status instanceof OutPassStatus) {
                        // Be defensive: older deployments may not have label() on the enum yet.
                        $statusLabel = method_exists($pass->status, 'label')
                            ? $pass->status->label()
                            : ucfirst($pass->status->value);
                    } else {
                        $statusLabel = ucfirst((string) ($pass->status ?? 'pending'));
                    }

                    return [
                        'id' => (string) $pass->id,
                        // reason can be enum-casted OR plain string (older rows / cast mismatch)
                        'reason' => $reasonValue,
                        'reason_label' => $reasonLabel,
                        // status can be enum-casted OR plain string (older rows / cast mismatch)
                        'status' => $statusValue,
                        'status_label' => $statusLabel,
                        'hostel' => $pass->hostel?->name,
                        // requested_at may be null in older data; fall back to created_at
                        'requested_at' => $pass->requested_at?->toIso8601String() ?? $pass->created_at?->toIso8601String(),
                        'required_date' => $pass->required_date?->format('Y-m-d'),
                        'valid_until' => $pass->valid_until?->toIso8601String(),
                        'decided_at' => $pass->decided_at?->toIso8601String(),
                        'created_at' => $pass->created_at->toIso8601String(),
                        'updated_at' => $pass->updated_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'data' => $passes,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student gate passes', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
                'user_id' => $user->id,
            ]);

            $payload = [
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve gate passes. Please try again.',
            ];

            // Debug helper for mobile dev builds (do not expose unless explicitly requested)
            if ($request->header('X-Debug') === '1') {
                $payload['debug'] = [
                    'exception' => get_class($e),
                    'message' => $e->getMessage(),
                ];
            }

            return response()->json($payload, Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new gate pass (outpass) request
     * 
     * Students can request an outpass with reason, overnight flag, and optional note
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can create gate passes.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate student has a hostel assignment
        $student = $user->student;
        if (!$student->hostel_id) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/no_hostel',
                'title' => 'No Hostel Assignment',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'You must be assigned to a hostel to request an outpass.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get valid OutPassType values for validation
        $validReasons = array_map(fn($case) => $case->value, OutPassType::cases());

        $validated = $request->validate([
            'reason' => 'required|string|in:' . implode(',', $validReasons),
            'required_date' => 'required|date|after_or_equal:today',
            // Backward compatibility: accept but ignore overnight and note
            'overnight' => 'sometimes|boolean',
            'valid_until' => 'nullable|date|after:now',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            // Generate idempotency key from request header or create new one
            $idempotencyKey = $request->header('Idempotency-Key') ?? Str::uuid()->toString();

            // Check for existing request with same idempotency key (prevent duplicates)
            $existing = OutPass::where('student_id', $student->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();

            if ($existing) {
                return response()->json([
                    'message' => 'Outpass request already exists',
                    'data' => $this->formatOutPass($existing),
                ], Response::HTTP_OK);
            }

            // Calculate valid_until from required_date (use end of that day)
            $requiredDate = \Carbon\Carbon::parse($validated['required_date'])->setTimezone('Asia/Kolkata');
            $validUntil = $requiredDate->copy()->endOfDay();

            $pass = OutPass::create([
                'tenant_id' => $user->tenant_id,
                'student_id' => $student->id,
                'hostel_id' => $student->hostel_id,
                'reason' => OutPassType::from($validated['reason']),
                'overnight' => false, // Always false per new requirements
                'status' => OutPassStatus::PENDING,
                'requested_at' => now('Asia/Kolkata'),
                'required_date' => $requiredDate,
                'valid_until' => $validUntil,
                'note' => null, // No longer accepting notes per new requirements
                'idempotency_key' => $idempotencyKey,
            ]);

            // Record history for audit trail
            $pass->recordHistory(
                null, 
                OutPassStatus::PENDING, 
                'Out-pass requested via mobile app',
                $user->id,
                'Request Submitted',
                'Student submitted the out-pass request'
            );

            Log::info('Student created gate pass request', [
                'gate_pass_id' => $pass->id,
                'student_id' => $student->id,
                'user_id' => $user->id,
                'reason' => $validated['reason'],
                'overnight' => false,
            ]);

            // Notify Rector + Guards about new pending outpass (push + in-app for Rector)
            try {
                $recipients = app(NotificationRecipients::class);
                $push       = app(PushNotifier::class);

                $tenantId  = (string) $user->tenant_id;
                $hostelId  = (int) $student->hostel_id;
                $rector    = $recipients->rectorForHostel($tenantId, $hostelId);
                $guards    = $recipients->guardsForHostel($tenantId, $hostelId);

                $timeRange = $pass->requested_at?->format('d M H:i') . '–' . $pass->valid_until?->format('H:i');

                if ($rector) {
                    $push->toUserTemplate(
                        $rector->id,
                        'rector.outpass_submitted',
                        [
                            'student_name' => $user->name,
                            'time_range'   => $timeRange,
                        ],
                        [
                            'screen'     => 'Notifications',
                            'type'       => 'outpass_submitted',
                            'outpass_id' => (string) $pass->id,
                        ]
                    );
                }

                foreach ($guards as $guard) {
                    $push->toUserTemplate(
                        $guard->id,
                        'guard.outpass_gate_action',
                        [
                            'outpass_id'   => $pass->id,
                            'student_name' => $user->name,
                            'time_range'   => $timeRange,
                        ],
                        [
                            'screen'     => 'Notifications',
                            'type'       => 'outpass_gate_action',
                            'outpass_id' => (string) $pass->id,
                        ]
                    );
                }
            } catch (\Throwable $e) {
                Log::warning('GatePassController: failed to send rector/guard outpass_submitted push', [
                    'error' => $e->getMessage(),
                    'pass_id' => $pass->id,
                ]);
            }

            return response()->json([
                'message' => 'Gate pass request created successfully',
                'data' => $this->formatOutPass($pass),
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create gate pass', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'student_id' => $student->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/creation_failed',
                'title' => 'Creation Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create gate pass. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get single gate pass details
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $pass = OutPass::where('student_id', $user->student->id)
                ->where('id', $id)
                ->with(['hostel', 'histories.actor'])
                ->firstOrFail();

            // Ensure backup code exists for approved passes (some older deployments
            // may not generate it on approval due to missing observer/flow).
            // This allows the student app to show a 4-digit fallback without relying on SMS.
            $statusValue = $pass->status instanceof OutPassStatus
                ? $pass->status
                : (is_string($pass->status) ? OutPassStatus::tryFrom($pass->status) : null);

            $isApproved = $statusValue === OutPassStatus::APPROVED;
            if (
                $isApproved &&
                !$pass->backup_code_used_at &&
                !$pass->qr_scanned_at &&
                $pass->hostel?->areBackupCodesEnabled() &&
                (empty($pass->backup_code) || empty($pass->backup_code_plain))
            ) {
                // Generates hashed + encrypted plain code
                $pass->generateBackupCode();
                // Refresh model so formatOutPass sees updated fields
                $pass->refresh();
            }

            return response()->json([
                'data' => $this->formatOutPass($pass, true),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Gate pass not found.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Cancel a pending gate pass request
     */
    public function cancel(Request $request, string $id): JsonResponse
    {
        $user = $request->user();
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can access this endpoint.',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            $pass = OutPass::where('student_id', $user->student->id)
                ->where('id', $id)
                ->firstOrFail();

            // Can only cancel pending requests
            if ($pass->status !== OutPassStatus::PENDING) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/invalid_status',
                    'title' => 'Cannot Cancel',
                    'status' => Response::HTTP_BAD_REQUEST,
                    'detail' => 'Only pending requests can be cancelled.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $previousStatus = $pass->status;
            $pass->forceFill([
                'status' => OutPassStatus::CANCELLED,
                'decided_at' => now(),
            ])->save();

            $pass->recordHistory(
                $previousStatus,
                OutPassStatus::CANCELLED,
                'Cancelled by student',
                $user->id,
                'Request Cancelled',
                'Student cancelled the out-pass request'
            );

            Log::info('Student cancelled gate pass', [
                'gate_pass_id' => $pass->id,
                'student_id' => $user->student->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'message' => 'Gate pass cancelled successfully',
                'data' => $this->formatOutPass($pass),
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Gate pass not found.',
            ], Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Format outpass for API response
     */
    private function formatOutPass(OutPass $pass, bool $includeHistory = false): array
    {
        $now = now('Asia/Kolkata');
        $isExpired = $pass->valid_until ? $now->greaterThan($pass->valid_until) : false;

        $reasonValue = $pass->reason instanceof OutPassType ? $pass->reason->value : ($pass->reason ?? null);
        $reasonLabel = null;
        if ($pass->reason instanceof OutPassType) {
            $reasonLabel = method_exists($pass->reason, 'label')
                ? $pass->reason->label()
                : ucfirst($pass->reason->value);
        } elseif ($pass->reason !== null) {
            $reasonLabel = ucfirst((string) $pass->reason);
        }

        $statusValue = $pass->status instanceof OutPassStatus ? $pass->status->value : ($pass->status ?? null);
        $statusLabel = null;
        if ($pass->status instanceof OutPassStatus) {
            $statusLabel = method_exists($pass->status, 'label')
                ? $pass->status->label()
                : ucfirst($pass->status->value);
        } else {
            $statusLabel = ucfirst((string) ($pass->status ?? 'pending'));
        }

        $statusColor = $pass->status instanceof OutPassStatus
            ? (method_exists($pass->status, 'color') ? $pass->status->color() : 'gray')
            : 'gray';

        $data = [
            'id' => (string) $pass->id,
            'unique_id' => (string) ($pass->unique_id ?? ('OP-' . $pass->id)),
            'reason' => $reasonValue,
            'reason_label' => $reasonLabel,
            'overnight' => (bool) $pass->overnight,
            'status' => $statusValue,
            'status_label' => $statusLabel,
            'status_color' => $statusColor,
            'hostel' => $pass->hostel?->name,
            'requested_at' => $pass->requested_at?->toIso8601String(),
            'valid_until' => $pass->valid_until?->toIso8601String(),
            'decided_at' => $pass->decided_at?->toIso8601String(),
            'is_expired' => $isExpired,
            'note' => $pass->note,
            'created_at' => $pass->created_at->toIso8601String(),
            'updated_at' => $pass->updated_at->toIso8601String(),
        ];

        // For the student gate-pass screen: include backup code if it's still usable.
        $statusValue = $pass->status instanceof OutPassStatus ? $pass->status : (is_string($pass->status) ? OutPassStatus::tryFrom($pass->status) : null);
        $isApproved = $statusValue === OutPassStatus::APPROVED;
        if (
            $isApproved &&
            !$pass->backup_code_used_at &&
            !$pass->qr_scanned_at
        ) {
            $data['backup_code'] = $pass->backup_code_plain;
        } else {
            $data['backup_code'] = null;
        }

        if ($includeHistory && $pass->relationLoaded('histories')) {
            $data['history'] = $pass->histories->map(fn($h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'label' => $h->timeline_label,
                'description' => $h->timeline_description,
                'actor' => $h->actor?->name,
                'changed_at' => $h->changed_at?->toIso8601String(),
            ])->toArray();
        }

        return $data;
    }
}
