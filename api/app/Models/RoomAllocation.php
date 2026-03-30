<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Traits\TenantScoped;
use App\Models\CheckoutChecklist;
use App\Models\CheckoutHistory;

class RoomAllocation extends Model
{
    /** @use HasFactory<\Database\Factories\RoomAllocationFactory> */
    use HasFactory, TenantScoped;

    protected $fillable = [
        'tenant_id',
'student_id',
        'room_bed_id',
        'hostel_id',
        'effective_from',
        'effective_to',
        'is_active',
        'note',
        'expected_checkout_at',
        'checkout_notified_at',
        'checkout_status',
    ];

    protected $casts = [
        'effective_from' => 'datetime',
        'effective_to' => 'datetime',
        'expected_checkout_at' => 'datetime',
        'checkout_notified_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * Get the tenant this model belongs to.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function roomBed(): BelongsTo
    {
        return $this->belongsTo(RoomBed::class);
    }

    /**
     * Backward-compatible alias used by older page queries.
     */
    public function bed(): BelongsTo
    {
        return $this->roomBed();
    }

    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    public function checkoutChecklist(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(CheckoutChecklist::class);
    }

    public function checkoutHistories(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CheckoutHistory::class);
    }
}
