<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\Leaves\Models\Leave;
use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class LeaveController extends Controller
{
    /**
     * Get student's own leaves
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
            // Get tenant ID from user
            $tenantId = $user->tenant_id;
            
            if (!$tenantId) {
                Log::warning('LeaveController: No tenant_id found for user', [
                    'user_id' => $user->id,
                    'student_id' => $user->student->id,
                ]);
            }

            $query = Leave::where('student_id', $user->student->id);
            
            // Filter by tenant_id if available
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $leaves = $query->latest('submitted_at')
                ->get()
                ->map(function ($leave) {
                    return [
                        'id' => (string) $leave->id,
                        'unique_id' => $leave->unique_id,
                        'title' => $leave->title,
                        'description' => $leave->description,
                        'reason_for_leave' => $leave->reason_for_leave,
                        'from_date' => $leave->from_date->format('Y-m-d'),
                        'to_date' => $leave->to_date->format('Y-m-d'),
                        'status' => $leave->status,
                        'rejection_reason' => $leave->rejection_reason,
                        'submitted_date' => $leave->submitted_at->format('Y-m-d H:i:s'),
                        'created_at' => $leave->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'data' => $leaves,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student leaves', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
                'tenant_id' => $user->tenant_id ?? null,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve leaves. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new leave request
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Log authentication status for debugging
        if (!$user) {
            Log::error('LeaveController::store - User not authenticated', [
                'has_auth_header' => $request->hasHeader('Authorization'),
                'auth_header_present' => !empty($request->header('Authorization')),
                'tenant_code' => $request->header('X-Tenant-Code'),
                'url' => $request->fullUrl(),
            ]);
            
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required. Please log in again.',
            ], Response::HTTP_UNAUTHORIZED);
        }
        
        if (!$user->student) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_a_student',
                'title' => 'Not a Student',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only students can create leaves.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'reason_for_leave' => 'required|string|max:500',
            'from_date' => 'required|date',
            'to_date' => 'required|date|after_or_equal:from_date',
            'emergency_contact' => 'nullable|string|max:20',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            // Get tenant ID from user
            $tenantId = $user->tenant_id;
            
            if (!$tenantId) {
                Log::warning('LeaveController: No tenant_id found for user when creating leave', [
                    'user_id' => $user->id,
                    'student_id' => $user->student->id,
                ]);
            }

            // Generate unique ID if not provided
            $idempotencyKey = $validated['idempotency_key'] ?? Str::uuid()->toString();
            
            // Check for duplicate using idempotency key (scoped to tenant if available)
            $duplicateQuery = Leave::where('idempotency_key', $idempotencyKey);
            if ($tenantId) {
                $duplicateQuery->where('tenant_id', $tenantId);
            }
            $existingLeave = $duplicateQuery->first();
            
            if ($existingLeave) {
                return response()->json([
                    'data' => [
                        'id' => (string) $existingLeave->id,
                        'unique_id' => $existingLeave->unique_id,
                        'message' => 'Leave request already created',
                    ],
                ], Response::HTTP_OK);
            }

            $leave = Leave::create([
                'tenant_id' => $tenantId,
                'student_id' => $user->student->id,
                'hostel_id' => $user->student->hostel_id ?? null,
                'title' => $validated['title'],
                'description' => $validated['reason_for_leave'], // Using reason as description
                'reason_for_leave' => $validated['reason_for_leave'],
                'from_date' => $validated['from_date'],
                'to_date' => $validated['to_date'],
                'emergency_contact' => $validated['emergency_contact'] ?? null,
                'status' => 'pending',
                'submitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            // Notify Rector about new leave request (push + in-app notification via PushNotifier)
            try {
                if ($tenantId && $user->student->hostel_id) {
                    /** @var NotificationRecipients $recipients */
                    $recipients = app(NotificationRecipients::class);
                    /** @var PushNotifier $push */
                    $push = app(PushNotifier::class);

                    $rector = $recipients->rectorForHostel((string) $tenantId, (int) $user->student->hostel_id);

                    if ($rector) {
                        $from = Carbon::parse($validated['from_date'])->format('d M');
                        $to = Carbon::parse($validated['to_date'])->format('d M');
                        $dateRange = $from === $to ? $from : "{$from}–{$to}";

                        $push->toUserTemplate(
                            $rector->id,
                            'rector.leave_submitted',
                            [
                                'student_name' => $user->name,
                                'date_range'   => $dateRange,
                            ],
                            [
                                'screen'   => 'Notifications',
                                'type'     => 'leave_submitted',
                                'leave_id' => (string) $leave->id,
                            ]
                        );
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('LeaveController::store - failed to send rector leave_submitted notification', [
                    'error' => $e->getMessage(),
                    'leave_id' => $leave->id ?? null,
                    'student_id' => $user->student->id,
                ]);
            }

            return response()->json([
                'message' => 'Leave request created successfully',
                'data' => [
                    'id' => (string) $leave->id,
                    'unique_id' => $leave->unique_id,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create leave request', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/create_failed',
                'title' => 'Create Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create leave request. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get specific leave details
     */
    public function show(Request $request, $id): JsonResponse
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
            // Get tenant ID from user
            $tenantId = $user->tenant_id;
            
            $query = Leave::where('id', $id)
                ->where('student_id', $user->student->id);
            
            // Filter by tenant_id if available
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $leave = $query->firstOrFail();

            return response()->json([
                'data' => [
                    'id' => (string) $leave->id,
                    'unique_id' => $leave->unique_id,
                    'title' => $leave->title,
                    'description' => $leave->description,
                    'reason_for_leave' => $leave->reason_for_leave,
                    'from_date' => $leave->from_date->format('Y-m-d'),
                    'to_date' => $leave->to_date->format('Y-m-d'),
                    'emergency_contact' => $leave->emergency_contact,
                    'status' => $leave->status,
                    'rejection_reason' => $leave->rejection_reason,
                    'submitted_date' => $leave->submitted_at->format('Y-m-d H:i:s'),
                    'created_at' => $leave->created_at->toIso8601String(),
                ],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Leave request not found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch leave details', [
                'error' => $e->getMessage(),
                'leave_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve leave details. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

