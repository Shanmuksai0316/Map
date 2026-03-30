<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;

class CheckoutChecklist extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'room_allocation_id',
        'status',
        'inspection_passed',
        'keys_collected',
        'dues_cleared',
        'photos',
        'notes',
        'created_by',
        'completed_by',
        'completed_at',
    ];

    protected $casts = [
        'inspection_passed' => 'boolean',
        'keys_collected' => 'boolean',
        'dues_cleared' => 'boolean',
        'photos' => AsArrayObject::class,
        'completed_at' => 'datetime',
    ];

    public function roomAllocation(): BelongsTo
    {
        return $this->belongsTo(RoomAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }
}
