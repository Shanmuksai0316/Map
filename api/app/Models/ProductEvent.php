<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        // 'tenant_id', // REMOVED - automatic isolation
'campus_id',
        'hostel_id',
        'user_id',
        'role',
        'name',
        'entity_type',
        'entity_id',
        'properties',
        'happened_at',
    ];

    protected $casts = [
        'properties' => 'array',
        'happened_at' => 'datetime',
    ];

    /**
     * Tenant relationship.
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Event catalog constants
     */
    // Out-Pass events
    public const EVENT_OUTPASS_CREATED = 'outpass.created';
    public const EVENT_OUTPASS_APPROVED = 'outpass.approved';
    public const EVENT_OUTPASS_DECLINED = 'outpass.declined';
    public const EVENT_OUTPASS_EXPIRED = 'outpass.expired';
    public const EVENT_OUTPASS_CANCELLED = 'outpass.cancelled';
    
    // Gate events
    public const EVENT_GATE_EXIT = 'gate.exit';
    public const EVENT_GATE_ENTRY = 'gate.entry';
    public const EVENT_GATE_EMERGENCY_EXIT = 'gate.emergency_exit';
    
    // Attendance events
    public const EVENT_ATTENDANCE_MARKED = 'attendance.marked';
    public const EVENT_ATTENDANCE_SESSION_OPENED = 'attendance.session_opened';
    public const EVENT_ATTENDANCE_SESSION_CLOSED = 'attendance.session_closed';
    
    // Ticket events
    public const EVENT_TICKET_CREATED = 'ticket.created';
    public const EVENT_TICKET_RESOLVED = 'ticket.resolved';
    public const EVENT_TICKET_CLOSED = 'ticket.closed';
    
    // Payment events
    public const EVENT_PAYMENT_CREATED = 'payment.created';
    public const EVENT_PAYMENT_PAID = 'payment.paid';
    public const EVENT_PAYMENT_EXPIRED = 'payment.expired';
    
    // Checklist events
    public const EVENT_CHECKLIST_SUBMITTED = 'checklist.submitted';
    public const EVENT_CHECKLIST_APPROVED = 'checklist.approved';
    
    // Sports events
    public const EVENT_SPORTS_BOOKED = 'sports.booked';
    public const EVENT_SPORTS_CANCELLED = 'sports.cancelled';
    public const EVENT_SPORTS_NO_SHOW = 'sports.no_show';
    
    // Laundry events
    public const EVENT_LAUNDRY_REQUESTED = 'laundry.requested';
    public const EVENT_LAUNDRY_READY = 'laundry.ready';
    public const EVENT_LAUNDRY_COMPLETED = 'laundry.completed';
}

