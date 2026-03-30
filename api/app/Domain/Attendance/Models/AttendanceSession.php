<?php

namespace App\Domain\Attendance\Models;

use App\Models\Hostel;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class AttendanceSession extends Model
{
    use HasFactory;

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_OPEN = 'open';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id', // Required for mass assignment in jobs
        'campus_id',
        'hostel_id',
        'name',
        'kind',
        'scheduled_at',
        'session_date', // Support legacy columns for backward compatibility
        'session_time', // Support legacy columns for backward compatibility
        'status',
        'metadata',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'session_date' => 'date',
        'session_time' => 'datetime', // Will be cast as time
        'metadata' => 'array',
    ];
    
    /**
     * Get scheduled_at from session_date + session_time if not set
     */
    public function getScheduledAtAttribute($value)
    {
        if ($value) {
            return $value;
        }
        
        // Compute from session_date + session_time for backward compatibility
        if ($this->session_date && $this->session_time) {
            return Carbon::parse($this->session_date)
                ->setTimeFromTimeString($this->session_time->format('H:i:s'));
        }
        
        return null;
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function marks(): HasMany
    {
        return $this->hasMany(AttendanceMark::class, 'attendance_session_id');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AttendanceMark::class, 'attendance_session_id');
    }

    public function isOpen(?Carbon $now = null): bool
    {
        $now = $now ?? Carbon::now('Asia/Kolkata');
        
        $openAt = $this->metadata['open_at'] ?? null;
        $closeAt = $this->metadata['close_at'] ?? null;
        
        if (!$openAt || !$closeAt) {
            return false;
        }
        
        return $now->betweenIncluded(Carbon::parse($openAt), Carbon::parse($closeAt)->copy()->subSecond());
    }

    public function recalcCounts(): void
    {
        $presentCount = $this->marks()->where('status', 'present')->count();
        $absentCount = $this->marks()->where('status', 'absent')->count();
        $excusedCount = $this->marks()->where('status', 'excused')->count();
        
        $this->metadata = array_merge($this->metadata ?? [], [
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'excused_count' => $excusedCount,
        ]);
        
        $this->save();
    }

    public function recomputeCounts(): void
    {
        $presentCount = $this->logs()->where('status', 'present')->count();
        $absentCount = $this->logs()->where('status', 'absent')->count();
        $leaveCount = $this->logs()->where('status', 'leave')->count();
        
        $this->metadata = array_merge($this->metadata ?? [], [
            'present_count' => $presentCount,
            'absent_count' => $absentCount,
            'leave_count' => $leaveCount,
        ]);
        
        $this->saveQuietly();
    }
}
