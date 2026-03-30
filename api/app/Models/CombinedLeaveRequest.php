<?php

namespace App\Models;

use App\Domain\Leaves\Models\Leave;
use App\Domain\SickLeaves\Models\SickLeave;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Virtual model for combined leave requests (Leave + SickLeave)
 */
class CombinedLeaveRequest extends Model
{
    protected $table = 'combined_leave_requests';

    protected $fillable = [
        'id',
        'type',
        'tenant_id',
        'student_id',
        'hostel_id',
        'unique_id',
        'title',
        'description',
        'reason',
        'from_date',
        'to_date',
        'status',
        'submitted_at',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'sla_due_at',
        'sla_breached_at',
        'sla_warning_sent_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'sla_due_at' => 'datetime',
        'sla_breached_at' => 'datetime',
        'sla_warning_sent_at' => 'datetime',
        'from_date' => 'date',
        'to_date' => 'date',
    ];

    /**
     * Override the query to use our custom logic
     */
    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope('combined_union', function ($builder) {
            // Tenant and status are applied by consuming queries.
            // This scope only builds a unified Leave + Sick Leave dataset.

            // Build union query for leaves
            $leavesQuery = DB::query()
                ->select([
                    'id',
                    DB::raw("'leave' as type"),
                    'tenant_id',
                    'student_id',
                    'hostel_id',
                    'unique_id',
                    'title',
                    'description',
                    'reason_for_leave as reason',
                    'from_date',
                    'to_date',
                    'status',
                    'submitted_at',
                    'approved_by',
                    'approved_at',
                    'rejection_reason',
                    'sla_due_at',
                    'sla_breached_at',
                    'sla_warning_sent_at',
                ])
                ->from('leaves');

            // Build union query for sick leaves
            $sickLeavesQuery = DB::query()
                ->select([
                    'id',
                    DB::raw("'sick_leave' as type"),
                    'tenant_id',
                    'student_id',
                    'hostel_id',
                    'unique_id',
                    'title',
                    'description',
                    'illness as reason',
                    DB::raw('NULL as from_date'),
                    DB::raw('NULL as to_date'),
                    'status',
                    'submitted_at',
                    'approved_by',
                    'approved_at',
                    'rejection_reason',
                    'sla_due_at',
                    'sla_breached_at',
                    'sla_warning_sent_at',
                ])
                ->from('sick_leaves');

            // Alias must match model table so Filament record resolution works.
            $builder->fromSub($leavesQuery->union($sickLeavesQuery), 'combined_leave_requests');
        });
    }

    /**
     * Get the underlying model instance
     */
    public function getUnderlyingModel()
    {
        return $this->type === 'leave'
            ? Leave::find($this->id)
            : SickLeave::find($this->id);
    }

    /**
     * Get the student relationship
     */
    public function student()
    {
        return $this->belongsTo(\App\Models\Student::class);
    }

    /**
     * Get the hostel relationship
     */
    public function hostel()
    {
        return $this->belongsTo(\App\Models\Hostel::class);
    }
}
