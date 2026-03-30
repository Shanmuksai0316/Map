<?php

namespace App\Domain\Attendance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceSessionV2 extends Model
{
    protected $table = 'attendance_sessions';
    
    protected $fillable = [
        'tenant_id',
        'hostel_id', 
        'session_date',
        'scheduled_at',
        'status',
        'metadata'
    ];
    
    protected $casts = [
        'scheduled_at' => 'datetime',
        'metadata' => 'array'
    ];

    public function scopeForTenant($q, $tenantId) 
    { 
        return $q->where('tenant_id', $tenantId); 
    }
    
    public function scopeForHostel($q, $hostelId) 
    { 
        return $q->where('hostel_id', $hostelId); 
    }
    
    public function scopeToday($q) 
    { 
        return $q->where('session_date', now('Asia/Kolkata')->toDateString()); 
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Hostel::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(\App\Models\AttendanceLog::class, 'session_id');
    }
}
