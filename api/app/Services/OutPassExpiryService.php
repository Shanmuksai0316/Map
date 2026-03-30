<?php

namespace App\Services;

use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class OutPassExpiryService
{
    public function __construct(
        private readonly AuditLogger $auditLogger
    ) {}

    public function expireOutPasses(): int
    {
        $now = Carbon::now('Asia/Kolkata');
        $expiredCount = 0;

        // Find all approved outpasses that have passed their valid_until time
        $expiredOutPasses = OutPass::query()
            ->where('status', OutPassStatus::APPROVED)
            ->where('valid_until', '<', $now)
            ->get();

        foreach ($expiredOutPasses as $outPass) {
            $this->expireOutPass($outPass);
            $expiredCount++;
        }

        return $expiredCount;
    }

    private function expireOutPass(OutPass $outPass): void
    {
        DB::transaction(function () use ($outPass): void {
            $previousStatus = $outPass->status;

            // Update status to expired
            $outPass->forceFill([
                'status' => OutPassStatus::EXPIRED,
                'decided_at' => now(),
                'decision_by' => null, // System action
            ])->save();

            // Record history
            $outPass->recordHistory(
                $previousStatus,
                OutPassStatus::EXPIRED,
                'Out-pass expired automatically',
                actorId: null,
                label: 'Expired',
                description: sprintf('Out-pass expired at %s', $outPass->valid_until->format('Y-m-d H:i:s'))
            );

            // Log audit event
            $this->auditLogger->log('outpass.expired', $outPass, [
                'student_id' => $outPass->student_id,
                'hostel_id' => $outPass->hostel_id,
                'valid_until' => $outPass->valid_until->toISOString(),
                'expired_at' => now()->toISOString(),
            ]);
        });
    }

    public function getExpiringOutPasses(int $minutesBeforeExpiry = 30): \Illuminate\Database\Eloquent\Collection
    {
        $now = Carbon::now('Asia/Kolkata');
        $expiryThreshold = $now->copy()->addMinutes($minutesBeforeExpiry);

        return OutPass::query()
            ->where('status', OutPassStatus::APPROVED)
            ->where('valid_until', '<=', $expiryThreshold)
            ->where('valid_until', '>', $now)
            ->with(['student.user', 'hostel'])
            ->get();
    }

    public function isExpired(OutPass $outPass): bool
    {
        if ($outPass->status !== OutPassStatus::APPROVED) {
            return false;
        }

        return $outPass->valid_until && $outPass->valid_until->isPast();
    }

    public function getExpiryStatus(OutPass $outPass): array
    {
        if (!$outPass->valid_until) {
            return [
                'is_expired' => false,
                'expires_in_minutes' => null,
                'status' => 'no_expiry',
            ];
        }

        $now = Carbon::now('Asia/Kolkata');
        $isExpired = $outPass->valid_until->isPast();
        $expiresInMinutes = $isExpired ? 0 : $now->diffInMinutes($outPass->valid_until, false);

        $status = 'valid';
        if ($isExpired) {
            $status = 'expired';
        } elseif ($expiresInMinutes <= 30) {
            $status = 'expiring_soon';
        }

        return [
            'is_expired' => $isExpired,
            'expires_in_minutes' => max(0, $expiresInMinutes),
            'status' => $status,
            'valid_until' => $outPass->valid_until->toISOString(),
        ];
    }
}
