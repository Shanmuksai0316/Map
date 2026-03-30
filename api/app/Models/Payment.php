<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory, TenantScoped;

    protected $fillable = [
        'student_id',
        'reference',
        'amount_paise',
        'currency',
        'status',
        'mode',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount_paise' => 'integer',
    ];

    /**
     * Get the student that owns the payment
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Boot the model and set up event listeners
     */
    protected static function boot()
    {
        parent::boot();

        // Emit events when payment is created or updated
        static::created(function ($payment) {
            ProductEvent::create([
                'tenant_id' => $payment->tenant_id,
                'event_type' => 'payment.recorded',
                'entity_type' => 'payment',
                'entity_id' => $payment->id,
                'properties' => [
                    'amount' => $payment->amount_paise / 100,
                    'mode' => $payment->mode,
                    'reference' => $payment->reference,
                    'status' => $payment->status,
                    'student_id' => $payment->student_id,
                ],
                'occurred_at' => $payment->created_at,
            ]);
        });

        static::updated(function ($payment) {
            // Only emit status change events if status actually changed
            if ($payment->wasChanged('status')) {
                ProductEvent::create([
                    'tenant_id' => $payment->tenant_id,
                    'event_type' => 'payment.status_changed',
                    'entity_type' => 'payment',
                    'entity_id' => $payment->id,
                    'properties' => [
                        'from_status' => $payment->getOriginal('status'),
                        'to_status' => $payment->status,
                        'changed_by_role' => 'campus_manager', // Could be enhanced to track actual user
                        'reference' => $payment->reference,
                        'student_id' => $payment->student_id,
                    ],
                    'occurred_at' => now(),
                ]);
            }
        });
    }

    /**
     * Get amount in rupees (not paise)
     */
    public function getAmountAttribute(): float
    {
        return $this->amount_paise / 100;
    }

    /**
     * Set amount in rupees (converts to paise)
     */
    public function setAmountAttribute(float $value): void
    {
        $this->amount_paise = (int) ($value * 100);
    }
}
