<?php

namespace App\Services\Gate;

use App\Models\Student;
use App\Services\OtpService;
use Illuminate\Support\Facades\Log;

class OtpVerifier
{
    private const PURPOSE = 'gate_entry';

    public function __construct(
        private readonly OtpService $otpService
    ) {}

    /**
     * Send OTP to student for gate verification.
     *
     * @return array{sent: bool, debug_code?: string}
     */
    public function send(Student $student): array
    {
        $user = $student->user;
        if (!$user) {
            Log::warning('gate.otp.no_user', ['student_id' => $student->id]);
            return ['sent' => false];
        }

        $phone = $user->phone ?? $student->phone;
        if (!$phone) {
            Log::warning('gate.otp.no_phone', [
                'student_id' => $student->id,
                'user_id' => $user->id,
            ]);
            return ['sent' => false];
        }

        try {
            $result = $this->otpService->start(
                userId: $user->id,
                purpose: self::PURPOSE,
                channel: 'sms',
                to: $phone,
                tenantId: (string) $student->tenant_id
            );

            Log::info('gate.otp.sent', [
                'student_id' => $student->id,
                'user_id' => $user->id,
                'phone_last4' => substr($phone, -4),
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('gate.otp.send_failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return ['sent' => false];
        }
    }

    /**
     * Verify OTP for a student.
     * 
     * OTP bypass: If GATE_OTP_BYPASS env is true, accepts any non-empty code for testing.
     * Otherwise, uses real MSG91 OTP verification.
     */
    public function check(Student $student, string $otpCode): bool
    {
        $user = $student->user;
        if (!$user) {
            Log::warning('gate.otp.verify.no_user', ['student_id' => $student->id]);
            return false;
        }

        if (empty($otpCode)) {
            Log::warning('gate.otp.verify.empty_code', ['student_id' => $student->id]);
            return false;
        }

        // OTP bypass for testing/development
        if (config('services.gate.otp_bypass', env('GATE_OTP_BYPASS', false))) {
            Log::info('gate.otp.bypass', [
                'student_id' => $student->id,
                'user_id' => $user->id,
                'bypass_enabled' => true,
            ]);
            return true; // Bypass enabled - accept any non-empty code
        }

        try {
            $isValid = $this->otpService->verify(
                userId: $user->id,
                purpose: self::PURPOSE,
                code: $otpCode,
                tenantId: (string) $student->tenant_id
            );

            Log::info('gate.otp.verify', [
                'student_id' => $student->id,
                'user_id' => $user->id,
                'valid' => $isValid,
            ]);

            return $isValid;
        } catch (\Exception $e) {
            Log::error('gate.otp.verify_failed', [
                'student_id' => $student->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check if OTP was recently verified for this student.
     */
    public function recentlyVerified(Student $student): bool
    {
        $user = $student->user;
        if (!$user) {
            return false;
        }

        return $this->otpService->recentlyVerified(
            userId: $user->id,
            purpose: self::PURPOSE,
            tenantId: (string) $student->tenant_id
        );
    }

    /**
     * Get remaining OTP attempts for this student.
     */
    public function getRemainingAttempts(Student $student): int
    {
        $user = $student->user;
        if (!$user) {
            return 0;
        }

        return $this->otpService->getRemainingAttempts(
            userId: $user->id,
            purpose: self::PURPOSE,
            tenantId: (string) $student->tenant_id
        );
    }
}
