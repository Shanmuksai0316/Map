<?php

namespace App\Models\Central;

use Illuminate\Database\Eloquent\Model;

/**
 * StudentView Model
 * 
 * Read-only model for Super Admin to view all students across all tenants.
 * 
 * NOTE: In a database-per-tenant architecture, students are stored in each tenant's
 * database, not the central database. This model provides a virtual view across all tenants.
 */
class StudentView extends Model
{
    protected $connection = null; // Use default connection (pgsql in production, sqlite in local)
    protected $table = 'students';
    
    public $timestamps = true;
    public $incrementing = false;
    
    protected $fillable = [
        'id',
        'user_id',
        'hostel_id',
        'map_student_id',
        'student_uid',
        'roll_no',
        'program',
        'year_of_study',
        'admission_year',
        'hostel_fee_paid',
        'payment_mode',
        'payment_amount',
        'payment_date',
        'guardian',
        'correspondence_address',
    ];
    
    protected $casts = [
        'guardian' => 'array',
        'correspondence_address' => 'array',
        'hostel_fee_paid' => 'boolean',
        'payment_date' => 'datetime',
    ];
    
    // Disable writes - this is a read-only view
    public function save(array $options = []) {}
    public function create(array $attributes = []) {}
    public function update(array $attributes = [], array $options = []) {}
    public function delete() {}
    public function forceDelete() {}
    
    /**
     * Tenant relationship removed - students table doesn't have tenant_id column
     * 
     * In database-per-tenant architecture, students are isolated by their
     * tenant database, not by a column reference.
     * 
     * To get tenant info, you would need to join through: student->hostel->campus->tenant
     * which is complex for aggregated views.
     */
    // public function tenant() - REMOVED to avoid tenant_id column errors
    
    /**
     * Get the student's hostel
     */
    public function hostel()
    {
        return $this->belongsTo(HostelView::class, 'hostel_id');
    }
    
    /**
     * Get the student's user
     */
    public function user()
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }
    
    /**
     * Scope to get students from a specific tenant
     * 
     * Note: Disabled because students table doesn't have tenant_id column
     * in the aggregated central database view.
     * Students are isolated by their tenant database, not by a column.
     */
    public function scopeForTenant($query, $tenantId)
    {
        // Cannot filter by tenant_id as column doesn't exist
        // Return query as-is (shows all students from central view)
        return $query;
    }
}

