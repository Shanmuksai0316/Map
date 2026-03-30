<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Legacy Tenant Model (Single-Database Multi-Tenancy)
 * 
 * This model is preserved for backward compatibility during migration.
 * Use the new Tenant model for database-per-tenant architecture.
 */
class TenantLegacy extends Model
{
    use HasFactory;

    protected $table = 'tenants_legacy'; // Renamed table for migration period

    protected $fillable = [
        'code',
        'name',
        'addon_security',
        'addon_sports',
        'addon_laundry',
        'settings',
    ];

    protected $casts = [
        'addon_security' => 'boolean',
        'addon_sports' => 'boolean',
        'addon_laundry' => 'boolean',
        'settings' => 'array',
    ];

    public function campuses(): HasMany
    {
        return $this->hasMany(Campus::class);
    }

    public function hostels(): HasMany
    {
        return $this->hasMany(Hostel::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
