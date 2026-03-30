<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\GuestEntries\Models\GuestEntry;
use App\Http\Controllers\Controller;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notify\PushNotifier;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

class GuestEntryController extends Controller
{
    /**
     * Get student's own guest entries
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
            $guestEntries = GuestEntry::where('tenant_id', $user->tenant_id)
                ->where('student_id', $user->student->id)
                ->latest('submitted_at')
                ->get()
                ->map(function ($guestEntry) {
                    return [
                        'id' => (string) $guestEntry->id,
                        'unique_id' => $guestEntry->unique_id,
                        'title' => $guestEntry->title,
                        'description' => $guestEntry->description,
                        'guests' => $guestEntry->guests,
                        'primary_contact_mobile' => $guestEntry->primary_contact_mobile,
                        'visit_date' => $guestEntry->visit_date->format('Y-m-d'),
                        'check_in_time' => $guestEntry->check_in_time, // Already stored as string (H:i format)
                        'check_out_time' => $guestEntry->check_out_time, // Already stored as string (H:i format) or null
                        'purpose_to_visit' => $guestEntry->purpose_to_visit,
                        'status' => $guestEntry->status,
                        'rejection_reason' => $guestEntry->rejection_reason,
                        'submitted_date' => $guestEntry->submitted_at->format('Y-m-d H:i:s'),
                        'created_at' => $guestEntry->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'data' => $guestEntries,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student guest entries', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve guest entries. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new guest entry request
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Log authentication status for debugging
        if (!$user) {
            Log::error('GuestEntryController::store - User not authenticated', [
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
                'detail' => 'Only students can create guest entries.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'guests' => 'required|array|min:1|max:4', // Up to 4 guests
            'guests.*.name' => 'required|string|max:255',
            'guests.*.phone' => 'nullable|string|max:20',
            'guests.*.relationship' => 'required|string|max:100',
            // ID fields made optional for backward compatibility
            'guests.*.id_type' => 'nullable|in:aadhar_card,driving_license,passport,voter_id',
            'guests.*.id_number' => 'nullable|string|max:50',
            'primary_contact_mobile' => 'nullable|string|max:20', // Made optional per new requirements
            'visit_date' => 'required|date',
            'check_in_time' => 'nullable|string|date_format:H:i',
            'check_out_time' => 'nullable|string|date_format:H:i',
            'purpose_to_visit' => 'required|string|max:500',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $idempotencyKey = $validated['idempotency_key'] ?? Str::uuid()->toString();
            
            // Check for duplicate
            $existingGuestEntry = GuestEntry::where('idempotency_key', $idempotencyKey)->first();
            if ($existingGuestEntry) {
                return response()->json([
                    'data' => [
                        'id' => (string) $existingGuestEntry->id,
                        'unique_id' => $existingGuestEntry->unique_id,
                        'message' => 'Guest entry request already created',
                    ],
                ], Response::HTTP_OK);
            }

            // Provide default times if not provided (database requires time values)
            $checkInTime = $validated['check_in_time'] ?? '09:00';
            $checkOutTime = $validated['check_out_time'] ?? '18:00';
            
            $guestEntry = GuestEntry::create([
                'tenant_id' => $user->tenant_id,
                'student_id' => $user->student->id,
                'hostel_id' => $user->student->hostel_id ?? null,
                'title' => 'Guest Visit',
                'description' => $validated['purpose_to_visit'],
                'guests' => $validated['guests'],
                'primary_contact_mobile' => $validated['primary_contact_mobile'] ?? $user->phone, // Use student phone if not provided
                'visit_date' => $validated['visit_date'],
                'check_in_time' => $checkInTime,
                'check_out_time' => $checkOutTime,
                'purpose_to_visit' => $validated['purpose_to_visit'],
                'status' => 'pending', // Will go to rector for approval
                'submitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            // Notify Rector about new guest entry (push + in-app notification via PushNotifier)
            try {
                if ($user->tenant_id && $user->student->hostel_id) {
                    /** @var NotificationRecipients $recipients */
                    $recipients = app(NotificationRecipients::class);
                    /** @var PushNotifier $push */
                    $push = app(PushNotifier::class);

                    $rector = $recipients->rectorForHostel((string) $user->tenant_id, (int) $user->student->hostel_id);

                    if ($rector) {
                        $visitDate = Carbon::parse($validated['visit_date'])->format('d M Y');

                        $push->toUserTemplate(
                            $rector->id,
                            'rector.guest_entry_submitted',
                            [
                                'student_name' => $user->name,
                                'visit_date'   => $visitDate,
                            ],
                            [
                                'screen'        => 'Notifications',
                                'type'          => 'guest_entry_submitted',
                                'guestentry_id' => (string) $guestEntry->id,
                            ]
                        );
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('GuestEntryController::store - failed to send rector guest_entry_submitted notification', [
                    'error' => $e->getMessage(),
                    'guest_entry_id' => $guestEntry->id ?? null,
                    'student_id' => $user->student->id,
                ]);
            }

            return response()->json([
                'message' => 'Guest entry request created successfully',
                'data' => [
                    'id' => (string) $guestEntry->id,
                    'unique_id' => $guestEntry->unique_id,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create guest entry request', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'tenant_id' => $user->tenant_id,
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/create_failed',
                'title' => 'Create Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create guest entry request. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get specific guest entry details
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
            $guestEntry = GuestEntry::where('id', $id)
                ->where('student_id', $user->student->id)
                ->firstOrFail();

            return response()->json([
                'data' => [
                    'id' => (string) $guestEntry->id,
                    'unique_id' => $guestEntry->unique_id,
                    'title' => $guestEntry->title,
                    'description' => $guestEntry->description,
                    'guests' => $guestEntry->guests,
                    'primary_contact_mobile' => $guestEntry->primary_contact_mobile,
                    'visit_date' => $guestEntry->visit_date->format('Y-m-d'),
                    // Times are stored as plain strings (HH:MM), so return them directly
                    'check_in_time' => $guestEntry->check_in_time,
                    'check_out_time' => $guestEntry->check_out_time,
                    'purpose_to_visit' => $guestEntry->purpose_to_visit,
                    'status' => $guestEntry->status,
                    'rejection_reason' => $guestEntry->rejection_reason,
                    'submitted_date' => $guestEntry->submitted_at->format('Y-m-d H:i:s'),
                    'created_at' => $guestEntry->created_at->toIso8601String(),
                ],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Guest entry request not found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch guest entry details', [
                'error' => $e->getMessage(),
                'guest_entry_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve guest entry details. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

