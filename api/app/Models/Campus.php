<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Campus Model (Single Shared Database)
 * 
 * Stored in central database with tenant_id scoping.
 * Tenant isolation is enforced via TenantScope global scope and PostgreSQL RLS.
 */
class Campus extends Model
{
    use HasFactory;
    use Traits\TenantScoped;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'address',
    ];

    protected $casts = [
        'address' => 'array',
    ];

    /**
     * Get the tenant this campus belongs to.
     */
    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
    }

    public function hostels(): HasMany
    {
        return $this->hasMany(Hostel::class);
    }
}
