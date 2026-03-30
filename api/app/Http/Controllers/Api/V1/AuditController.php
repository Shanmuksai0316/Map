<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PiiRevealLog;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit Controller
 * 
 * Handles PII reveal requests with audit logging.
 */
class AuditController extends Controller
{
    /**
     * Reveal PII for a student
     * 
     * POST /api/v1/audit/pii/reveal
     *
     * Logs all PII access for audit purposes.
     */
    public function revealPii(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only Rector and Campus Manager can reveal PII
        if (!$user->hasAnyRole(['Rector', 'Campus Manager'])) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rector and Campus Manager can reveal PII.',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'pii_type' => ['required', 'string', 'in:phone,guardian,medical'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Invalid request parameters.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $studentId = $request->input('student_id');
        $piiType = $request->input('pii_type');

        try {
            // Get student
            $student = Student::findOrFail($studentId);

            // Get PII value based on type
            $piiValue = null;
            switch ($piiType) {
                case 'phone':
                    // Get phone from user
                    $userModel = $student->getUserFromCentral();
                    $piiValue = $userModel?->phone ?? null;
                    break;
                
                case 'guardian':
                    // Get guardian info (decrypted automatically by Laravel)
                    $guardianData = $student->guardian;
                    if (is_array($guardianData)) {
                        $piiValue = json_encode($guardianData);
                    } else {
                        $piiValue = $guardianData;
                    }
                    break;
                
                case 'medical':
                    // Get medical notes (decrypted automatically by Laravel)
                    $medicalData = $student->medical_notes;
                    if (is_array($medicalData)) {
                        $piiValue = json_encode($medicalData);
                    } else {
                        $piiValue = $medicalData;
                    }
                    break;
            }

            if (!$piiValue) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/not_found',
                    'title' => 'Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => 'PII data not found for this student.',
                ], Response::HTTP_NOT_FOUND);
            }

            // Log PII reveal for audit
            PiiRevealLog::create([
                'user_id' => $user->id,
                'student_id' => $studentId,
                'pii_type' => $piiType,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'revealed_at' => now(),
                'metadata' => [
                    'action' => 'reveal_pii',
                ],
            ]);

            Log::info('PII revealed', [
                'user_id' => $user->id,
                'student_id' => $studentId,
                'pii_type' => $piiType,
                'ip_address' => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'value' => $piiValue,
                    'pii_type' => $piiType,
                    'student_id' => $studentId,
                    'revealed_at' => now()->toISOString(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to reveal PII', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'student_id' => $studentId ?? null,
                'pii_type' => $piiType ?? null,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to reveal PII. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get PII reveal audit logs
     * 
     * GET /api/v1/audit/pii/logs
     * 
     * Only accessible by Rector and Campus Manager
     */
    public function getPiiLogs(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Only Rector and Campus Manager can view audit logs
        if (!$user->hasAnyRole(['Rector', 'Campus Manager'])) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/forbidden',
                'title' => 'Forbidden',
                'status' => Response::HTTP_FORBIDDEN,
                'detail' => 'Only Rector and Campus Manager can view audit logs.',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'student_id' => ['sometimes', 'integer', 'exists:students,id'],
            'pii_type' => ['sometimes', 'string', 'in:phone,guardian,medical'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => 'Invalid request parameters.',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $query = PiiRevealLog::query()
                ->with(['student:id,student_uid,user_id', 'user:id,name,phone'])
                ->orderBy('revealed_at', 'desc');

            if ($request->has('student_id')) {
                $query->where('student_id', $request->input('student_id'));
            }

            if ($request->has('pii_type')) {
                $query->where('pii_type', $request->input('pii_type'));
            }

            $limit = $request->input('limit', 50);
            $offset = $request->input('offset', 0);

            $logs = $query->limit($limit)->offset($offset)->get();
            $total = $query->count();

            return response()->json([
                'success' => true,
                'data' => $logs,
                'pagination' => [
                    'total' => $total,
                    'limit' => $limit,
                    'offset' => $offset,
                    'has_more' => ($offset + $limit) < $total,
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch PII logs', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/internal_server_error',
                'title' => 'Internal Server Error',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to fetch audit logs. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
