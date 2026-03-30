<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StaffUser extends User
{
    use HasFactory;

    protected $table = 'users';

    /**
     * Scope to only staff users (non-students)
     */
    public function scopeStaff($query)
    {
        return $query->where('kind', '!=', 'student');
    }

    /**
     * Relationship to tenant (central DB - stancl/tenancy)
     */
    public function tenantRelation(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
