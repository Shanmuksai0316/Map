<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImportJob extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     * 
     * ImportJobs are ALWAYS in the central database (has tenant_id foreign key).
     * Use default connection from .env (pgsql in production, sqlite in tests)
     */
    protected $connection = null; // Will use DB_CONNECTION from .env

    protected $fillable = [
        'tenant_id', // In central DB - references tenants table
        'kind',
        'status',
        'filename',
        'total_rows',
        'error_rows',
        'processed_rows',
        'inserted_rows',
        'updated_rows',
        'meta',
        'committed_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'committed_at' => 'datetime',
    ];

    /**
     * Get the tenant this model belongs to.
     * With database-per-tenant, tenant context is automatic.
     */
    public function tenant(): ?\App\Models\Tenant
    {
        return tenancy()->tenant;
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class);
    }
}
