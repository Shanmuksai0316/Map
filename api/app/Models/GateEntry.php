<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GateEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'guard_id',
        'event',
        'occurred_at',
        'source',
        'notes',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the tenant this model belongs to.
     * With database-per-tenant, tenant context is automatic.
     */
    public function tenant(): ?\App\Models\Tenant
    {
        return tenancy()->tenant;
    }


    public function guardUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guard_id');
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }
}
