<?php

namespace App\Services\Reports;

use App\Domain\Leaves\Models\Leave;
use App\Domain\SickLeaves\Models\SickLeave;
use App\Models\Domain\OutPass\OutPass;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use League\Csv\Writer;

class RectorReportService
{
    /**
     * Generate monthly PDF report
     */
    public function generateMonthlyPDF(string $tenantId, int $month, int $year): string
    {
        $data = $this->getReportData($tenantId, $month, $year);

        $pdf = Pdf::loadView('reports.rector-monthly', [
            'data' => $data,
            'month' => $month,
            'year' => $year,
            'generated_at' => now(),
        ]);

        $filename = "rector-report-{$year}-{$month}.pdf";
        $path = "reports/{$tenantId}/{$filename}";

        Storage::disk('public')->put($path, $pdf->output());

        return Storage::disk('public')->url($path);
    }

    /**
     * Generate monthly CSV report
     */
    public function generateMonthlyCSV(string $tenantId, int $month, int $year): string
    {
        $data = $this->getReportData($tenantId, $month, $year);

        $csv = Writer::createFromString('');

        // Add headers
        $csv->insertOne([
            'Request Type',
            'Unique ID',
            'Student Name',
            'Hostel',
            'Status',
            'Submitted At',
            'Decided At',
            'Decided By',
            'SLA Breached',
            'SLA Breach Hours',
        ]);

        // Add data rows
        foreach ($data['decisions'] as $decision) {
            $csv->insertOne([
                $decision['type'],
                $decision['unique_id'],
                $decision['student_name'],
                $decision['hostel_name'],
                $decision['status'],
                $decision['submitted_at'],
                $decision['decided_at'],
                $decision['decided_by'],
                $decision['sla_breached'] ? 'Yes' : 'No',
                $decision['sla_breach_hours'] ?? 0,
            ]);
        }

        $filename = "rector-report-{$year}-{$month}.csv";
        $path = "reports/{$tenantId}/{$filename}";

        Storage::disk('public')->put($path, $csv->toString());

        return Storage::disk('public')->url($path);
    }

    /**
     * Get comprehensive report data
     */
    private function getReportData(string $tenantId, int $month, int $year): array
    {
        $startDate = "{$year}-{$month}-01 00:00:00";
        $endDate = date('Y-m-t', strtotime($startDate)) . ' 23:59:59';

        $decisions = [];

        // Get OutPass decisions
        $outPasses = OutPass::with(['student.user', 'hostel'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'declined'])
            ->whereBetween('decided_at', [$startDate, $endDate])
            ->get();

        foreach ($outPasses as $outPass) {
            $decisions[] = [
                'type' => 'Out-Pass',
                'unique_id' => $outPass->unique_id,
                'student_name' => $outPass->student->user->name ?? 'Unknown',
                'hostel_name' => $outPass->hostel->name ?? 'Unknown',
                'status' => $outPass->status,
                'submitted_at' => $outPass->requested_at->format('Y-m-d H:i:s'),
                'decided_at' => $outPass->decided_at->format('Y-m-d H:i:s'),
                'decided_by' => $this->getUserName($outPass->decision_by),
                'sla_breached' => $this->checkSLABreach($outPass->requested_at, $outPass->decided_at, 2),
                'sla_breach_hours' => $this->calculateSLABreachHours($outPass->requested_at, $outPass->decided_at, 2),
            ];
        }

        // Get Leave decisions
        $leaves = Leave::with(['student.user', 'hostel'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->get();

        foreach ($leaves as $leave) {
            $decisions[] = [
                'type' => 'Leave',
                'unique_id' => $leave->unique_id,
                'student_name' => $leave->student->user->name ?? 'Unknown',
                'hostel_name' => $leave->hostel->name ?? 'Unknown',
                'status' => $leave->status,
                'submitted_at' => $leave->submitted_at->format('Y-m-d H:i:s'),
                'decided_at' => $leave->approved_at->format('Y-m-d H:i:s'),
                'decided_by' => $this->getUserName($leave->approved_by),
                'sla_breached' => $this->checkSLABreach($leave->submitted_at, $leave->approved_at, 4),
                'sla_breach_hours' => $this->calculateSLABreachHours($leave->submitted_at, $leave->approved_at, 4),
            ];
        }

        // Get Sick Leave decisions
        $sickLeaves = SickLeave::with(['student.user', 'hostel'])
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['approved', 'rejected'])
            ->whereBetween('approved_at', [$startDate, $endDate])
            ->get();

        foreach ($sickLeaves as $sickLeave) {
            $decisions[] = [
                'type' => 'Sick Leave',
                'unique_id' => $sickLeave->unique_id,
                'student_name' => $sickLeave->student->user->name ?? 'Unknown',
                'hostel_name' => $sickLeave->hostel->name ?? 'Unknown',
                'status' => $sickLeave->status,
                'submitted_at' => $sickLeave->submitted_at->format('Y-m-d H:i:s'),
                'decided_at' => $sickLeave->approved_at->format('Y-m-d H:i:s'),
                'decided_by' => $this->getUserName($sickLeave->approved_by),
                'sla_breached' => $this->checkSLABreach($sickLeave->submitted_at, $sickLeave->approved_at, 4),
                'sla_breach_hours' => $this->calculateSLABreachHours($sickLeave->submitted_at, $sickLeave->approved_at, 4),
            ];
        }

        // Calculate summary statistics
        $totalDecisions = count($decisions);
        $approvedCount = count(array_filter($decisions, fn($d) => $d['status'] === 'approved'));
        $rejectedCount = count(array_filter($decisions, fn($d) => in_array($d['status'], ['rejected', 'declined'])));
        $breachedCount = count(array_filter($decisions, fn($d) => $d['sla_breached']));

        $slaCompliance = $totalDecisions > 0 ? (($totalDecisions - $breachedCount) / $totalDecisions) * 100 : 100;

        return [
            'summary' => [
                'total_decisions' => $totalDecisions,
                'approved' => $approvedCount,
                'rejected' => $rejectedCount,
                'sla_breached' => $breachedCount,
                'sla_compliance_percentage' => round($slaCompliance, 1),
            ],
            'decisions' => $decisions,
        ];
    }

    private function getUserName(mixed $userId): string
    {
        if (!$userId) return 'System';

        $user = \App\Models\User::find($userId);
        return $user ? $user->name : 'Unknown';
    }

    private function checkSLABreach($submittedAt, $decidedAt, int $slaHours): bool
    {
        if (!$submittedAt || !$decidedAt) return false;

        $hoursElapsed = $submittedAt->diffInHours($decidedAt);
        return $hoursElapsed > $slaHours;
    }

    private function calculateSLABreachHours($submittedAt, $decidedAt, int $slaHours): ?float
    {
        if (!$submittedAt || !$decidedAt) return null;

        $hoursElapsed = $submittedAt->diffInHours($decidedAt);
        return $hoursElapsed > $slaHours ? $hoursElapsed - $slaHours : null;
    }
}
