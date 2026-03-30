<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * CampusView Model
 * 
 * Read-only model for Super Admin to view all campuses across all tenants.
 * Queries the CENTRAL database where legacy tenant data is stored.
 */
class CampusView extends Model
{
    protected $connection = null; // Use default connection (pgsql in production, sqlite in local)
    protected $table = 'campuses';
    
    public $timestamps = true;
    
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
     * Relationship to tenant (central DB - stancl/tenancy)
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }
    
    /**
     * Relationship to hostels in central DB
     */
    public function hostels()
    {
        return $this->hasMany(HostelView::class, 'campus_id');
    }
}

