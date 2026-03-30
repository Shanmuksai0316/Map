<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExportJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id', // Required - ExportJob is in central database
        'user_id',
        'type',
        'filters',
        'status',
        'file_url',
        'file_key',
        'total_rows',
        'error_message',
        'completed_at',
        'expires_at',
    ];

    protected $casts = [
        'filters' => 'array',
        'completed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the tenant this model belongs to.
     * With database-per-tenant, tenant context is automatic.
     */
    public function tenant(): ?\App\Models\Tenant
    {
        return tenancy()->tenant;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Export types
     */
    public const TYPE_STUDENTS = 'students';
    public const TYPE_OUTPASSES = 'outpasses';
    public const TYPE_ATTENDANCE = 'attendance';
    public const TYPE_GATE_ENTRIES = 'gate_entries';
    public const TYPE_PAYMENTS = 'payments';
    public const TYPE_TICKETS = 'tickets';
    public const TYPE_LAUNDRY = 'laundry';
    public const TYPE_SPORTS = 'sports';

    /**
     * Mark export as complete
     */
    public function markComplete(string $fileUrl, string $fileKey, int $totalRows): void
    {
        $this->update([
            'status' => 'Ready',
            'file_url' => $fileUrl,
            'file_key' => $fileKey,
            'total_rows' => $totalRows,
            'completed_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);
    }

    /**
     * Mark export as failed
     */
    public function markFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'Failed',
            'error_message' => $errorMessage,
            'completed_at' => now(),
        ]);
    }

    /**
     * Check if export is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}

