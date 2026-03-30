<?php

namespace App\Domain\Checklists\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChecklistInstance extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'Pending';
    public const STATUS_SUBMITTED = 'Submitted';
    public const STATUS_APPROVED = 'Approved';
    public const STATUS_SENT_BACK = 'SentBack';

    protected $fillable = [
        'tenant_id',
        'template_id',
        'date',
        'shift',
        'role',
        'assignee_user_id',
        'status',
        'review_status',
        'total_tasks',
        'completed_tasks',
        'submitted_at',
        'manager_user_id',
        'manager_note',
        'reviewed_at',
        'due_at',
        'completed_at',
        'morning_reminded_at',
        'afternoon_reminded_at',
        'overdue_notified_at',
    ];

    protected $casts = [
        'date' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
        'morning_reminded_at' => 'datetime',
        'afternoon_reminded_at' => 'datetime',
        'overdue_notified_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(ChecklistTemplate::class, 'template_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(ChecklistItem::class, 'instance_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_user_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Checklists\ChecklistInstanceFactory::new();
    }

    public function recalcCompleted(): void
    {
        $completed = $this->items()->where('state', 'Done')->count();
        $this->forceFill(['completed_tasks' => $completed])->save();
    }
}

