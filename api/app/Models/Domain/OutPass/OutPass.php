<?php

namespace App\Models\Domain\OutPass;

use App\Enums\OutPassStatus;
use App\Enums\OutPassType;
use App\Models\Hostel;
use App\Models\Student;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class OutPass extends Model
{
    /** @use HasFactory<\Database\Factories\Domain\OutPass\OutPassFactory> */
    use HasFactory;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($outPass) {
            if (empty($outPass->sla_due_at) && !empty($outPass->requested_at)) {
                // 2-hour SLA for Out-Pass
                $outPass->sla_due_at = $outPass->requested_at->copy()->addHours(2);
            }
        });
    }

    protected $fillable = [
        'tenant_id',
        'student_id',
        'hostel_id',
        'unique_id',
        'reason',
        'overnight',
        'status',
        'requested_at',
        'requested_for',
        'decided_at',
        'valid_until',
        'note',
        'idempotency_key',
        'decision_by',
        'sla_due_at',
        'sla_breached_at',
        'sla_warning_sent_at',
        'backup_code',
        'backup_code_plain',
        'backup_code_used_at',
        'qr_scanned_at',
    ];

    protected $casts = [
        'reason' => OutPassType::class,
        'status' => OutPassStatus::class,
        'overnight' => 'boolean',
        'requested_at' => 'datetime',
        'requested_for' => 'date',
        'decided_at' => 'datetime',
        'valid_until' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'sla_warning_sent_at' => 'datetime',
        'backup_code_plain' => 'encrypted',
        'backup_code_used_at' => 'datetime',
        'qr_scanned_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OutPassHistory::class);
    }

    public function recordHistory(?OutPassStatus $from, OutPassStatus $to, ?string $note = null, ?int $actorId = null, ?string $label = null, ?string $description = null): void
    {
        $this->histories()->create([
            'tenant_id' => $this->tenant_id,
            'acted_by' => $actorId ?? Auth::id(),
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'note' => $note,
            'timeline_label' => $label,
            'timeline_description' => $description,
            'changed_at' => now(),
        ]);
    }

    /**
     * Check if this outpass is valid for student exit.
     *
     * An outpass is valid for exit if:
     * - Status is approved
     * - Current time is within the valid window (requested_at to valid_until)
     * - QR has not already been scanned for exit
     */
    public function isValidForExit(): bool
    {
        if ($this->status !== OutPassStatus::APPROVED) {
            return false;
        }

        $now = Carbon::now('Asia/Kolkata');

        // Check if within valid time window
        if ($this->requested_at && $now->lessThan($this->requested_at->copy()->subMinutes(60))) {
            return false; // Too early
        }

        if ($this->valid_until && $now->greaterThan($this->valid_until)) {
            return false; // Expired
        }

        return true;
    }

    /**
     * Check if this outpass is valid for student entry (return).
     */
    public function isValidForEntry(): bool
    {
        if ($this->status !== OutPassStatus::APPROVED) {
            return false;
        }

        // For entry, we allow even if past valid_until (to track late returns)
        return true;
    }

    /**
     * Check if the student is returning late.
     */
    public function isLateReturn(): bool
    {
        if (!$this->valid_until) {
            return false;
        }

        return Carbon::now('Asia/Kolkata')->greaterThan($this->valid_until);
    }

    /**
     * Get late return minutes (0 if not late).
     */
    public function getLateMinutes(): int
    {
        if (!$this->isLateReturn()) {
            return 0;
        }

        return (int) abs(Carbon::now('Asia/Kolkata')->diffInMinutes($this->valid_until));
    }

    /**
     * Generate a 4-digit backup code for this outpass.
     */
    public function generateBackupCode(): string
    {
        $code = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        $this->update([
            'backup_code' => bcrypt($code),
            // Store encrypted plain code so the student app can display it.
            // (Guard verification still uses the hashed `backup_code` column.)
            'backup_code_plain' => $code,
        ]);
        return $code;
    }

    /**
     * Verify a backup code against this outpass.
     */
    public function verifyBackupCode(string $code): bool
    {
        if (!$this->backup_code) {
            return false;
        }

        if ($this->backup_code_used_at) {
            return false; // Already used
        }

        return password_verify($code, $this->backup_code);
    }

    /**
     * Mark the backup code as used.
     */
    public function markBackupCodeUsed(): void
    {
        $this->update(['backup_code_used_at' => now()]);
    }

    /**
     * Mark QR as scanned for exit.
     */
    public function markQrScanned(): void
    {
        $this->update(['qr_scanned_at' => now()]);
    }

    /**
     * Check if QR has already been scanned.
     */
    public function isQrScanned(): bool
    {
        return $this->qr_scanned_at !== null;
    }
}
