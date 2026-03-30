<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\LaundryRequest;
use App\Enums\LaundryRequestStatus;
use App\Enums\LaundryServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaundryController extends Controller
{
    public function raiseRequest(Request $request): JsonResponse
    {
        try {
            \Log::error('RAISE REQUEST METHOD CALLED!!!');
            \Log::error('REQUEST DATA DEBUG: ' . json_encode($request->all()));
            \Log::info('Mobile LaundryController::raiseRequest called', [
                'user_id' => auth()->user()?->id,
                'request_data' => $request->all(),
            ]);

            $user = auth()->user();

            if (!$user) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/unauthenticated',
                    'title' => 'Unauthenticated',
                    'status' => 401,
                    'detail' => 'Authentication required. Please log in again.',
                ], 401);
            }

            // For students: create request for themselves
            if ($user->student) {
                $studentId = $user->student->id;
            } 
            // For staff (Laundry Manager): create request on behalf of student
            else {
                // Validate that user has permission to raise requests
                $hasLaundryRole = $user->hasAnyRole(['Laundry Manager', 'Campus Manager', 'Super Admin']);

                if (!$hasLaundryRole) {
                    return response()->json([
                        'type' => 'https://map-hms.dev/errors/forbidden',
                        'title' => 'Forbidden',
                        'status' => 403,
                        'detail' => 'Insufficient permissions to raise laundry requests.',
                    ], 403);
                }

                // For staff, validate basic request data
                $rules = [
                    'service_type' => 'required|string',
                    'bag_count' => 'nullable|integer|min:1',
                    'weight_kg' => 'nullable|numeric|min:0.1',
                ];
                
                // If student_id is provided, validate it exists
                if ($request->filled('student_id')) {
                    $rules['student_id'] = 'exists:students,id';
                } else {
                    // Try to find student by MAP ID if student_id not provided
                    if ($request->filled('map_student_id')) {
                        $mapStudentId = trim($request->map_student_id);
                        $tenantId = $user->tenant_id;
                        
                        \Log::info('Searching for student by MAP ID', [
                            'map_student_id' => $mapStudentId,
                            'tenant_id' => $tenantId,
                            'user_id' => $user->id,
                        ]);
                        
                        // First, try exact match (case-insensitive) with tenant filter
                        $student = \App\Models\Student::where('tenant_id', $tenantId)
                            ->whereRaw('LOWER(TRIM(map_student_id)) = LOWER(TRIM(?))', [$mapStudentId])
                            ->whereHas('user', function($query) {
                                $query->where('archived', false)
                                      ->where('is_active', true);
                            })
                            ->first();
                        
                        // If not found, try without user filter (in case user is inactive but student exists)
                        if (!$student) {
                            \Log::warning('Student not found with active user, trying without user filter', [
                                'map_student_id' => $mapStudentId,
                            ]);
                            
                            $student = \App\Models\Student::where('tenant_id', $tenantId)
                                ->whereRaw('LOWER(TRIM(map_student_id)) = LOWER(TRIM(?))', [$mapStudentId])
                                ->first();
                        }
                        
                        // If still not found, try searching by student_uid as fallback
                        if (!$student) {
                            \Log::info('Trying to find by student_uid as fallback', [
                                'map_student_id' => $mapStudentId,
                            ]);
                            
                            $student = \App\Models\Student::where('tenant_id', $tenantId)
                                ->whereRaw('LOWER(TRIM(student_uid)) = LOWER(TRIM(?))', [$mapStudentId])
                                ->whereHas('user', function($query) {
                                    $query->where('archived', false)
                                          ->where('is_active', true);
                                })
                                ->first();
                        }
                        
                        if ($student) {
                            $studentId = $student->id;
                            \Log::info('Student found by MAP ID search', [
                                'student_id' => $studentId,
                                'student_name' => $student->user->name ?? 'N/A',
                                'map_student_id' => $student->map_student_id ?? 'N/A',
                                'student_uid' => $student->student_uid ?? 'N/A',
                                'searched_value' => $mapStudentId,
                                'tenant_id' => $tenantId,
                            ]);
                        } else {
                            // Log all students with similar MAP IDs for debugging
                            $similarStudents = \App\Models\Student::where('tenant_id', $tenantId)
                                ->whereRaw('LOWER(map_student_id) LIKE ?', ['%' . strtolower($mapStudentId) . '%'])
                                ->limit(5)
                                ->get(['id', 'map_student_id', 'student_uid']);
                            
                            \Log::warning('Student not found by MAP ID search', [
                                'map_student_id' => $mapStudentId,
                                'tenant_id' => $tenantId,
                                'user_id' => $user->id,
                                'similar_students' => $similarStudents->toArray(),
                            ]);
                            
                            return response()->json([
                                'type' => 'https://map-hms.dev/errors/validation_error',
                                'title' => 'Validation Error',
                                'status' => 422,
                                'detail' => 'Student not found. Please provide a valid MAP Student ID or Student ID.',
                                'errors' => [
                                    'map_student_id' => ['Student not found with MAP ID: ' . $mapStudentId],
                                    'service_type' => ['Service type is required (e.g., "standard", "express", "dry_cleaning").'],
                                ],
                                'debug' => [
                                    'user_type' => 'staff',
                                    'required_fields' => ['service_type', 'student_id OR map_student_id'],
                                    'received_data' => $request->all(),
                                    'tenant_id' => $tenantId,
                                    'searched_map_id' => $mapStudentId,
                                ]
                            ], 422);
                        }
                    } else {
                        // If no student_id or map_student_id provided, return an error asking for it
                        return response()->json([
                            'type' => 'https://map-hms.dev/errors/validation_error',
                            'title' => 'Validation Error',
                            'status' => 422,
                            'detail' => 'Student ID or MAP Student ID is required when raising laundry requests on behalf of students.',
                            'errors' => [
                                'student_id' => ['Student ID is required for staff users to raise laundry requests.'],
                                'map_student_id' => ['MAP Student ID is required if Student ID is not provided.'],
                                'service_type' => ['Service type is required (e.g., "standard", "express", "dry_cleaning").'],
                            ],
                            'debug' => [
                                'user_type' => 'staff',
                                'required_fields' => ['service_type', 'student_id OR map_student_id'],
                                'received_data' => $request->all(),
                            ]
                        ], 422);
                    }
                }
                
                // Set default service_type if not provided
                if (!$request->filled('service_type')) {
                    $request->merge(['service_type' => 'wash_only']);
                }
                
                // Validate service_type is a valid enum value
                $validServiceTypes = array_map(fn($type) => $type->value, LaundryServiceType::cases());
                if (!in_array($request->service_type, $validServiceTypes)) {
                    return response()->json([
                        'type' => 'https://map-hms.dev/errors/validation_error',
                        'title' => 'Validation Error',
                        'status' => 422,
                        'detail' => 'Invalid service type. Valid types: ' . implode(', ', $validServiceTypes),
                        'errors' => [
                            'service_type' => ['Invalid service type. Must be one of: ' . implode(', ', $validServiceTypes)],
                        ],
                    ], 422);
                }
                
                $request->validate($rules);
                
                // Get student_id after validation
                $studentId = $studentId ?? $request->student_id;
            }

            // Validate tenant
            $tenantId = $user->tenant_id;
            if (!$tenantId) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/tenant_required',
                    'title' => 'Tenant Required',
                    'status' => 403,
                    'detail' => 'User must be associated with a tenant.',
                ], 403);
            }

            // Create the laundry request
            // Handle both 'bag_count' and 'item_count' from frontend (item_count is used in mobile app)
            $bagCount = $request->bag_count ?? $request->item_count ?? 1;
            
            // Get student to retrieve hostel_id
            $student = \App\Models\Student::find($studentId);
            
            if (!$student) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Student Not Found',
                    'status' => 404,
                    'detail' => 'Student not found.',
                ], 404);
            }
            
            // Prepare metadata
            $metadata = [
                'room_number' => $request->room_number ?? null,
                'raised_by_manager' => true,
                'manager_id' => $user->id,
                'manager_name' => $user->name,
            ];
            
            // Create laundry request with tenant_id
            $laundryRequestData = [
                'student_id' => $studentId,
                'hostel_id' => $student->hostel_id,
                'service_type' => $request->service_type ?? 'wash_only',
                'bag_count' => $bagCount,
                'weight_kg' => $request->weight_kg,
                'notes' => $request->special_instructions ?? $request->description ?? null,
                'status' => LaundryRequestStatus::PENDING,
                'requested_at' => now(),
                'initiated_by_user_id' => $user->id,
                'metadata' => $metadata,
            ];
            
            // Set tenant_id directly if column exists (bypassing fillable)
            $laundryRequest = new LaundryRequest($laundryRequestData);
            $laundryRequest->tenant_id = $tenantId;
            $laundryRequest->save();

            return response()->json([
                'data' => [
                    'id' => $laundryRequest->id,
                    'student_id' => $laundryRequest->student_id,
                    'service_type' => $laundryRequest->service_type,
                    'bag_count' => $laundryRequest->bag_count,
                    'weight_kg' => $laundryRequest->weight_kg,
                    'status' => $laundryRequest->status,
                    'requested_at' => $laundryRequest->requested_at?->toIso8601String(),
                    'created_at' => $laundryRequest->created_at->toIso8601String(),
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_error',
                'title' => 'Validation Error',
                'status' => 422,
                'detail' => 'Invalid input data.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('LaundryController::raiseRequest - Exception', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? 'unknown',
                'tenant_id' => $user->tenant_id ?? 'unknown',
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'Failed to create laundry request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function getRequests(Request $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        try {
            if ($user->student) {
                // Student can only see their own requests
                $query = LaundryRequest::where("student_id", $user->student->id)
                    ->with(["student.user", "cycle"]);
            } else {
                // Staff user - check tenant and roles
                $tenantId = $user->tenant_id;

                if (!$tenantId) {
                    return response()->json([
                        'type' => 'https://map-hms.dev/errors/tenant_required',
                        'title' => 'Tenant Required',
                        'status' => 403,
                        'detail' => 'Staff user must be associated with a tenant.',
                    ], 403);
                }

                // Check if user has appropriate staff roles for laundry management
                $hasLaundryRole = $user->hasAnyRole(['Laundry Manager', 'Campus Manager', 'Super Admin']);

                if (!$hasLaundryRole) {
                    return response()->json([
                        'type' => 'https://map-hms.dev/errors/forbidden',
                        'title' => 'Forbidden',
                        'status' => 403,
                        'detail' => 'Insufficient permissions to access laundry requests.',
                    ], 403);
                }

                $query = LaundryRequest::where("tenant_id", $tenantId)
                    ->with(["student.user", "cycle", "initiatedBy"]);

                // Filter by status if provided
                if ($request->filled("status")) {
                    $statuses = explode(",", $request->status);
                    // Filter out empty strings and convert to array
                    $statuses = array_filter(array_map('trim', $statuses));
                    if (!empty($statuses)) {
                        // Use string comparison since database stores as string
                        $query->whereIn("status", $statuses);
                    }
                }
            }

            $limit = $request->integer("limit", 100);
            $query->limit($limit)->orderBy("created_at", "desc");
            $requests = $query->get();

            $transformedRequests = $requests->map(function ($request) {
                return [
                    "id" => $request->id,
                    "student_name" => $request->student?->user?->name ?? "Unknown Student",
                    "student_id" => $request->student_id,
                    "service_type" => $request->service_type instanceof \BackedEnum ? $request->service_type->value : $request->service_type,
                    "bag_count" => $request->bag_count,
                    "weight_kg" => $request->weight_kg,
                    "status" => $request->status instanceof \BackedEnum ? $request->status->value : $request->status,
                    "pickup_code" => $request->pickup_code,
                    "requested_at" => $request->requested_at?->toIso8601String(),
                    "picked_up_at" => $request->picked_up_at?->toIso8601String(),
                    "ready_at" => $request->ready_at?->toIso8601String(),
                    "delivered_at" => $request->delivered_at?->toIso8601String(),
                    "completed_at" => $request->completed_at?->toIso8601String(),
                    "total_amount" => $request->total_amount,
                    "payment_status" => $request->payment_status,
                    "laundry_cycle_name" => $request->cycle?->name ?? $request->laundryCycle?->name ?? null,
                    "created_at" => $request->created_at->toIso8601String(),
                    "updated_at" => $request->updated_at->toIso8601String(),
                    "is_delayed" => $request->isDelayed(),
                ];
            });

            return response()->json(["data" => $transformedRequests], 200);

        } catch (\Exception $e) {
            \Log::error('LaundryController::getRequests - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $user->id ?? 'unknown',
                'tenant_id' => $user->tenant_id ?? 'unknown',
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'Failed to retrieve laundry requests: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        try {
            \Log::info('LaundryController::show called', [
                'request_id' => $id,
                'user_id' => $user->id,
                'user_type' => $user->student ? 'student' : 'staff',
                'tenant_id' => $user->tenant_id,
            ]);

            $query = LaundryRequest::with(["student.user", "cycle", "initiatedBy", "hostel"]);

            if ($user->student) {
                $laundryRequest = $query->where("student_id", $user->student->id)
                    ->where("id", $id)
                    ->first();
                
                \Log::info('Student query result', [
                    'request_id' => $id,
                    'student_id' => $user->student->id,
                    'found' => $laundryRequest ? true : false,
                ]);
            } else {
                $tenantId = $user->tenant_id;
                if (!$tenantId) {
                    return response()->json([
                        'type' => 'https://map-hms.dev/errors/tenant_required',
                        'title' => 'Tenant Required',
                        'status' => 403,
                        'detail' => 'Staff user must be associated with a tenant.',
                    ], 403);
                }

                $laundryRequest = $query->where("tenant_id", $tenantId)
                    ->where("id", $id)
                    ->first();
                
                \Log::info('Staff query result', [
                    'request_id' => $id,
                    'tenant_id' => $tenantId,
                    'found' => $laundryRequest ? true : false,
                ]);
            }

            if (!$laundryRequest) {
                \Log::warning('Laundry request not found', [
                    'request_id' => $id,
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                ]);
                
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Laundry request not found.',
                ], 404);
            }

            // Transform to match frontend expectations
            $data = [
                "id" => $laundryRequest->id,
                "student_name" => $laundryRequest->student?->user?->name ?? "Unknown Student",
                "student_id" => $laundryRequest->student_id,
                "service_type" => $laundryRequest->service_type instanceof \BackedEnum ? $laundryRequest->service_type->value : $laundryRequest->service_type,
                "bag_count" => $laundryRequest->bag_count,
                "weight_kg" => $laundryRequest->weight_kg,
                "status" => $laundryRequest->status instanceof \BackedEnum ? $laundryRequest->status->value : $laundryRequest->status,
                "pickup_code" => $laundryRequest->pickup_code,
                "requested_at" => $laundryRequest->requested_at?->toIso8601String(),
                "picked_up_at" => $laundryRequest->picked_up_at?->toIso8601String(),
                "ready_at" => $laundryRequest->ready_at?->toIso8601String(),
                "delivered_at" => $laundryRequest->delivered_at?->toIso8601String(),
                "completed_at" => $laundryRequest->completed_at?->toIso8601String(),
                "notes" => $laundryRequest->notes,
                "total_amount" => $laundryRequest->total_amount,
                "payment_status" => $laundryRequest->payment_status,
                "laundry_cycle_name" => $laundryRequest->cycle?->name ?? null,
                "verification" => [
                    "requires_manual_verify" => $laundryRequest->requiresManualVerify(),
                ],
                "created_at" => $laundryRequest->created_at->toIso8601String(),
                "updated_at" => $laundryRequest->updated_at->toIso8601String(),
                "is_delayed" => $laundryRequest->isDelayed(),
            ];

            return response()->json(["data" => $data], 200);

        } catch (\Exception $e) {
            \Log::error('LaundryController::show - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'Failed to retrieve laundry request: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function verifyCode(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        // Only laundry managers can verify codes
        $hasLaundryRole = $user->hasAnyRole(['Laundry Manager', 'Campus Manager', 'Super Admin']);
        if (!$hasLaundryRole) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Insufficient permissions to verify pickup codes.',
            ], 403);
        }

        try {
            $request->validate([
                'pickup_code' => 'required|string|size:4',
            ]);

            $tenantId = $user->tenant_id;
            $laundryRequest = LaundryRequest::where("tenant_id", $tenantId)
                ->where("id", $id)
                ->first();

            if (!$laundryRequest) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Laundry request not found.',
                ], 404);
            }

            // Verify the code
            if ($laundryRequest->pickup_code !== $request->pickup_code) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/validation_error',
                    'title' => 'Invalid Code',
                    'status' => 422,
                    'detail' => 'Invalid pickup code. Please check and try again.',
                ], 422);
            }

            // Check if request is in READY status
            if ($laundryRequest->status !== LaundryRequestStatus::READY) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/validation_error',
                    'title' => 'Invalid Status',
                    'status' => 422,
                    'detail' => 'Laundry request is not ready for pickup. Current status: ' . $laundryRequest->status->value,
                ], 422);
            }

            // Move READY -> DELIVERED -> COMPLETED so we respect enum transitions.
            $delivered = $laundryRequest->transitionTo(LaundryRequestStatus::DELIVERED, 'Pickup code verified by ' . $user->name);
            if (! $delivered) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/internal_error',
                    'title' => 'Status Update Failed',
                    'status' => 500,
                    'detail' => 'Failed to mark laundry request as delivered.',
                ], 500);
            }

            $completed = $laundryRequest->transitionTo(LaundryRequestStatus::COMPLETED, 'Laundry pickup completed by ' . $user->name);
            if (! $completed) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/internal_error',
                    'title' => 'Status Update Failed',
                    'status' => 500,
                    'detail' => 'Failed to complete laundry request after verification.',
                ], 500);
            }

            return response()->json([
                'data' => [
                    'id' => $laundryRequest->id,
                    'status' => $laundryRequest->status->value,
                    'message' => 'Pickup code verified successfully. Laundry request marked as completed.',
                ]
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_error',
                'title' => 'Validation Error',
                'status' => 422,
                'detail' => 'Invalid input data.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('LaundryController::verifyCode - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'Failed to verify pickup code: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function manualVerify(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if (! $user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        if (! $user->hasAnyRole(['Laundry Manager', 'Campus Manager', 'Super Admin'])) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Insufficient permissions to manually verify laundry requests.',
            ], 403);
        }

        $request->validate([
            'verify_notes' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $tenantId = $user->tenant_id;
        $laundryRequest = LaundryRequest::where("tenant_id", $tenantId)
            ->where("id", $id)
            ->first();

        if (! $laundryRequest) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'Laundry request not found.',
            ], 404);
        }

        if (! $laundryRequest->requiresManualVerify()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_state',
                'title' => 'Manual Verification Not Required',
                'status' => 422,
                'detail' => 'Request must be in ready status for manual verification.',
            ], 422);
        }

        $verified = $laundryRequest->manualVerify($request->string('verify_notes')->toString());
        if (! $verified) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/verification_failed',
                'title' => 'Manual Verification Failed',
                'status' => 500,
                'detail' => 'Unable to manually verify this request.',
            ], 500);
        }

        // Bring request to completed state for lifecycle parity with code verification.
        $completed = $laundryRequest->transitionTo(
            LaundryRequestStatus::COMPLETED,
            'Manual verification completed by ' . $user->name
        );

        if (! $completed) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/status_update_failed',
                'title' => 'Completion Failed',
                'status' => 500,
                'detail' => 'Manual verification succeeded, but completion failed.',
            ], 500);
        }

        return response()->json([
            'data' => [
                'id' => $laundryRequest->id,
                'status' => $laundryRequest->status->value,
                'manual_verify_notes' => $laundryRequest->manual_verify_notes,
                'message' => 'Laundry request manually verified and marked as completed.',
            ],
        ], 200);
    }

    /**
     * Update laundry request status (e.g. collected, washing, drying).
     * Only allowed transitions per LaundryRequestStatus are accepted.
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        $hasLaundryRole = $user->hasAnyRole(['Laundry Manager', 'Campus Manager', 'Super Admin']);
        if (!$hasLaundryRole) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Insufficient permissions to update laundry request status.',
            ], 403);
        }

        $validStatuses = array_map(fn($s) => $s->value, LaundryRequestStatus::cases());
        $request->validate([
            'status' => ['required', 'string', \Illuminate\Validation\Rule::in($validStatuses)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $tenantId = $user->tenant_id;
        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => 403,
                'detail' => 'Staff user must be associated with a tenant.',
            ], 403);
        }

        $laundryRequest = LaundryRequest::where('tenant_id', $tenantId)->where('id', $id)->first();
        if (!$laundryRequest) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => 404,
                'detail' => 'Laundry request not found.',
            ], 404);
        }

        $newStatus = LaundryRequestStatus::from($request->string('status')->toString());
        if (!$laundryRequest->status->canTransitionTo($newStatus)) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/invalid_transition',
                'title' => 'Invalid Status Transition',
                'status' => 422,
                'detail' => "Cannot transition from {$laundryRequest->status->value} to {$newStatus->value}",
                'current_status' => $laundryRequest->status->value,
                'requested_status' => $newStatus->value,
            ], 422);
        }

        $notes = $request->input('notes');
        $success = $laundryRequest->transitionTo($newStatus, is_string($notes) ? $notes : null);
        if (!$success) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/status_update_failed',
                'title' => 'Status Update Failed',
                'status' => 500,
                'detail' => 'Failed to update laundry request status.',
            ], 500);
        }

        $laundryRequest->load(['student.user', 'cycle', 'initiatedBy', 'hostel']);
        $data = [
            'id' => $laundryRequest->id,
            'student_name' => $laundryRequest->student?->user?->name ?? 'Unknown Student',
            'student_id' => $laundryRequest->student_id,
            'service_type' => $laundryRequest->service_type instanceof \BackedEnum ? $laundryRequest->service_type->value : $laundryRequest->service_type,
            'bag_count' => $laundryRequest->bag_count,
            'weight_kg' => $laundryRequest->weight_kg,
            'status' => $laundryRequest->status instanceof \BackedEnum ? $laundryRequest->status->value : $laundryRequest->status,
            'pickup_code' => $laundryRequest->pickup_code,
            'requested_at' => $laundryRequest->requested_at?->toIso8601String(),
            'picked_up_at' => $laundryRequest->picked_up_at?->toIso8601String(),
            'ready_at' => $laundryRequest->ready_at?->toIso8601String(),
            'delivered_at' => $laundryRequest->delivered_at?->toIso8601String(),
            'completed_at' => $laundryRequest->completed_at?->toIso8601String(),
            'notes' => $laundryRequest->notes,
            'total_amount' => $laundryRequest->total_amount,
            'payment_status' => $laundryRequest->payment_status,
            'laundry_cycle_name' => $laundryRequest->cycle?->name ?? null,
            'created_at' => $laundryRequest->created_at->toIso8601String(),
            'updated_at' => $laundryRequest->updated_at->toIso8601String(),
        ];

        return response()->json(['data' => $data, 'message' => 'Status updated successfully.'], 200);
    }

    public function markReadyForPickup(Request $request, $id): JsonResponse
    {
        $user = auth()->user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthenticated',
                'title' => 'Unauthenticated',
                'status' => 401,
                'detail' => 'Authentication required. Please log in again.',
            ], 401);
        }

        // Only laundry managers can mark requests as ready
        $hasLaundryRole = $user->hasAnyRole(['Laundry Manager', 'Campus Manager', 'Super Admin']);
        if (!$hasLaundryRole) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => 403,
                'detail' => 'Insufficient permissions to mark requests as ready.',
            ], 403);
        }

        try {
            $request->validate([
                'notes' => 'nullable|string|max:500',
            ]);

            $tenantId = $user->tenant_id;
            if (!$tenantId) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/tenant_required',
                    'title' => 'Tenant Required',
                    'status' => 403,
                    'detail' => 'Staff user must be associated with a tenant.',
                ], 403);
            }

            $laundryRequest = LaundryRequest::where("tenant_id", $tenantId)
                ->where("id", $id)
                ->first();

            if (!$laundryRequest) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => 404,
                    'detail' => 'Laundry request not found.',
                ], 404);
            }

            // Transition to READY status
            // This will automatically generate pickup code and send notification
            $success = $laundryRequest->transitionTo(
                LaundryRequestStatus::READY,
                $request->input('notes', 'Marked ready for pickup by laundry manager')
            );

            if (!$success) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/invalid_transition',
                    'title' => 'Cannot Mark as Ready',
                    'status' => 422,
                    'detail' => 'Request cannot be marked as ready in its current status: ' . $laundryRequest->status->value,
                    'current_status' => $laundryRequest->status->value,
                ], 422);
            }

            // Reload with relationships
            $laundryRequest->load(["student.user", "cycle", "initiatedBy", "hostel"]);

            // Return updated request
            $data = [
                "id" => $laundryRequest->id,
                "student_name" => $laundryRequest->student?->user?->name ?? "Unknown Student",
                "student_id" => $laundryRequest->student_id,
                "service_type" => $laundryRequest->service_type instanceof \BackedEnum ? $laundryRequest->service_type->value : $laundryRequest->service_type,
                "bag_count" => $laundryRequest->bag_count,
                "weight_kg" => $laundryRequest->weight_kg,
                "status" => $laundryRequest->status instanceof \BackedEnum ? $laundryRequest->status->value : $laundryRequest->status,
                "pickup_code" => $laundryRequest->pickup_code,
                "requested_at" => $laundryRequest->requested_at?->toIso8601String(),
                "picked_up_at" => $laundryRequest->picked_up_at?->toIso8601String(),
                "ready_at" => $laundryRequest->ready_at?->toIso8601String(),
                "delivered_at" => $laundryRequest->delivered_at?->toIso8601String(),
                "completed_at" => $laundryRequest->completed_at?->toIso8601String(),
                "notes" => $laundryRequest->notes,
                "total_amount" => $laundryRequest->total_amount,
                "payment_status" => $laundryRequest->payment_status,
                "laundry_cycle_name" => $laundryRequest->cycle?->name ?? null,
                "created_at" => $laundryRequest->created_at->toIso8601String(),
                "updated_at" => $laundryRequest->updated_at->toIso8601String(),
            ];

            return response()->json([
                'data' => $data,
                'message' => 'Laundry request marked as ready for pickup. Pickup code generated and notification sent to student.',
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_error',
                'title' => 'Validation Error',
                'status' => 422,
                'detail' => 'Invalid input data.',
                'errors' => $e->errors(),
            ], 422);

        } catch (\Exception $e) {
            \Log::error('LaundryController::markReadyForPickup - Exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_error',
                'title' => 'Internal Server Error',
                'status' => 500,
                'detail' => 'Failed to mark request as ready: ' . $e->getMessage(),
            ], 500);
        }
    }
}
