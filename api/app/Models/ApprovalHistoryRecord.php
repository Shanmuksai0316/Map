<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Virtual model for Approval History
 * This model doesn't have a real table - it's used to represent combined approval history
 * from OutPasses, Leaves, and SickLeaves
 */
class ApprovalHistoryRecord extends Model
{
    protected $table = null; // No actual table
    
    public $timestamps = false;
    
    protected $fillable = [
        'type',
        'unique_id',
        'student_name',
        'hostel_name',
        'decision',
        'decided_at',
        'decided_by',
        'note',
        'timeline_label',
        'timeline_description',
    ];
    
    protected $casts = [
        'decided_at' => 'datetime',
    ];
}
