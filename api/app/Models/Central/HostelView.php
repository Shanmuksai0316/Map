<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * HostelView Model
 * 
 * Read-only model for Super Admin to view all hostels across all tenants.
 * Queries the CENTRAL database where legacy tenant data is stored.
 */
class HostelView extends Model
{
    protected $connection = null; // Use default connection (pgsql in production, sqlite in local)
    protected $table = 'hostels';
    
    public $timestamps = true;
    
    protected $fillable = [
        'tenant_id',
        'campus_id',
        'code',
        'name',
        'gender_mode',
        'curfew_time',
        'overnight_enabled',
    ];
    
    protected $casts = [
        'curfew_time' => 'datetime',
        'overnight_enabled' => 'boolean',
    ];
    
    /**
     * Relationship to tenant (central DB - stancl/tenancy)
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class, 'tenant_id');
    }
    
    /**
     * Relationship to campus in central DB
     */
    public function campus()
    {
        return $this->belongsTo(CampusView::class, 'campus_id');
    }
    
    /**
     * Relationship to students in central DB
     */
    public function students()
    {
        return $this->hasMany(StudentView::class, 'hostel_id');
    }
}

