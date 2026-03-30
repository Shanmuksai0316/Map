<?php

namespace App\Models;

use App\Models\Student;
use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLog extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'hostel_id',
        'attendance_session_id',
        'session_id',
        'attendance_date',
        'student_id',
        'status',
        'marked_at',
        'marked_by',
        'note',
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
        return $this->belongsTo(Student::class);
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
