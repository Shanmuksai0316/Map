<?php

namespace App\Http\Controllers\Api\V1;

use App\Jobs\SendApprovalNotification;
use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Http\Controllers\Controller;
use App\Http\Requests\OutPass\StoreOutPassRequest;
use App\Http\Requests\OutPass\UpdateOutPassRequest;
use App\Http\Resources\OutPassResource;
use App\Models\Domain\OutPass\OutPass;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class OutPassController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OutPass::class);

        $outPasses = OutPass::query()
            ->where('tenant_id', Auth::user()->tenant_id)
            ->with(['student.user', 'hostel', 'histories.actor'])
            ->latest('requested_at')
            ->paginate($request->integer('per_page', 20));

        return OutPassResource::collection($outPasses)->response();
    }

    public function store(StoreOutPassRequest $request): JsonResponse
    {
        $this->authorize('create', OutPass::class);

        $user = Auth::user();
        $idempotencyKey = $request->header('Idempotency-Key') ?: Str::uuid()->toString();

        // Auto-fill student's hostel if not provided
        $hostelId = $request->integer('hostel_id') ?: $user->student->hostel_id;
        
        // Verify overnight is allowed for this hostel
        if ($request->boolean('overnight')) {
            $hostel = \App\Models\Hostel::find($hostelId);
            if (!$hostel || !$hostel->overnight_enabled) {
                return response()->json([
                    'error' => 'Overnight out-passes are not enabled for this hostel',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        // Auto-fill valid_until if not provided (default to 8 hours from now)
        $validUntil = $request->date('valid_until') ?: now('Asia/Kolkata')->addHours(8);

        $outPass = OutPass::firstOrCreate(
            [
                'tenant_id' => $user->tenant_id,
                'student_id' => $user->student->id,
                'idempotency_key' => $idempotencyKey,
            ],
            [
                'hostel_id' => $hostelId,
                'reason' => OutPassType::from($request->string('reason')->toString()),
                'overnight' => $request->boolean('overnight'),
                'status' => OutPassStatus::PENDING,
                'requested_at' => now('Asia/Kolkata'),
                'valid_until' => $validUntil,
                'note' => $request->input('note'),
            ]
        );

        if ($outPass->wasRecentlyCreated) {
            $outPass->recordHistory(null, OutPassStatus::PENDING, 'Out-pass requested', label: 'Request Submitted', description: 'Student submitted the out-pass request');
        }

        return (new OutPassResource($outPass->load(['student.user', 'hostel', 'histories.actor'])))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(OutPass $outPass): OutPassResource
    {
        $this->authorize('view', $outPass);

        return new OutPassResource($outPass->load(['student.user', 'hostel', 'histories.actor']));
    }

    public function update(UpdateOutPassRequest $request, OutPass $outPass): OutPassResource|JsonResponse
    {
        $this->authorize('update', $outPass);

        $status = OutPassStatus::from($request->string('status')->toString());
        $previousStatus = $outPass->status;
        $user = Auth::user();
        $nowIst = Carbon::now('Asia/Kolkata');

        // Check for 24-hour expiry
        if ($this->isOutPassExpired($outPass)) {
            $outPass->forceFill([
                'status' => OutPassStatus::EXPIRED,
                'decided_at' => now(),
                'note' => 'Automatically expired after 24 hours',
                'decision_by' => null,
            ])->save();

            $outPass->recordHistory($previousStatus, OutPassStatus::EXPIRED, 'Automatically expired', label: 'Expired', description: 'Out-pass expired after 24 hours');

            return new OutPassResource($outPass->load(['student.user', 'hostel', 'histories.actor']));
        }

        $approvalPayload = null;

        // If transitioning to APPROVED, ensure validity + backup code are set for gate pass usage.
        if ($status === OutPassStatus::APPROVED && $previousStatus !== OutPassStatus::APPROVED) {
            // Ensure valid_until is set (prefer student's requested valid_until; otherwise default to 8 hours from now).
            $validUntil = $outPass->valid_until;
            if (!$validUntil || $validUntil->isPast()) {
                $validUntil = $nowIst->copy()->addHours(8);
                $outPass->forceFill(['valid_until' => $validUntil]);
            }

            // Ensure a backup code exists (generate only once, unless hostel disables backup codes).
            $hostel = $outPass->hostel;
            $backupCodePlain = null;
            if ($hostel?->areBackupCodesEnabled()) {
                if (empty($outPass->backup_code) || empty($outPass->backup_code_plain)) {
                    $backupCodePlain = $outPass->generateBackupCode();
                } else {
                    // If already generated earlier, return the encrypted plain code (if present).
                    $backupCodePlain = $outPass->backup_code_plain;
                }
            }

            $approvalPayload = [
                'outpass_id' => (string) $outPass->id,
                'unique_id' => (string) ($outPass->unique_id ?? ('OP-' . $outPass->id)),
                'valid_until' => $validUntil?->toIso8601String(),
                'backup_code' => $backupCodePlain,
            ];
        }

        $outPass->forceFill([
            'status' => $status,
            'decided_at' => $nowIst,
            'note' => $request->input('note'),
            'decision_by' => $user->id,
        ])->save();

        $outPass->recordHistory($previousStatus, $status, $request->input('note'), label: 'Decision Recorded', description: sprintf('Status changed to %s', $status->value));

        // Dispatch approval/rejection notifications (SMS + push) for student and campus manager.
        if (
            $previousStatus !== $status
            && in_array($status, [OutPassStatus::APPROVED, OutPassStatus::DECLINED, OutPassStatus::REJECTED], true)
        ) {
            $studentUserId = $outPass->student?->user?->id;
            if ($studentUserId) {
                SendApprovalNotification::dispatch(
                    approvalType: 'outpass',
                    recordId: (int) $outPass->id,
                    decision: $status === OutPassStatus::APPROVED ? 'approved' : 'rejected',
                    note: $request->input('note'),
                    studentId: (int) $studentUserId,
                    rectorId: (int) $user->id,
                    tenantId: (string) $user->tenant_id
                );
            }
        }

        $resource = new OutPassResource($outPass->load(['student.user', 'hostel', 'histories.actor']));
        if ($approvalPayload !== null) {
            $resource->additional($approvalPayload);
        }

        return $resource;
    }

    public function cancel(OutPass $outPass): OutPassResource
    {
        $this->authorize('cancel', $outPass);

        $previousStatus = $outPass->status;

        $outPass->forceFill([
            'status' => OutPassStatus::CANCELLED,
            'decided_at' => now(),
            'decision_by' => Auth::id(),
        ])->save();

        $outPass->recordHistory($previousStatus, OutPassStatus::CANCELLED, 'Out-pass cancelled', label: 'Cancelled', description: 'Out-pass was cancelled');

        return new OutPassResource($outPass->load(['student.user', 'hostel', 'histories.actor']));
    }

    private function isOutPassExpired(OutPass $outPass): bool
    {
        // Check if out-pass is older than 24 hours and still pending
        if ($outPass->status !== OutPassStatus::PENDING) {
            return false;
        }

        // Use copy() to avoid mutating the original timestamp
        $expiryTime = $outPass->requested_at->copy()->addHours(24);

        return now()->isAfter($expiryTime);
    }
}
