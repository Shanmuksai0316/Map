<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

/**
 * Payment Status Controller
 *
 * Provides read-only access to student payment information.
 * All payments are handled manually (cash/card/cheque) with no online processing.
 */
class PaymentController extends Controller
{
    /**
     * Get payment status for a specific student
     *
     * Returns current payment status, outstanding amounts, and payment history.
     * Used by Student app and Campus Manager dashboard.
     */
    public function getStudentPaymentStatus(Student $student): JsonResponse
    {
        // Ensure user can access this student's data
        $this->authorize('view', $student);

        // Emit view event
        \App\Models\ProductEvent::create([
            'tenant_id' => tenant()->id,
            'event_type' => 'payment.viewed',
            'entity_type' => 'student',
            'entity_id' => $student->id,
            'properties' => [
                'viewer_role' => auth()->user()?->roles->first()?->name ?? 'unknown',
                'student_id' => $student->id,
            ],
            'occurred_at' => now(),
        ]);

        // Get payment records for this student
        $payments = $student->payments()
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate totals
        $totalPaid = $payments->where('status', 'confirmed')->sum('amount_paise') / 100;
        $totalPending = $payments->where('status', 'pending')->sum('amount_paise') / 100;
        $totalFailed = $payments->where('status', 'failed')->sum('amount_paise') / 100;

        // Get outstanding balance (this would be calculated based on fees vs payments)
        // For now, return a placeholder - in real implementation this would be
        // calculated from fee structure and payment history
        $outstandingBalance = max(0, 50000 - $totalPaid); // Example: assuming ₹50,000 total fees

        return response()->json([
            'data' => [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'total_fees' => 50000, // This should come from fee configuration
                'total_paid' => $totalPaid,
                'total_pending' => $totalPending,
                'total_failed' => $totalFailed,
                'outstanding_balance' => $outstandingBalance,
                'payment_status' => $outstandingBalance <= 0 ? 'paid' : 'pending',
                'last_payment_date' => $payments->where('status', 'confirmed')->first()?->created_at,
                'recent_payments' => $payments->take(5)->map(function ($payment) {
                    return [
                        'id' => $payment->id,
                        'reference' => $payment->reference,
                        'amount' => $payment->amount_paise / 100,
                        'currency' => $payment->currency,
                        'status' => $payment->status,
                        'mode' => $payment->mode,
                        'created_at' => $payment->created_at,
                    ];
                }),
            ],
        ]);
    }

    /**
     * Get payment summary for campus manager dashboard
     *
     * Returns aggregated payment statistics for the tenant.
     */
    public function getPaymentSummary(): JsonResponse
    {
        $tenant = tenant();

        // Get all students for this tenant
        $students = Student::where('tenant_id', $tenant->id)->get();

        $totalStudents = $students->count();
        $paidStudents = 0;
        $pendingStudents = 0;
        $overdueStudents = 0;

        $totalCollected = 0;
        $totalPending = 0;
        $totalOutstanding = 0;

        foreach ($students as $student) {
            $payments = $student->payments;
            $paid = $payments->where('status', 'confirmed')->sum('amount_paise') / 100;
            $pending = $payments->where('status', 'pending')->sum('amount_paise') / 100;

            $outstanding = max(0, 50000 - $paid); // Example calculation

            $totalCollected += $paid;
            $totalPending += $pending;
            $totalOutstanding += $outstanding;

            if ($outstanding <= 0) {
                $paidStudents++;
            } elseif ($outstanding > 0 && $paid > 0) {
                $pendingStudents++;
            } else {
                $overdueStudents++;
            }
        }

        return response()->json([
            'data' => [
                'total_students' => $totalStudents,
                'paid_students' => $paidStudents,
                'pending_students' => $pendingStudents,
                'overdue_students' => $overdueStudents,
                'total_collected' => $totalCollected,
                'total_pending' => $totalPending,
                'total_outstanding' => $totalOutstanding,
                'collection_rate' => $totalStudents > 0 ? ($paidStudents / $totalStudents) * 100 : 0,
            ],
        ]);
    }
}
