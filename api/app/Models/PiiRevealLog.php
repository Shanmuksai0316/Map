<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * PII Reveal Log Model (Tenant Database)
 * 
 * Tracks access to Personally Identifiable Information (PII) for audit purposes.
 * Stores in tenant database - automatic isolation by database.
 * NO tenant_id needed with database-per-tenant architecture.
 */
class PiiRevealLog extends Model
{
    protected $fillable = [
        'user_id',
        'student_id',
        'pii_type',
        'ip_address',
        'user_agent',
        'revealed_at',
        'metadata',
    ];

    protected $casts = [
        'revealed_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Get the user who revealed the PII
     */
    public function user(): BelongsTo
    {
        // Cross-database relationship: PiiRevealLog (tenant DB) -> User (central DB)
        return $this->belongsTo(User::class);
    }

    /**
     * Get the student whose PII was revealed
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Scope to filter by PII type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('pii_type', $type);
    }

    /**
     * Scope to filter by user
     */
    public function scopeByUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to filter by student
     */
    public function scopeByStudent($query, int $studentId)
    {
        return $query->where('student_id', $studentId);
    }
}
