<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GatePassScan;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class GatePassController extends Controller
{
    /**
     * Verify a QR gate pass.
     */
    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'qr_data' => 'required|string',
            'scan_type' => 'required|string|in:entry,exit',
            'gate_location' => 'sometimes|string|max:255',
            'device_id' => 'sometimes|string|max:255',
        ]);

        $user = $request->user();
        $tenantId = tenancy()->tenant?->id;

        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'This action requires a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Decrypt and validate QR data
            $qrMetadata = $this->decryptQrData($validated['qr_data']);
            
            // Validate QR data structure
            $this->validateQrData($qrMetadata);
            
            // Check if student exists and is active
            $student = $this->validateStudent($qrMetadata['student_id'], $tenantId);
            
            // Check for duplicate scans
            $this->checkDuplicateScan($student->id, $validated['scan_type'], $tenantId);
            
            // Create scan record
            $scan = GatePassScan::createScan(
                $tenantId,
                $student->id,
                $user->id,
                $validated['qr_data'],
                $validated['scan_type'],
                $qrMetadata,
                $validated['gate_location'] ?? null,
                $validated['device_id'] ?? null
            );

            Log::info('Gate pass verified successfully', [
                'scan_id' => $scan->scan_id,
                'student_id' => $student->id,
                'scan_type' => $validated['scan_type'],
                'scanned_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Gate pass verified successfully',
                'data' => [
                    'scan_id' => $scan->scan_id,
                    'student' => [
                        'id' => $student->id,
                        'name' => $student->name,
                        'student_uid' => $student->student_uid,
                        'hostel' => $student->hostel_name,
                    ],
                    'scan_type' => $validated['scan_type'],
                    'timestamp' => $scan->scan_timestamp,
                    'valid' => true,
                ],
            ], Response::HTTP_OK);

        } catch (ValidationException $e) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/validation_failed',
                'title' => 'Validation Failed',
                'status' => Response::HTTP_UNPROCESSABLE_ENTITY,
                'detail' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (\Exception $e) {
            Log::error('Gate pass verification failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/verification_failed',
                'title' => 'Verification Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Gate pass verification failed. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Record a gate pass scan.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scan_id' => 'required|string',
            'status' => 'required|string|in:success,failed',
            'error_message' => 'sometimes|string|max:500',
        ]);

        $user = $request->user();
        $tenantId = tenancy()->tenant?->id;

        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'This action requires a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $scan = GatePassScan::where('scan_id', $validated['scan_id'])
                ->where('tenant_id', $tenantId)
                ->where('scanned_by_user_id', $user->id)
                ->first();

            if (!$scan) {
                return response()->json([
                    'type' => 'https://map-hms.dev/errors/scan_not_found',
                    'title' => 'Scan Not Found',
                    'status' => Response::HTTP_NOT_FOUND,
                    'detail' => 'The specified scan record was not found.',
                ], Response::HTTP_NOT_FOUND);
            }

            if ($validated['status'] === 'failed') {
                $scan->markAsInvalid($validated['error_message'] ?? 'Scan failed');
            }

            Log::info('Gate pass scan recorded', [
                'scan_id' => $scan->scan_id,
                'status' => $validated['status'],
                'scanned_by' => $user->id,
            ]);

            return response()->json([
                'message' => 'Scan recorded successfully',
                'data' => [
                    'scan_id' => $scan->scan_id,
                    'status' => $validated['status'],
                    'timestamp' => now(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Gate pass scan recording failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/scan_recording_failed',
                'title' => 'Scan Recording Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to record scan. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get scan statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'days' => 'sometimes|integer|min:1|max:365',
        ]);

        $tenantId = tenancy()->tenant?->id;
        $days = $validated['days'] ?? 30;

        if (!$tenantId) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/tenant_required',
                'title' => 'Tenant Required',
                'status' => Response::HTTP_BAD_REQUEST,
                'detail' => 'This action requires a tenant context.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $stats = GatePassScan::getScanStats($tenantId, $days);

        return response()->json([
            'data' => $stats,
        ], Response::HTTP_OK);
    }

    /**
     * Decrypt QR data.
     */
    private function decryptQrData(string $encryptedData): array
    {
        try {
            $decrypted = Crypt::decryptString($encryptedData);
            return json_decode($decrypted, true);
        } catch (\Exception $e) {
            throw new ValidationException('Invalid QR code format.');
        }
    }

    /**
     * Validate QR data structure.
     */
    private function validateQrData(array $qrMetadata): void
    {
        $requiredFields = ['student_id', 'tenant_id', 'timestamp', 'signature'];
        
        foreach ($requiredFields as $field) {
            if (!isset($qrMetadata[$field])) {
                throw new ValidationException("Missing required field: {$field}");
            }
        }

        // Check timestamp validity (QR should not be older than 24 hours)
        $qrTimestamp = \Carbon\Carbon::createFromTimestamp($qrMetadata['timestamp']);
        if ($qrTimestamp->isBefore(now()->subDay())) {
            throw new ValidationException('QR code has expired.');
        }
    }

    /**
     * Validate student exists and is active.
     */
    private function validateStudent(int $studentId, string $tenantId): User
    {
        $student = User::where('id', $studentId)
            ->where('tenant_id', $tenantId)
            ->where('role', 'student')
            ->where('is_active', true)
            ->first();

        if (!$student) {
            throw new ValidationException('Student not found or inactive.');
        }

        return $student;
    }

    /**
     * Check for duplicate scans.
     */
    private function checkDuplicateScan(int $studentId, string $scanType, string $tenantId): void
    {
        $recentScan = GatePassScan::where('student_id', $studentId)
            ->where('tenant_id', $tenantId)
            ->where('scan_type', $scanType)
            ->where('scan_timestamp', '>=', now()->subMinutes(5))
            ->where('is_valid', true)
            ->first();

        if ($recentScan) {
            throw new ValidationException('Duplicate scan detected. Please wait before scanning again.');
        }
    }
}
