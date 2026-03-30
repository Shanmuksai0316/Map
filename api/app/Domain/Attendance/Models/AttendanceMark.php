<?php

namespace App\Domain\Attendance\Models;

use App\Models\Room;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceMark extends Model
{
    use HasFactory;

    protected $table = 'attendance_logs'; // Map to existing table

    public const STATUS_UNMARKED = 'unmarked';
    public const STATUS_PRESENT = 'present';
    public const STATUS_ABSENT = 'absent';
    public const STATUS_LEAVE = 'leave';

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'attendance_session_id',
        'attendance_date',
        'student_id',
        'status',
        'marked_at',
        'marked_by',
        'notes', // Column name in database is 'notes' (plural)
        'metadata',
    ];

    protected $casts = [
        'marked_at' => 'datetime',
        'attendance_date' => 'date',
        'metadata' => 'array',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function scopeForSession($query, int $sessionId)
    {
        return $query->where('attendance_session_id', $sessionId);
    }
}
