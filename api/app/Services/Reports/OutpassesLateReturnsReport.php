<?php

namespace App\Services\Reports;

use App\Models\OutPass;
use App\Models\Hostel;
use App\Support\HostelScope;
use Carbon\Carbon;

class OutpassesLateReturnsReport
{
    public static function generate(array $params): \Generator
    {
        $tenantId = auth()->user()->tenant_id;
        $fromDate = Carbon::parse($params['from_date'])->startOfDay();
        $toDate = Carbon::parse($params['to_date'])->endOfDay();
        $hostelId = $params['hostel_id'] ?? null;
        
        $outPasses = OutPass::where('tenant_id', $tenantId)
            ->whereBetween('created_at', [$fromDate, $toDate])
            ->when($hostelId, fn($q) => $q->where('hostel_id', $hostelId))
            ->with(['hostel', 'student.user'])
            ->get();
            
        foreach ($outPasses as $outPass) {
            $isLate = false;
            $lateByHours = 0;
            
            if ($outPass->expected_return && $outPass->returned_at) {
                if ($outPass->returned_at->gt($outPass->expected_return)) {
                    $isLate = true;
                    $lateByHours = $outPass->expected_return->diffInHours($outPass->returned_at);
                }
            } elseif ($outPass->expected_return && now()->gt($outPass->expected_return)) {
                $isLate = true;
                $lateByHours = $outPass->expected_return->diffInHours(now());
            }
            
            yield [
                'OutPass ID' => $outPass->id,
                'Student Name' => $outPass->student->user->name,
                'Student UID' => $outPass->student->student_uid,
                'Hostel' => $outPass->hostel->name,
                'Purpose' => $outPass->purpose,
                'Status' => $outPass->status,
                'Expected Return' => $outPass->expected_return ? $outPass->expected_return->format('Y-m-d H:i') : 'N/A',
                'Actual Return' => $outPass->returned_at ? $outPass->returned_at->format('Y-m-d H:i') : 'Not Returned',
                'Is Late' => $isLate ? 'Yes' : 'No',
                'Late By (Hours)' => $isLate ? $lateByHours : 0,
                'Created' => $outPass->created_at->format('Y-m-d H:i'),
            ];
        }
    }
}
