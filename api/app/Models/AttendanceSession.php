<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSession extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'campus_id',
        'hostel_id',
        'session_date',
        'session_time',
        'name',
        'kind',
        'scheduled_at',
        'status',
        'metadata',
    ];

    protected $casts = [
        'session_date' => 'date',
        'scheduled_at' => 'datetime',
        'metadata' => 'array',
    ];

    /**
     * Tenant relationship.
     *
     * Must be an Eloquent relationship for Filament/Eloquent internals.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceLog::class);
    }
}
