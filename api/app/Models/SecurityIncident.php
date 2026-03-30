<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityIncident extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'reported_by_user_id',
        'title',
        'description',
        'severity',
        'location',
        'status',
        'resolved_at',
        'resolved_by_user_id',
        'resolution_notes',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    /**
     * Get the tenant this incident belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who reported the incident
     */
    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    /**
     * Get the user who resolved the incident
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    /**
     * Scope to filter by severity
     */
    public function scopeSeverity($query, string $severity)
    {
        return $query->where('severity', $severity);
    }

    /**
     * Scope to get open incidents
     */
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    /**
     * Scope to get resolved incidents
     */
    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }
}

