<?php

namespace App\Models;

use App\Models\Traits\TenantScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckoutHistory extends Model
{
    use HasFactory;
    use TenantScoped;

    protected $fillable = [
        'tenant_id',
        'room_allocation_id',
        'event',
        'payload',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function roomAllocation(): BelongsTo
    {
        return $this->belongsTo(RoomAllocation::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
