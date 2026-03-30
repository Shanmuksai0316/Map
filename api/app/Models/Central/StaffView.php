<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * StaffView Model
 * 
 * Read-only model for Super Admin to view all staff users across all tenants.
 * Queries the CENTRAL database where legacy tenant data is stored.
 */
class StaffView extends Model
{
    protected $connection = null; // Use default connection (pgsql in production, sqlite in local)
    protected $table = 'users';
    
    public $timestamps = true;
    
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'phone',
        'kind',
    ];
    
    /**
     * Relationship to tenant (central DB - stancl/tenancy)
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }
    
    /**
     * Scope to only staff users (not students)
     */
    public function scopeStaff($query)
    {
        return $query->where('kind', '!=', 'student');
    }
}

