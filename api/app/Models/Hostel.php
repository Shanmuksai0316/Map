<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Hostel Model (Single Shared Database)
 * 
 * Stored in central database with tenant_id scoping.
 * Tenant isolation is enforced via TenantScope global scope and PostgreSQL RLS.
 */
class Hostel extends Model
{
    use HasFactory;
    use Traits\TenantScoped;

    protected $fillable = [
        'tenant_id',
        'campus_id',
        'code',
        'name',
        'gender_mode',
        'location',
        'address',
        'curfew_time',
        'curfew_start',
        'curfew_end',
        'qr_required_during_curfew',
        'backup_codes_enabled',
        'overnight_enabled',
        'visiting_start',
        'visiting_end',
        'settings',
    ];

    protected $casts = [
        'overnight_enabled' => 'boolean',
        'qr_required_during_curfew' => 'boolean',
        'backup_codes_enabled' => 'boolean',
        'settings' => 'array',
        'address' => 'array',
    ];

    /**
     * Get the tenant this hostel belongs to.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function staff()
    {
        return $this->belongsToMany(\App\Models\User::class, 'staff_assignments')
            ->wherePivotNull('revoked_at');
    }

    public function amenities()
    {
        return $this->belongsToMany(\App\Models\Amenity::class, 'hostel_amenities');
    }

    public function modules()
    {
        return $this->hasMany(\App\Models\HostelModule::class);
    }

    /**
     * Check if current time is within curfew hours.
     *
     * Curfew typically spans overnight (e.g., 20:00 to 06:00).
     * During curfew, stricter verification (QR/backup code) may be required.
     */
    public function isDuringCurfew(): bool
    {
        if (!$this->curfew_start || !$this->curfew_end) {
            // If curfew times not set, default to 20:00-06:00
            $curfewStart = '20:00';
            $curfewEnd = '06:00';
        } else {
            $curfewStart = $this->curfew_start;
            $curfewEnd = $this->curfew_end;
        }

        $now = Carbon::now('Asia/Kolkata');
        $currentTime = $now->format('H:i');

        // Handle overnight curfew (e.g., 20:00 to 06:00)
        if ($curfewStart > $curfewEnd) {
            // Curfew spans midnight
            return $currentTime >= $curfewStart || $currentTime < $curfewEnd;
        }

        // Regular curfew (e.g., 22:00 to 23:00)
        return $currentTime >= $curfewStart && $currentTime < $curfewEnd;
    }

    /**
     * Check if QR verification is required at the current time.
     */
    public function isQrRequiredNow(): bool
    {
        if (!$this->qr_required_during_curfew) {
            return false;
        }

        return $this->isDuringCurfew();
    }

    /**
     * Check if backup codes are enabled for this hostel.
     */
    public function areBackupCodesEnabled(): bool
    {
        return $this->backup_codes_enabled ?? true;
    }

    /**
     * Get curfew start time as Carbon instance.
     */
    public function getCurfewStartTime(): ?Carbon
    {
        if (!$this->curfew_start) {
            return Carbon::now('Asia/Kolkata')->setTimeFromTimeString('20:00');
        }
        return Carbon::now('Asia/Kolkata')->setTimeFromTimeString($this->curfew_start);
    }

    /**
     * Get curfew end time as Carbon instance.
     */
    public function getCurfewEndTime(): ?Carbon
    {
        if (!$this->curfew_end) {
            return Carbon::now('Asia/Kolkata')->setTimeFromTimeString('06:00');
        }
        return Carbon::now('Asia/Kolkata')->setTimeFromTimeString($this->curfew_end);
    }
}
