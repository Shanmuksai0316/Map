<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Crypt;

class GatePassScan extends Model
{
    protected $fillable = [
        'scan_id',
        'tenant_id',
        'student_id',
        'scanned_by_user_id',
        'qr_data',
        'scan_type',
        'gate_location',
        'device_id',
        'qr_metadata',
        'is_valid',
        'rejection_reason',
        'scan_timestamp',
    ];

    protected $casts = [
        'qr_metadata' => 'array',
        'is_valid' => 'boolean',
        'scan_timestamp' => 'datetime',
    ];

    protected $hidden = [
        'qr_data', // Encrypted data should be hidden
    ];

    /**
     * Get the tenant that owns the gate pass scan.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the student that was scanned.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the user who performed the scan.
     */
    public function scannedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by_user_id');
    }

    /**
     * Generate a new scan ID.
     */
    public static function generateScanId(): string
    {
        return 'scan_' . uniqid() . '_' . time();
    }

    /**
     * Encrypt QR data before storing.
     */
    public function setQrDataAttribute($value): void
    {
        $this->attributes['qr_data'] = Crypt::encryptString($value);
    }

    /**
     * Decrypt QR data when retrieving.
     */
    public function getQrDataAttribute($value): string
    {
        return Crypt::decryptString($value);
    }

    /**
     * Create a new gate pass scan record.
     */
    public static function createScan(
        string $tenantId,
        int $studentId,
        int $scannedByUserId,
        string $qrData,
        string $scanType,
        array $qrMetadata,
        string $gateLocation = null,
        string $deviceId = null
    ): self {
        return self::create([
            'scan_id' => self::generateScanId(),
            'tenant_id' => $tenantId,
            'student_id' => $studentId,
            'scanned_by_user_id' => $scannedByUserId,
            'qr_data' => $qrData,
            'scan_type' => $scanType,
            'gate_location' => $gateLocation,
            'device_id' => $deviceId,
            'qr_metadata' => $qrMetadata,
            'scan_timestamp' => now(),
        ]);
    }

    /**
     * Mark the scan as invalid with a reason.
     */
    public function markAsInvalid(string $reason): void
    {
        $this->update([
            'is_valid' => false,
            'rejection_reason' => $reason,
        ]);
    }

    /**
     * Get scan statistics for a tenant.
     */
    public static function getScanStats(string $tenantId, int $days = 30): array
    {
        $startDate = now()->subDays($days);
        
        return [
            'total_scans' => self::where('tenant_id', $tenantId)
                ->where('scan_timestamp', '>=', $startDate)
                ->count(),
            'valid_scans' => self::where('tenant_id', $tenantId)
                ->where('scan_timestamp', '>=', $startDate)
                ->where('is_valid', true)
                ->count(),
            'invalid_scans' => self::where('tenant_id', $tenantId)
                ->where('scan_timestamp', '>=', $startDate)
                ->where('is_valid', false)
                ->count(),
            'entry_scans' => self::where('tenant_id', $tenantId)
                ->where('scan_timestamp', '>=', $startDate)
                ->where('scan_type', 'entry')
                ->count(),
            'exit_scans' => self::where('tenant_id', $tenantId)
                ->where('scan_timestamp', '>=', $startDate)
                ->where('scan_type', 'exit')
                ->count(),
        ];
    }
}
