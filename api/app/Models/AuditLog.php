<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
'user_id',
        'action',
        'auditable_type',
        'auditable_id',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'meta' => 'array',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Common audit actions as constants
     */
    public const ACTION_LOGIN = 'auth.login';
    public const ACTION_LOGOUT = 'auth.logout';
    public const ACTION_OTP_SENT = 'auth.otp_sent';
    public const ACTION_OTP_VERIFIED = 'auth.otp_verified';
    
    public const ACTION_OUTPASS_APPROVE = 'outpass.approve';
    public const ACTION_OUTPASS_DECLINE = 'outpass.decline';
    public const ACTION_OUTPASS_CANCEL = 'outpass.cancel';
    
    public const ACTION_ATTENDANCE_MARK = 'attendance.mark';
    public const ACTION_ATTENDANCE_EDIT_AFTER_CLOSE = 'attendance.edit_after_close';
    
    public const ACTION_PAYMENT_MARK_AS_PAID = 'payment.mark_as_paid';
    public const ACTION_PAYMENT_MARK_AS_PAID_REVOKE = 'payment.mark_as_paid_revoke';
    
    public const ACTION_PII_REVEAL_MEDICAL = 'pii.reveal_medical';
    public const ACTION_PII_REVEAL_GUARDIAN = 'pii.reveal_guardian';
    public const ACTION_PII_REVEAL_CONTACT = 'pii.reveal_contact';
    
    public const ACTION_EXPORT_CREATE = 'export.create';
    public const ACTION_EXPORT_DOWNLOAD = 'export.download';
    
    public const ACTION_INCIDENT_CREATE = 'incident.create';
    public const ACTION_INCIDENT_CLOSE = 'incident.close';
    
    public const ACTION_GATE_EMERGENCY_EXIT = 'gate.emergency_exit';
}

