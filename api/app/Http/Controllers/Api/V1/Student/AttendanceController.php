<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class AttendanceController extends Controller
{
    /**
     * Get student's own attendance records
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
            $month = $request->input('month'); // Optional: YYYY-MM format
            
            $query = AttendanceLog::where('student_id', $user->student->id)
                ->orderBy('marked_at', 'desc');
            
            if ($month) {
                $query->whereYear('marked_at', substr($month, 0, 4))
                      ->whereMonth('marked_at', substr($month, 5, 2));
            }
            
            $attendance = $query->get()->map(function ($record) {
                return [
                    'id' => (string) $record->id,
                    'student_id' => (string) $record->student_id,
                    'date' => $record->marked_at?->toDateString(),
                    'status' => $record->status,
                    'marked_by' => $record->marker?->name ?? 'System',
                    'marked_at' => $record->marked_at?->toIso8601String(),
                    'notes' => $record->note,
                ];
            });

            return response()->json([
                'data' => $attendance,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student attendance', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve attendance records. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get student's attendance statistics
     */
    public function stats(Request $request): JsonResponse
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
            $studentId = $user->student->id;
            
            // Overall stats
            $totalRecords = AttendanceLog::where('student_id', $studentId)->count();
            $presentCount = AttendanceLog::where('student_id', $studentId)
                ->where('status', 'present')->count();
            $absentCount = AttendanceLog::where('student_id', $studentId)
                ->where('status', 'absent')->count();
            $leaveCount = AttendanceLog::where('student_id', $studentId)
                ->where('status', 'leave')->count();
            
            // Current month stats
            $currentMonth = now()->format('Y-m');
            $monthlyPresent = AttendanceLog::where('student_id', $studentId)
                ->where('status', 'present')
                ->whereYear('marked_at', now()->year)
                ->whereMonth('marked_at', now()->month)
                ->count();
            
            $monthlyTotal = AttendanceLog::where('student_id', $studentId)
                ->whereYear('marked_at', now()->year)
                ->whereMonth('marked_at', now()->month)
                ->count();

            $stats = [
                'total_records' => $totalRecords,
                'present' => $presentCount,
                'absent' => $absentCount,
                'on_leave' => $leaveCount,
                'percentage' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0,
                'monthly' => [
                    'month' => $currentMonth,
                    'present' => $monthlyPresent,
                    'total' => $monthlyTotal,
                    'percentage' => $monthlyTotal > 0 ? round(($monthlyPresent / $monthlyTotal) * 100, 2) : 0,
                ],
            ];

            return response()->json([
                'data' => $stats,
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            Log::error('Failed to fetch student attendance stats', [
                'error' => $e->getMessage(),
                'student_id' => $user->student->id,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'type' => 'https://map-hms.dev/errors/fetch_failed',
                'title' => 'Fetch Failed',
                'status' => Response::HTTP_INTERNAL_SERVER_ERROR,
                'detail' => 'Failed to retrieve attendance statistics. Please try again.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}

