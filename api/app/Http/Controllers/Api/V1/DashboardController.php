<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\Tickets\Models\Ticket;
use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Domain\OutPass\OutPass;
use App\Models\FacilityBooking;
use App\Models\SportsFacility;
use App\Models\Student;
use App\Support\Roles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'type' => 'https://map-hms.dev/errors/unauthorized',
                'title' => 'Unauthorized',
                'status' => Response::HTTP_UNAUTHORIZED,
                'detail' => 'Authentication required.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Return role-specific dashboard data
        switch ($user->roles->first()?->name) {
            case Roles::SPORTS_MANAGER:
                return $this->getSportsManagerDashboard($user);
            case Roles::CAMPUS_MANAGER:
                return $this->getCampusManagerDashboard($user);
            case Roles::STUDENT:
                return $this->getStudentDashboard($user);
            default:
                return $this->getGeneralDashboard($user);
        }
    }

    private function getSportsManagerDashboard($user): JsonResponse
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        
        $totalFacilities = $this->safeMetric(fn () => SportsFacility::where('is_active', true)->count(), [SportsFacility::class]);
        $activeBookings = $this->safeMetric(fn () => FacilityBooking::where('status', 'active')->count(), [FacilityBooking::class]);

        // Real checklist backlog query
        $pendingChecklists = 0;
        if (class_exists(\App\Domain\Checklists\Models\ChecklistInstance::class)) {
            $pendingChecklists = $this->safeMetric(
                fn () => \App\Domain\Checklists\Models\ChecklistInstance::where('status', 'pending')->count(),
                [\App\Domain\Checklists\Models\ChecklistInstance::class]
            );
        }

        $metrics = [
            'total_facilities' => $totalFacilities,
            'active_bookings' => $activeBookings,
            'available_facilities' => max(0, $totalFacilities - $activeBookings),
            'pending_checklists' => $pendingChecklists,
            'updated_at' => now()->toIso8601String(),
        ];

        return response()->json(['data' => $metrics]);
    }

    private function getCampusManagerDashboard($user): JsonResponse
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        
        // Real queries instead of mocked data
        $totalStudents = $this->safeMetric(fn () => Student::count(), [Student::class]);
        
        // Present today - actual query
        $presentToday = $this->safeMetric(
            fn () => AttendanceLog::whereDate('attendance_date', today())
                ->where('status', 'present')
                ->distinct('student_id')
                ->count('student_id'),
            [AttendanceLog::class]
        );
        
        // Absent today - real calculation
        $absentToday = $totalStudents - $presentToday;
        
        // Active gate passes - real query
        $activeGatePasses = $this->safeMetric(
            fn () => OutPass::where('status', \App\Enums\OutPassStatus::APPROVED)
                ->where('valid_until', '>', now())
                ->count(),
            [OutPass::class]
        );
        
        // Pending complaints/tickets - real query
        $pendingComplaints = $this->safeMetric(
            fn () => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
            [Ticket::class]
        );
        
        // Pending approvals - real query
        $pendingApprovals = $this->safeMetric(
            fn () => OutPass::where('status', \App\Enums\OutPassStatus::PENDING)->count(),
            [OutPass::class]
        );

        $metrics = [
            'total_students' => $totalStudents,
            'present_today' => $presentToday,
            'absent_today' => $absentToday,
            'active_gate_passes' => $activeGatePasses,
            'pending_complaints' => $pendingComplaints,
            'pending_approvals' => $pendingApprovals,
            'updated_at' => now()->toIso8601String(),
        ];

        return response()->json(['data' => $metrics]);
    }

    private function getStudentDashboard($user): JsonResponse
    {
        $student = $user->student;
        if (!$student) {
            return response()->json(['data' => []]);
        }

        $metrics = [
            'active_bookings' => $this->safeMetric(
                fn () => FacilityBooking::where('student_id', $student->id)
                    ->where('status', 'active')
                    ->count(),
                [FacilityBooking::class]
            ),
            'upcoming_bookings' => $this->safeMetric(
                fn () => FacilityBooking::where('student_id', $student->id)
                    ->where('status', 'active')
                    ->where('start_at', '>', now())
                    ->count(),
                [FacilityBooking::class]
            ),
            'pending_requests' => $this->safeMetric(
                fn () => Ticket::where('reporter_student_id', $student->id)
                    ->where('status', 'open')
                    ->count(),
                [Ticket::class]
            ),
        ];

        return response()->json(['data' => $metrics]);
    }

    private function getGeneralDashboard($user): JsonResponse
    {
        // NOTE: tenant_id removed - we're in tenant context, no tenant_id column in tenant DB
        
        // Real queries
        $totalStudents = $this->safeMetric(fn () => Student::count(), [Student::class]);
        $activeGatePasses = $this->safeMetric(
            fn () => OutPass::where('status', \App\Enums\OutPassStatus::APPROVED)
                ->where('valid_until', '>', now())
                ->count(),
            [OutPass::class]
        );
        
        $presentToday = $this->safeMetric(
            fn () => AttendanceLog::whereDate('attendance_date', today())
                ->where('status', 'present')
                ->distinct('student_id')
                ->count('student_id'),
            [AttendanceLog::class]
        );
        
        $absentToday = max(0, $totalStudents - $presentToday);
        
        $pendingComplaints = $this->safeMetric(
            fn () => Ticket::whereIn('status', ['open', 'in_progress'])->count(),
            [Ticket::class]
        );
        $pendingApprovals = $this->safeMetric(
            fn () => OutPass::where('status', \App\Enums\OutPassStatus::PENDING)->count(),
            [OutPass::class]
        );
        
        // Checklist backlog (if applicable)
        $checklistBacklog = 0;
        if (class_exists(\App\Domain\Checklists\Models\ChecklistInstance::class)) {
            $checklistBacklog = $this->safeMetric(
                fn () => \App\Domain\Checklists\Models\ChecklistInstance::where('status', 'pending')->count(),
                [\App\Domain\Checklists\Models\ChecklistInstance::class]
            );
        }
        
        return response()->json([
            'data' => [
                'total_students' => $totalStudents,
                'present_today' => $presentToday,
                'absent_today' => $absentToday,
                'active_gate_passes' => $activeGatePasses,
                'pending_complaints' => $pendingComplaints,
                'pending_approvals' => $pendingApprovals,
                'checklist_backlog' => $checklistBacklog,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Safely execute a metric query by ensuring required tables exist and catching errors.
     */
    private function safeMetric(callable $callback, array $modelClasses = []): int
    {
        try {
            foreach ($modelClasses as $class) {
                if (!class_exists($class)) {
                    return 0;
                }

                $model = new $class();
                if (!Schema::hasTable($model->getTable())) {
                    return 0;
                }
            }

            return (int) $callback();
        } catch (\Throwable $exception) {
            Log::warning('dashboard_metric_failed', [
                'message' => $exception->getMessage(),
                'metric_trace' => app()->environment('production') ? null : $exception->getTraceAsString(),
            ]);

            return 0;
        }
    }
}
