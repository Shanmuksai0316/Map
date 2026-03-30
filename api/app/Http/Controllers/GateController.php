<?php

/**
 * GateController
 * 
 * Module: Gate
 * Purpose: Handle gate operations including student entry/exit, device management, and visitor control
 * Key routes: POST /gate/out, POST /gate/in, GET /gate/outpasses/today, GET /gate/visitors/today
 * Policies: GatePolicy@out, GatePolicy@in, GatePolicy@listOutPasses, GatePolicy@visitorsList
 * @tenant-scope: All operations scoped to user's tenant_id
 * Feature flags: None (core functionality)
 * Side effects: Audit logging, metrics collection, notifications
 * Owner: MAP Co-Pilot
 */

namespace App\Http\Controllers;

use App\Domain\Gate\Models\GateDevice;
use App\Domain\Gate\Models\GateEntry;
use App\Domain\Visitors\Models\GuestVisit;
use App\Enums\OutPassStatus;
use App\Models\Domain\OutPass\OutPass;
use App\Domain\Leaves\Models\Leave;
use App\Models\GateDutyHandover;
use App\Models\GatePassScan;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Gate\DeviceGuard;
use App\Services\Gate\OtpVerifier;
use App\Services\Gate\VisitingHoursService;
use App\Services\Metrics\Metrics;
use App\Services\Notify\PushNotifier;
use App\Services\Notifications\NotificationRecipients;
use App\Services\Notifications\SmsService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GateController extends Controller
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OtpVerifier $otpVerifier,
        private readonly DeviceGuard $deviceGuard,
        private readonly VisitingHoursService $visitingHoursService,
        private readonly PushNotifier $pushNotifier,
        private readonly NotificationRecipients $recipients,
        private readonly SmsService $smsService
    ) {}

    /**
     * Get today's approved outpasses for the caller's hostel(s)
     * 
     * @param Request $request HTTP request with user authentication
     * @return JsonResponse List of approved outpasses for today
     * @tenant-scope: Automatically filtered by user's tenant_id
     * @policy: GatePolicy@listOutPasses
     */
    public function listOutPasses(Request $request): JsonResponse
    {
        $this->authorize('listOutPasses', GateEntry::class);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Get hostels the user can access
        $hostelIds = $this->getAccessibleHostelIds($user);

        $outpasses = OutPass::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->where('status', 'approved')
            ->whereDate('requested_at', today())
            ->select(['id', 'student_id', 'requested_at as start_at', 'valid_until as return_by', 'status'])
            ->get();

        return response()->json($outpasses);
    }

    /**
     * Process student going OUT through the gate
     * 
     * @param Request $request HTTP request with student identification and method
     * @return JsonResponse Gate entry record with verification status
     * @tenant-scope: Student must belong to user's tenant
     * @policy: GatePolicy@out
     * @audit: gate.out event logged with student and method details
     * @metrics: GateOut counter incremented with method and verification status
     */
    public function out(Request $request): JsonResponse
    {
        $this->authorize('out', GateEntry::class);

        $validated = $request->validate([
            'student_uid' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'method' => ['required', 'in:qr,otp,backup_code,manual'],
            'otp_code' => ['nullable', 'string'],
            'backup_code' => ['nullable', 'string', 'size:4'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Resolve student
        $student = $this->resolveStudent($validated, $tenantId);
        if (!$student) {
            throw ValidationException::withMessages([
                'student' => ['Student not found'],
            ]);
        }

        // @feature-flag: Device guard check for enhanced security
        $this->deviceGuard->assert($request, $user, $student->hostel_id);

        // Check for approved outpass within time window
        $outpass = $this->findApprovedOutpass($student, $tenantId);
        $method = $validated['method'];

        if (!$outpass && $method !== 'manual') {
            $this->pushNotifier->toUserTemplate(
                $user->id,
                'guard.gate_pass_denied',
                [
                    'outpass_id' => 'N/A',
                ],
                [
                    'type' => 'gate_pass_denied',
                    'student_id' => (string) $student->id,
                ]
            );

            throw ValidationException::withMessages([
                'outpass' => ['E_OUTPASS_REQUIRED'],
            ]);
        }

        // Mark QR as scanned if using QR method (to prevent reuse)
        if ($method === 'qr' && $outpass) {
            $outpass->markQrScanned();
        }

        // Verify based on method
        $verified = true;
        if ($method === 'otp') {
            $verified = $this->otpVerifier->check($student, $validated['otp_code'] ?? '');
        } elseif ($method === 'backup_code') {
            // Verify backup code against the outpass
            if (!$outpass) {
                throw ValidationException::withMessages([
                    'backup_code' => ['E_NO_OUTPASS: No outpass found to verify backup code against'],
                ]);
            }
            $verified = $outpass->verifyBackupCode($validated['backup_code'] ?? '');
            if ($verified) {
                $outpass->markBackupCodeUsed();
            }
        } elseif ($method === 'manual') {
            $verified = false;
        }

        // Create gate entry
        $gateEntry = GateEntry::create([
            'tenant_id' => $tenantId,
            'hostel_id' => $student->hostel_id,
            'student_id' => $student->id,
            'outpass_id' => $outpass?->id,
            'event' => 'student_exit',
            'occurred_at' => now(),
            'source' => 'web',
            'direction' => GateEntry::DIRECTION_OUT,
            'method' => $method,
            'verified' => $verified,
            'verified_at' => $verified ? now() : null,
            'guard_user_id' => $user->id,
            'guard_id' => $user->id,
            'note' => $validated['note'] ?? null,
        ]);

        // Audit log
        $this->auditLogger->log('gate.out', $gateEntry, [
            'student_id' => $student->id,
            'outpass_id' => $outpass?->id,
            'method' => $method,
            'verified' => $verified,
        ]);

        // Send metrics
        Metrics::count('GateOut', 1, [
            'tenant_id' => $tenantId,
            'hostel_id' => $student->hostel_id,
            'method' => $method,
            'verified' => $verified ? 'true' : 'false',
            'late' => '0', // Out is never late
        ]);

        if (!$verified && $method !== 'manual') {
            $this->pushNotifier->toUserTemplate(
                $user->id,
                'guard.gate_pass_denied',
                [
                    'outpass_id' => (string) ($outpass?->id ?? 'N/A'),
                ],
                [
                    'type' => 'gate_pass_denied',
                    'student_id' => (string) $student->id,
                    'outpass_id' => (string) ($outpass?->id ?? ''),
                ]
            );
        }

        // If this OUT corresponds to a verified outpass usage, send role-based notifications
        if ($outpass && $verified) {
            $timeFormatted = now()->format('d M, H:i');

            $tenantIdStr = (string) $tenantId;
            $hostelIdInt = (int) $student->hostel_id;

            $rector        = $this->recipients->rectorForHostel($tenantIdStr, $hostelIdInt);
            $warden        = $this->recipients->wardenForHostel($tenantIdStr, $hostelIdInt);
            $campusManager = $this->recipients->campusManagerForTenant($tenantIdStr);
            $guards        = $this->recipients->guardsForHostel($tenantIdStr, $hostelIdInt);

            // Rector: gate pass used
            if ($rector) {
                $this->pushNotifier->toUserTemplate(
                    $rector->id,
                    'rector.gate_pass_used',
                    [
                        'outpass_id'   => $outpass->id,
                        'student_name' => $student->user->name ?? $student->name,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'       => 'gate_pass_used',
                        'outpass_id' => (string) $outpass->id,
                    ]
                );
            }

            // Warden: student has gone out
            if ($warden) {
                $this->pushNotifier->toUserTemplate(
                    $warden->id,
                    'warden.checkout_outpass',
                    [
                        'student_name' => $student->user->name ?? $student->name,
                        'outpass_id'   => $outpass->id,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'       => 'checkout_outpass',
                        'outpass_id' => (string) $outpass->id,
                    ]
                );
            }

            // Campus Manager: student has gone out
            if ($campusManager) {
                $this->pushNotifier->toUserTemplate(
                    $campusManager->id,
                    'campus_manager.checkout_outpass',
                    [
                        'student_name' => $student->user->name ?? $student->name,
                        'outpass_id'   => $outpass->id,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'       => 'checkout_outpass',
                        'outpass_id' => (string) $outpass->id,
                    ]
                );
            }

            // Guards: student checked out
            foreach ($guards as $guard) {
                $this->pushNotifier->toUserTemplate(
                    $guard->id,
                    'guard.checkout_outpass',
                    [
                        'student_name' => $student->user->name ?? $student->name,
                        'outpass_id'   => $outpass->id,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'       => 'checkout_outpass',
                        'outpass_id' => (string) $outpass->id,
                    ]
                );
            }

            if ($user->hasRole('Guard')) {
                $this->pushNotifier->toUserTemplate(
                    $user->id,
                    'guard.gate_pass_used',
                    [
                        'outpass_id'   => $outpass->id,
                        'student_name' => $student->user->name ?? $student->name,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'       => 'gate_pass_used',
                        'outpass_id' => (string) $outpass->id,
                    ]
                );
            }

            // Student: out-pass used
            if ($student->user) {
                $this->pushNotifier->toUserTemplate(
                    $student->user->id,
                    'student.outpass_used',
                    [
                        'outpass_id' => $outpass->id,
                        'time'       => $timeFormatted,
                    ],
                    [
                        'type'       => 'outpass_used',
                        'outpass_id' => (string) $outpass->id,
                    ]
                );
            }
        }

        // Also notify on active approved leave (if any) for today's date for the same student
        $todayIst = Carbon::now('Asia/Kolkata')->toDateString();
        $leave = Leave::query()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('status', 'approved')
            ->whereDate('from_date', '<=', $todayIst)
            ->whereDate('to_date', '>=', $todayIst)
            ->latest('from_date')
            ->first();

        if ($leave) {
            $timeFormatted = now()->format('d M, H:i');

            $tenantIdStr = (string) $tenantId;
            $hostelIdInt = (int) $student->hostel_id;

            $warden        = $this->recipients->wardenForHostel($tenantIdStr, $hostelIdInt);
            $campusManager = $this->recipients->campusManagerForTenant($tenantIdStr);
            $guards        = $this->recipients->guardsForHostel($tenantIdStr, $hostelIdInt);

            // Warden: student checked out on leave
            if ($warden) {
                $this->pushNotifier->toUserTemplate(
                    $warden->id,
                    'warden.checkout_leave',
                    [
                        'student_name' => $student->user->name ?? $student->name,
                        'leave_id'     => $leave->id,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'     => 'checkout_leave',
                        'leave_id' => (string) $leave->id,
                    ]
                );
            }

            // Campus Manager
            if ($campusManager) {
                $this->pushNotifier->toUserTemplate(
                    $campusManager->id,
                    'campus_manager.checkout_leave',
                    [
                        'student_name' => $student->user->name ?? $student->name,
                        'leave_id'     => $leave->id,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'     => 'checkout_leave',
                        'leave_id' => (string) $leave->id,
                    ]
                );
            }

            // Guards – mirror leave checkout as well
            foreach ($guards as $guard) {
                $this->pushNotifier->toUserTemplate(
                    $guard->id,
                    'guard.checkout_leave',
                    [
                        'student_name' => $student->user->name ?? $student->name,
                        'leave_id'     => $leave->id,
                        'time'         => $timeFormatted,
                    ],
                    [
                        'type'     => 'checkout_leave',
                        'leave_id' => (string) $leave->id,
                    ]
                );
            }
        }

        return response()->json([
            'id' => $gateEntry->id,
            'verified' => $verified,
            'outpass_id' => $outpass?->id,
        ], 201);
    }

    /**
     * Process student coming IN through the gate
     * 
     * @param Request $request HTTP request with student identification and method
     * @return JsonResponse Gate entry record with late minutes calculation
     * @tenant-scope: Student must belong to user's tenant
     * @policy: GatePolicy@in
     * @audit: gate.in and gate.late_return events logged
     * @metrics: GateIn counter incremented with method and late status
     */
    public function in(Request $request): JsonResponse
    {
        $this->authorize('in', GateEntry::class);

        $validated = $request->validate([
            'student_uid' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'method' => ['required', 'in:qr,otp,backup_code,manual'],
            'otp_code' => ['nullable', 'string'],
            'backup_code' => ['nullable', 'string', 'size:4'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        // Resolve student
        $student = $this->resolveStudent($validated, $tenantId);
        if (!$student) {
            throw ValidationException::withMessages([
                'student' => ['Student not found'],
            ]);
        }

        // Device guard check (feature-flagged)
        $this->deviceGuard->assert($request, $user, $student->hostel_id);

        // Find latest open approved outpass for today or last out gate entry
        $outpass = $this->findLatestOpenOutpass($student, $tenantId);
        $outGateEntry = $this->findLatestOutEntry($student, $tenantId);

        // Use outpass from out gate entry if no direct outpass found
        if (!$outpass && $outGateEntry?->outpass_id) {
            $outpass = OutPass::find($outGateEntry->outpass_id);
        }

        // Calculate late minutes
        $lateMinutes = 0;
        if ($outpass && $outpass->valid_until) {
            $now = Carbon::now('Asia/Kolkata');
            $returnBy = Carbon::parse($outpass->valid_until)->setTimezone('Asia/Kolkata');
            // If now > returnBy, student is late
            if ($now->greaterThan($returnBy)) {
                $lateMinutes = (int) $now->diffInMinutes($returnBy, false);
                // Ensure positive value for API response
                if ($lateMinutes < 0) {
                    $lateMinutes = abs($lateMinutes);
                }
            }
        }

        // Check if hostel requires QR during curfew
        $hostel = $student->hostel;
        $isDuringCurfew = $hostel?->isDuringCurfew() ?? false;
        $qrRequiredDuringCurfew = $hostel?->isQrRequiredNow() ?? false;
        $method = $validated['method'];

        // During curfew, only QR, OTP, or backup_code are allowed (no manual)
        if ($qrRequiredDuringCurfew && $method === 'manual') {
            throw ValidationException::withMessages([
                'method' => ['E_CURFEW_VERIFICATION_REQUIRED: During curfew hours, QR scan, OTP, or backup code verification is required'],
            ]);
        }

        // Verify based on method
        $verified = true;
        if ($method === 'otp') {
            $verified = $this->otpVerifier->check($student, $validated['otp_code'] ?? '');
        } elseif ($method === 'backup_code') {
            // Verify backup code against the outpass
            if (!$outpass) {
                throw ValidationException::withMessages([
                    'backup_code' => ['E_NO_OUTPASS: No outpass found to verify backup code against'],
                ]);
            }
            $verified = $outpass->verifyBackupCode($validated['backup_code'] ?? '');
            if ($verified) {
                $outpass->markBackupCodeUsed();
            }
        } elseif ($method === 'manual') {
            $verified = false;
        }

        // Create gate entry
        $gateEntry = GateEntry::create([
            'tenant_id' => $tenantId,
            'hostel_id' => $student->hostel_id,
            'student_id' => $student->id,
            'outpass_id' => $outpass?->id,
            'event' => 'student_entry',
            'occurred_at' => now(),
            'source' => 'web',
            'direction' => GateEntry::DIRECTION_IN,
            'method' => $method,
            'verified' => $verified,
            'verified_at' => $verified ? now() : null,
            'guard_user_id' => $user->id,
            'guard_id' => $user->id,
            'note' => $validated['note'] ?? null,
            'late_minutes' => $lateMinutes,
        ]);

        // Audit logs
        if ($lateMinutes > 0) {
            $this->auditLogger->log('gate.late_return', $gateEntry, [
                'student_id' => $student->id,
                'late_minutes' => $lateMinutes,
                'outpass_id' => $outpass?->id,
            ]);

            // SMS: Late Return Alert to leadership
            if ($verified && $outpass) {
                $tenantIdStr = (string) $tenantId;
                $hostelIdInt = (int) $student->hostel_id;

                $rector        = $this->recipients->rectorForHostel($tenantIdStr, $hostelIdInt);
                $warden        = $this->recipients->wardenForHostel($tenantIdStr, $hostelIdInt);
                $campusManager = $this->recipients->campusManagerForTenant($tenantIdStr);

                $hostelName  = $student->hostel?->name ?? 'N/A';
                $studentName = $student->user->name ?? $student->full_name ?? 'Student';
                $actualTime  = now('Asia/Kolkata')->format('H:i');
                $curfewTime  = optional($outpass->valid_until)->setTimezone('Asia/Kolkata')->format('H:i');

                $message = "OMAPMS: Alert: {$studentName} returned late at {$actualTime} (Curfew: {$curfewTime}). Hostel: {$hostelName}.";

                $payload = [
                    'related_type' => 'outpass_late_return',
                    'related_id' => (string) $outpass->id,
                    'student_id' => $student->id,
                    'late_minutes' => $lateMinutes,
                ];

                foreach ([$rector, $warden, $campusManager] as $recipient) {
                    if ($recipient && $recipient->phone) {
                        $this->smsService->send(
                            $recipient->phone,
                            $message,
                            (string) $tenantId,
                            'late_return_alert',
                            $payload
                        );
                    }
                }
            }
        }

        $this->auditLogger->log('gate.in', $gateEntry, [
            'student_id' => $student->id,
            'method' => $method,
            'verified' => $verified,
        ]);

        // Send metrics
        Metrics::count('GateIn', 1, [
            'tenant_id' => $tenantId,
            'hostel_id' => $student->hostel_id,
            'method' => $method,
            'verified' => $verified ? 'true' : 'false',
            'late' => $lateMinutes > 0 ? '1' : '0',
        ]);

        return response()->json([
            'id' => $gateEntry->id,
            'late_minutes' => $lateMinutes,
        ], 201);
    }

    private function getAccessibleHostelIds($user): array
    {
        return \App\Support\HostelScope::idsFor($user);
    }

    private function resolveStudent(array $validated, int $tenantId): ?Student
    {
        if (isset($validated['student_id'])) {
            return Student::where('tenant_id', $tenantId)
                ->where('user_id', $validated['student_id'])
                ->first();
        }

        if (isset($validated['student_uid'])) {
            return Student::where('tenant_id', $tenantId)
                ->where('student_uid', $validated['student_uid'])
                ->first();
        }

        return null;
    }

    private function findApprovedOutpass(Student $student, int $tenantId): ?OutPass
    {
        $now = Carbon::now();
        $windowStart = $now->copy()->subMinutes(60); // 60 minutes before now

        return OutPass::query()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('status', 'approved')
            ->where('requested_at', '<=', $now)
            ->where('requested_at', '>=', $windowStart)
            ->first();
    }

    private function findLatestOpenOutpass(Student $student, int $tenantId): ?OutPass
    {
        return OutPass::query()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('status', 'approved')
            ->whereDate('requested_at', today())
            ->orderBy('requested_at', 'desc')
            ->first();
    }

    private function findLatestOutEntry(Student $student, int $tenantId): ?GateEntry
    {
        return GateEntry::query()
            ->where('tenant_id', $tenantId)
            ->where('student_id', $student->id)
            ->where('direction', GateEntry::DIRECTION_OUT)
            ->whereDate('created_at', today())
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Register a gate device
     */
    public function registerDevice(Request $request): JsonResponse
    {
        $this->authorize('devicesRegister', GateEntry::class);

        $validated = $request->validate([
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
            'device_uuid' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        $device = GateDevice::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'hostel_id' => $validated['hostel_id'],
                'device_uuid' => $validated['device_uuid'],
            ],
            [
                'name' => $validated['name'],
                'is_active' => true,
                'enrolled_by_user_id' => $user->id,
                'enrolled_at' => now(),
            ]
        );

        return response()->json([
            'id' => $device->id,
            'hostel_id' => $device->hostel_id,
            'device_uuid' => $device->device_uuid,
            'is_active' => $device->is_active,
        ], 201);
    }

    /**
     * Heartbeat from a gate device
     */
    public function heartbeat(Request $request): JsonResponse
    {
        $this->authorize('devicesHeartbeat', GateEntry::class);

        $validated = $request->validate([
            'device_uuid' => ['required', 'string'],
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
        ]);

        $user = $request->user();
        $device = GateDevice::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hostel_id', $validated['hostel_id'])
            ->where('device_uuid', $validated['device_uuid'])
            ->where('is_active', true)
            ->firstOrFail();

        $device->update(['last_seen_at' => now()]);

        return response()->json([
            'ok' => true,
            'last_seen_at' => $device->last_seen_at,
            'hostel_id' => $device->hostel_id,
        ]);
    }

    /**
     * List today's visitors for a hostel
     */
    public function listVisitors(Request $request): JsonResponse
    {
        $this->authorize('visitorsList', GateEntry::class);

        $validated = $request->validate([
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
        ]);

        $user = $request->user();
        $hostelId = $validated['hostel_id'];

        // Device guard check
        $this->deviceGuard->assert($request, $user, $hostelId);

        $hostel = Hostel::findOrFail($hostelId);
        $window = $this->visitingHoursService->getWindowForToday($hostel);

        $now = Carbon::now('Asia/Kolkata');
        $withinWindow = $now->between($window['start'], $window['end']);

        // Query guest_visits for the current calendar date (system time, not test clock)
        $visitDate = Carbon::createFromTimestamp(time(), 'Asia/Kolkata')->toDateString();

        $guestVisits = GuestVisit::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('hostel_id', $hostelId)
            ->whereDate('visit_date', $visitDate)
            ->whereIn('status', [GuestVisit::STATUS_PRE_REGISTERED, GuestVisit::STATUS_ALLOWED])
            ->get();

        $visitors = $guestVisits->map(function ($visit) use ($withinWindow) {
            return [
                'id' => $visit->id,
                'student_id' => $visit->student_id,
                'name' => $visit->name,
                'phone' => $visit->phone,
                'whom_to_meet' => $visit->whom_to_meet,
                'within_window' => $withinWindow,
            ];
        })->values()->toArray();

        return response()->json([
            'visitors' => $visitors,
            'window' => [
                'start' => $window['start']->toISOString(),
                'end' => $window['end']->toISOString(),
                'within_window' => $withinWindow,
            ],
        ]);
    }

    /**
     * Allow a visitor
     */
    public function allowVisitor(Request $request, int $id): JsonResponse
    {
        $this->authorize('visitorsAllow', GateEntry::class);

        $validated = $request->validate([
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
            'method' => ['required', 'in:qr,otp,manual'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $hostelId = $validated['hostel_id'];

        // Device guard check
        $this->deviceGuard->assert($request, $user, $hostelId);

        $hostel = Hostel::findOrFail($hostelId);
        $window = $this->visitingHoursService->getWindowForToday($hostel);

        $now = Carbon::now('Asia/Kolkata');

        // Enforce visiting hours
        if ($now->lessThan($window['start']) || $now->greaterThan($window['end'])) {
            throw ValidationException::withMessages([
                'window' => ['E_VISIT_WINDOW: Outside visiting hours'],
            ]);
        }

        // Find and update guest visit
        $guestVisit = GuestVisit::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->firstOrFail();

        $guestVisit->update([
            'status' => GuestVisit::STATUS_ALLOWED,
            'allowed_by_user_id' => $user->id,
            'allowed_at' => now(),
        ]);

        $this->auditLogger->log('gate.visitor_allowed', $hostel, [
            'visitor_id' => $id,
            'method' => $validated['method'],
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Deny a visitor
     */
    public function denyVisitor(Request $request, int $id): JsonResponse
    {
        $this->authorize('visitorsDeny', GateEntry::class);

        $validated = $request->validate([
            'hostel_id' => ['required', 'integer', 'exists:hostels,id'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $user = $request->user();
        $hostelId = $validated['hostel_id'];

        // Device guard check
        $this->deviceGuard->assert($request, $user, $hostelId);

        $hostel = Hostel::findOrFail($hostelId);

        // Find and update guest visit
        $guestVisit = GuestVisit::query()
            ->where('tenant_id', $user->tenant_id)
            ->where('id', $id)
            ->firstOrFail();

        $guestVisit->update([
            'status' => GuestVisit::STATUS_DENIED,
            'denied_by_user_id' => $user->id,
            'denied_at' => now(),
        ]);

        $this->auditLogger->log('gate.visitor_denied', $hostel, [
            'visitor_id' => $id,
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Get today's duty handovers for the caller's hostel(s)
     *
     * @param Request $request HTTP request with user authentication
     * @return JsonResponse List of duty handovers for today
     */
    public function listDutyHandovers(Request $request): JsonResponse
    {
        $this->authorize('listDutyHandovers', GateDutyHandover::class);

        $handovers = GateDutyHandover::query()
            ->where('tenant_id', auth()->user()->tenant_id)
            ->whereHas('hostel', function ($query) {
                $query->whereIn('id', auth()->user()->getAccessibleHostelIds());
            })
            ->where(function ($query) {
                $query->whereDate('shift_start', today())
                    ->orWhereDate('shift_end', today());
            })
            ->with(['guardUser', 'hostel'])
            ->orderBy('shift_start')
            ->get();

        return response()->json([
            'data' => $handovers->map(function (GateDutyHandover $handover) {
                return [
                    'id' => $handover->id,
                    'guard' => [
                        'id' => $handover->guardUser->id,
                        'name' => $handover->guardUser->name,
                    ],
                    'hostel' => [
                        'id' => $handover->hostel->id,
                        'name' => $handover->hostel->name,
                    ],
                    'shift_start' => $handover->shift_start->toISOString(),
                    'shift_end' => $handover->shift_end->toISOString(),
                    'status' => $handover->status,
                    'incidents_count' => $handover->incidents_count,
                    'entries_processed' => $handover->entries_processed,
                    'issues_reported' => $handover->issues_reported,
                    'is_active' => $handover->isActive(),
                    'is_today' => $handover->isToday(),
                    'shift_duration_hours' => $handover->getShiftDuration(),
                ];
            }),
        ]);
    }

    /**
     * Create a duty handover record
     *
     * @param Request $request HTTP request with handover details
     * @return JsonResponse Created handover record
     */
    public function createDutyHandover(Request $request): JsonResponse
    {
        $this->authorize('createDutyHandover', GateDutyHandover::class);

        $validated = $request->validate([
            'hostel_id' => 'required|exists:hostels,id',
            'shift_start' => 'required|date|after:now',
            'shift_end' => 'required|date|after:shift_start',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Ensure user has access to the specified hostel
        $hostel = Hostel::where('id', $validated['hostel_id'])
            ->where('tenant_id', auth()->user()->tenant_id)
            ->firstOrFail();

        $handover = GateDutyHandover::create([
            'tenant_id' => auth()->user()->tenant_id,
            'hostel_id' => $validated['hostel_id'],
            'guard_id' => auth()->id(),
            'shift_start' => $validated['shift_start'],
            'shift_end' => $validated['shift_end'],
            'notes' => $validated['notes'],
            'status' => 'active',
        ]);

        $this->auditLogger->log('gate.duty_handover.created', [
            'handover_id' => $handover->id,
            'hostel_id' => $handover->hostel_id,
            'shift_start' => $handover->shift_start,
            'shift_end' => $handover->shift_end,
        ]);

        $guards = $this->recipients->guardsForHostel((string) auth()->user()->tenant_id, (int) $handover->hostel_id);
        $shiftTime = $handover->shift_start?->setTimezone('Asia/Kolkata')->format('d M, H:i') ?? '';
        foreach ($guards as $guard) {
            if ($guard->id === auth()->id()) {
                continue;
            }

            $this->pushNotifier->toUserTemplate(
                $guard->id,
                'guard.post_change',
                [
                    'post_name' => $hostel->name,
                    'time' => $shiftTime,
                ],
                [
                    'type' => 'post_change',
                    'handover_id' => (string) $handover->id,
                    'hostel_id' => (string) $handover->hostel_id,
                ]
            );
        }

        return response()->json([
            'message' => 'Duty handover created successfully',
            'handover' => $handover->load(['guardUser', 'hostel']),
        ], 201);
    }

    /**
     * Complete a duty handover
     *
     * @param GateDutyHandover $handover The handover to complete
     * @param Request $request HTTP request with completion details
     * @return JsonResponse Updated handover record
     */
    public function completeDutyHandover(Request $request, GateDutyHandover $handover): JsonResponse
    {
        $this->authorize('completeDutyHandover', $handover);

        $validated = $request->validate([
            'incidents_count' => 'required|integer|min:0',
            'entries_processed' => 'required|integer|min:0',
            'issues_reported' => 'nullable|string|max:2000',
            'completion_notes' => 'nullable|string|max:1000',
        ]);

        $handover->update([
            'incidents_count' => $validated['incidents_count'],
            'entries_processed' => $validated['entries_processed'],
            'issues_reported' => $validated['issues_reported'],
        ]);

        $handover->markCompleted($validated['completion_notes']);

        $this->auditLogger->log('gate.duty_handover.completed', [
            'handover_id' => $handover->id,
            'incidents_count' => $handover->incidents_count,
            'entries_processed' => $handover->entries_processed,
        ]);

        return response()->json([
            'message' => 'Duty handover completed successfully',
            'handover' => $handover->load(['guardUser', 'hostel']),
        ]);
    }

    /**
     * Process QR scan for gate entry
     *
     * @param Request $request HTTP request with QR data
     * @return JsonResponse Scan result with student info
     */
    public function scanQR(Request $request): JsonResponse
    {
        $this->authorize('scanQR', GateEntry::class);

        $validated = $request->validate([
            'qr_data' => 'required|string',
            'action' => 'required|in:out,in',
        ]);

        $qrData = json_decode($validated['qr_data'], true);

        if (!$qrData || !isset($qrData['student_uid'])) {
            return response()->json([
                'error' => 'Invalid QR code format',
            ], 422);
        }

        // Find student by UID
        $student = Student::where('student_uid', $qrData['student_uid'])
            ->whereHas('user', function ($query) {
                $query->where('tenant_id', auth()->user()->tenant_id);
            })
            ->first();

        if (!$student) {
            return response()->json([
                'error' => 'Student not found',
            ], 404);
        }

        // Check if there's an approved outpass for this student
        $outpass = OutPass::where('student_id', $student->id)
            ->where('status', 'approved')
            ->where('tenant_id', auth()->user()->tenant_id)
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>', now());
            })
            ->orderBy('requested_at', 'desc')
            ->first();

        // Check curfew status
        $hostel = $student->hostel;
        $isDuringCurfew = $hostel?->isDuringCurfew() ?? false;
        $qrRequired = $hostel?->isQrRequiredNow() ?? false;

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_uid' => $student->student_uid,
                'name' => $student->user->name,
                'hostel' => $hostel?->name,
            ],
            'outpass' => $outpass ? [
                'id' => $outpass->id,
                'valid_until' => $outpass->valid_until?->toISOString(),
                'is_valid' => $outpass->isValidForExit(),
                'is_qr_scanned' => $outpass->isQrScanned(),
                'has_backup_code' => !empty($outpass->backup_code),
            ] : null,
            'curfew' => [
                'is_during_curfew' => $isDuringCurfew,
                'qr_required' => $qrRequired,
                'curfew_start' => $hostel?->curfew_start ?? '20:00',
                'curfew_end' => $hostel?->curfew_end ?? '06:00',
            ],
            'action' => $validated['action'],
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Verify and record an OutPass gate-pass scan (QR or backup code).
     *
     * Payload: { out_pass_id, backup_code, method }
     * Rules:
     * - Only allowed during the curfew scan window: 20:00 -> hostel curfew_time
     * - OutPass must be APPROVED, not expired, and not previously scanned/used
     * - Code must match (verifyBackupCode)
     */
    public function outpassScans(Request $request): JsonResponse
    {
        $this->authorize('scanQR', GateEntry::class);

        $validated = $request->validate([
            'out_pass_id' => ['required', 'integer', 'exists:out_passes,id'],
            'method' => ['required', 'in:qr,backup_code'],
            // backup_code is required only for the manual fallback flow.
            // QR scans should not require a backup code.
            'backup_code' => ['nullable', 'string', 'size:4', 'required_if:method,backup_code'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        $outPass = OutPass::query()
            ->where('tenant_id', $tenantId)
            ->with(['student.user', 'hostel'])
            ->findOrFail($validated['out_pass_id']);

        // Ensure caller has access to the outpass hostel
        $hostelIds = $this->getAccessibleHostelIds($user);
        if (!in_array($outPass->hostel_id, $hostelIds, true)) {
            return response()->json([
                'error' => 'forbidden',
                'message' => 'You do not have access to this hostel',
            ], 403);
        }

        $hostel = $outPass->hostel;
        $studentUserId = $outPass->student?->user_id;
        if (!$studentUserId) {
            return response()->json([
                'error' => 'invalid_outpass',
                'message' => 'Out-pass has no linked student user',
            ], 422);
        }
        $now = Carbon::now('Asia/Kolkata');

        // Curfew scan window: 20:00 -> hostel curfew_time (defaults to 22:00:00)
        $windowStart = $now->copy()->startOfDay()->setTime(20, 0, 0);
        $curfewTime = Carbon::createFromFormat('H:i:s', $hostel?->curfew_time ?? '22:00:00', 'Asia/Kolkata');
        $windowEnd = $now->copy()->startOfDay()->setTime($curfewTime->hour, $curfewTime->minute, $curfewTime->second);
        if ($windowEnd->lessThanOrEqualTo($windowStart)) {
            $windowEnd->addDay();
        }

        if (!$now->between($windowStart, $windowEnd)) {
            return response()->json([
                'error' => 'outside_curfew_window',
                'message' => 'QR scans are only accepted during curfew scan hours',
                'window' => [
                    'start' => $windowStart->toISOString(),
                    'end' => $windowEnd->toISOString(),
                ],
            ], 422);
        }

        if ($outPass->status !== OutPassStatus::APPROVED) {
            return response()->json([
                'error' => 'outpass_not_approved',
                'message' => 'Out-pass is not approved',
            ], 422);
        }

        if (!$outPass->valid_until || $now->greaterThan($outPass->valid_until)) {
            return response()->json([
                'error' => 'expired',
                'message' => 'Out-pass has expired',
                'valid_until' => $outPass->valid_until?->toISOString(),
            ], 422);
        }

        if ($outPass->isQrScanned()) {
            return response()->json([
                'error' => 'already_scanned',
                'message' => 'This QR has already been scanned',
                'qr_scanned_at' => $outPass->qr_scanned_at?->toISOString(),
            ], 409);
        }

        if ($validated['method'] === 'backup_code') {
            if (!$outPass->verifyBackupCode($validated['backup_code'] ?? '')) {
                return response()->json([
                    'error' => 'invalid_code',
                    'message' => $outPass->backup_code_used_at ? 'Backup code already used' : 'Invalid backup code',
                ], 422);
            }
        }

        DB::transaction(function () use ($outPass, $validated, $user, $tenantId, $studentUserId): void {
            $now = now('Asia/Kolkata');

            // Single-use: mark QR as consumed; for backup-code method, also mark the code as consumed.
            $outPass->forceFill([
                'qr_scanned_at' => $now,
                // For QR scans, we also mark backup code as used to prevent re-use via manual fallback.
                // (If backup codes are not enabled / not generated, this is still safe.)
                'backup_code_used_at' => $now,
                // Clear plain code after successful scan (student can no longer reveal it).
                'backup_code_plain' => null,
            ])->save();

            // Record scan audit trail
            $scan = GatePassScan::createScan(
                (string) $tenantId,
                (int) $studentUserId,
                (int) $user->id,
                json_encode([
                    'type' => 'OUTPASS_GATE_PASS',
                    'out_pass_id' => $outPass->id,
                    'method' => $validated['method'],
                ]),
                'exit',
                [
                    'out_pass_id' => (string) $outPass->id,
                    'hostel_id' => (string) $outPass->hostel_id,
                    'method' => $validated['method'],
                ]
            );

            $scan->update([
                'is_valid' => true,
                'rejection_reason' => null,
            ]);
        });

        return response()->json([
            'ok' => true,
            'message' => 'Allow entry',
            'out_pass_id' => (string) $outPass->id,
        ], 200);
    }

    /**
     * Send OTP to student for gate verification
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $this->authorize('sendOtp', GateEntry::class);

        $validated = $request->validate([
            'student_uid' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        $student = $this->resolveStudent($validated, $tenantId);
        if (!$student) {
            throw ValidationException::withMessages([
                'student' => ['Student not found'],
            ]);
        }

        $result = $this->otpVerifier->send($student);

        return response()->json([
            'sent' => $result['sent'],
            'remaining_attempts' => $this->otpVerifier->getRemainingAttempts($student),
        ]);
    }

    /**
     * Verify by 4-digit backup code only (no out_pass_id). Finds the matching approved outpass,
     * records the scan, and returns out_pass_id for the app to show the outpass detail screen.
     */
    public function outpassVerifyByCode(Request $request): JsonResponse
    {
        $this->authorize('scanQR', GateEntry::class);

        $validated = $request->validate([
            'backup_code' => ['required', 'string', 'size:4'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;
        $hostelIds = $this->getAccessibleHostelIds($user);
        $code = $validated['backup_code'];
        $now = Carbon::now('Asia/Kolkata');

        $candidates = OutPass::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('hostel_id', $hostelIds)
            ->where('status', OutPassStatus::APPROVED)
            ->whereNotNull('backup_code')
            ->whereNull('qr_scanned_at')
            ->whereNull('backup_code_used_at')
            ->where('requested_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('valid_until')->orWhere('valid_until', '>=', $now);
            })
            ->orderBy('requested_at', 'desc')
            ->get();

        foreach ($candidates as $outPass) {
            if (!$outPass->verifyBackupCode($code)) {
                continue;
            }

            $hostel = $outPass->hostel;
            $windowStart = $now->copy()->startOfDay()->setTime(20, 0, 0);
            $curfewTime = Carbon::createFromFormat('H:i:s', $hostel?->curfew_time ?? '22:00:00', 'Asia/Kolkata');
            $windowEnd = $now->copy()->startOfDay()->setTime($curfewTime->hour, $curfewTime->minute, $curfewTime->second);
            if ($windowEnd->lessThanOrEqualTo($windowStart)) {
                $windowEnd->addDay();
            }
            if (!$now->between($windowStart, $windowEnd)) {
                return response()->json([
                    'error' => 'outside_curfew_window',
                    'message' => 'Verification is only accepted during curfew scan hours',
                ], 422);
            }

            $studentUserId = $outPass->student?->user_id;
            if (!$studentUserId) {
                return response()->json(['error' => 'invalid_outpass', 'message' => 'Out-pass has no linked student'], 422);
            }

            DB::transaction(function () use ($outPass, $user, $tenantId, $studentUserId): void {
                $now = now('Asia/Kolkata');
                $outPass->forceFill([
                    'qr_scanned_at' => $now,
                    'backup_code_used_at' => $now,
                    'backup_code_plain' => null,
                ])->save();

                $scan = GatePassScan::createScan(
                    (string) $tenantId,
                    (int) $studentUserId,
                    (int) $user->id,
                    json_encode([
                        'type' => 'OUTPASS_GATE_PASS',
                        'out_pass_id' => $outPass->id,
                        'method' => 'backup_code',
                    ]),
                    'exit',
                    [
                        'out_pass_id' => (string) $outPass->id,
                        'hostel_id' => (string) $outPass->hostel_id,
                        'method' => 'backup_code',
                    ]
                );
                $scan->update(['is_valid' => true, 'rejection_reason' => null]);
            });

            return response()->json([
                'ok' => true,
                'message' => 'Allow entry',
                'out_pass_id' => (string) $outPass->id,
            ], 200);
        }

        return response()->json([
            'error' => 'invalid_code',
            'message' => 'Invalid or already used backup code',
        ], 422);
    }

    /**
     * Verify backup code for an outpass
     */
    public function verifyBackupCode(Request $request): JsonResponse
    {
        $this->authorize('verifyBackupCode', GateEntry::class);

        $validated = $request->validate([
            'student_uid' => ['nullable', 'string'],
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
            'backup_code' => ['required', 'string', 'size:4'],
        ]);

        $user = $request->user();
        $tenantId = $user->tenant_id;

        $student = $this->resolveStudent($validated, $tenantId);
        if (!$student) {
            throw ValidationException::withMessages([
                'student' => ['Student not found'],
            ]);
        }

        // Find latest approved outpass
        $outpass = $this->findLatestOpenOutpass($student, $tenantId);
        if (!$outpass) {
            return response()->json([
                'valid' => false,
                'error' => 'No approved outpass found',
            ]);
        }

        $isValid = $outpass->verifyBackupCode($validated['backup_code']);

        return response()->json([
            'valid' => $isValid,
            'outpass_id' => $isValid ? $outpass->id : null,
        ]);
    }
}
