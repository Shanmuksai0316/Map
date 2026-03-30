<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Domain\SickLeaves\Models\SickLeave;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class SickLeaveController extends Controller
{
    /**
     * Get student's own sick leaves
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
                Log::warning('SickLeaveController: No tenant_id found for user', [
                    'user_id' => $user->id,
                    'student_id' => $user->student->id,
                ]);
            }

            $query = SickLeave::where('student_id', $user->student->id);
            
            // Filter by tenant_id if available
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $sickLeaves = $query->latest('submitted_at')
                ->get()
                ->map(function ($sickLeave) {
                    return [
                        'id' => (string) $sickLeave->id,
                        'unique_id' => $sickLeave->unique_id,
                        'title' => $sickLeave->title,
                        'description' => $sickLeave->description,
                        'illness' => $sickLeave->illness,
                        'illness_details' => $sickLeave->illness_details,
                        'need_medical_attention' => $sickLeave->need_medical_attention,
                        'contact_parents' => $sickLeave->contact_parents,
                        'status' => $sickLeave->status,
                        'rejection_reason' => $sickLeave->rejection_reason,
                        'submitted_date' => $sickLeave->submitted_at->format('Y-m-d H:i:s'),
                        'created_at' => $sickLeave->created_at->toIso8601String(),
                    ];
                });

            return response()->json([
                'data' => $sickLeaves,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student sick leaves', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve sick leaves. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create new sick leave request
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        
        // Log authentication status for debugging
        if (!$user) {
            Log::error('SickLeaveController::store - User not authenticated', [
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
                'detail' => 'Only students can create sick leaves.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'illness' => 'required|string|max:255',
            'illness_details' => 'required|string|max:1000',
            'need_medical_attention' => 'required|boolean',
            'contact_parents' => 'required|boolean',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        try {
            $idempotencyKey = $validated['idempotency_key'] ?? Str::uuid()->toString();
            
            // Get tenant ID from user
            $tenantId = $user->tenant_id;
            
            if (!$tenantId) {
                Log::warning('SickLeaveController: No tenant_id found for user when creating sick leave', [
                    'user_id' => $user->id,
                    'student_id' => $user->student->id,
                ]);
            }

            // Check for duplicate using idempotency key (scoped to tenant if available)
            $duplicateQuery = SickLeave::where('idempotency_key', $idempotencyKey);
            if ($tenantId) {
                $duplicateQuery->where('tenant_id', $tenantId);
            }
            $existingSickLeave = $duplicateQuery->first();
            
            if ($existingSickLeave) {
                return response()->json([
                    'data' => [
                        'id' => (string) $existingSickLeave->id,
                        'unique_id' => $existingSickLeave->unique_id,
                        'message' => 'Sick leave request already created',
                    ],
                ], Response::HTTP_OK);
            }

            $sickLeave = SickLeave::create([
                'tenant_id' => $tenantId,
                'student_id' => $user->student->id,
                'hostel_id' => $user->student->hostel_id ?? null,
                'title' => $validated['title'],
                'description' => $validated['illness_details'],
                'illness' => $validated['illness'],
                'illness_details' => $validated['illness_details'],
                'need_medical_attention' => $validated['need_medical_attention'],
                'contact_parents' => $validated['contact_parents'],
                'status' => 'pending',
                'submitted_at' => now(),
                'idempotency_key' => $idempotencyKey,
            ]);

            return response()->json([
                'message' => 'Sick leave request created successfully',
                'data' => [
                    'id' => (string) $sickLeave->id,
                    'unique_id' => $sickLeave->unique_id,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            Log::error('Failed to create sick leave request', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/create_failed',
                'title' => 'Create Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to create sick leave request. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get specific sick leave details
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
            
            $query = SickLeave::where('id', $id)
                ->where('student_id', $user->student->id);
            
            // Filter by tenant_id if available
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
            }
            
            $sickLeave = $query->firstOrFail();

            return response()->json([
                'data' => [
                    'id' => (string) $sickLeave->id,
                    'unique_id' => $sickLeave->unique_id,
                    'title' => $sickLeave->title,
                    'description' => $sickLeave->description,
                    'illness' => $sickLeave->illness,
                    'illness_details' => $sickLeave->illness_details,
                    'need_medical_attention' => $sickLeave->need_medical_attention,
                    'contact_parents' => $sickLeave->contact_parents,
                    'status' => $sickLeave->status,
                    'rejection_reason' => $sickLeave->rejection_reason,
                    'submitted_date' => $sickLeave->submitted_at->format('Y-m-d H:i:s'),
                    'created_at' => $sickLeave->created_at->toIso8601String(),
                ],
            ], Response::HTTP_OK);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/not_found',
                'title' => 'Not Found',
                'status' => Response::HTTP_NOT_FOUND,
                'detail' => 'Sick leave request not found.',
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            Log::error('Failed to fetch sick leave details', [
                'error' => $e->getMessage(),
                'sick_leave_id' => $id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve sick leave details. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

