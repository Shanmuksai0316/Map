<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportError extends Model
{
    use HasFactory;

    /**
     * The database connection that should be used by the model.
     * 
     * ImportErrors are ALWAYS in the central database (linked to import_jobs).
     * Use default connection from .env (pgsql in production, sqlite in tests)
     */
    protected $connection = null; // Will use DB_CONNECTION from .env

    protected $fillable = [
        'import_job_id',
        'row_number',
        'code',
        'message',
        'row_snapshot',
    ];

    protected $casts = [
        'row_snapshot' => 'array',
    ];

    public function job(): BelongsTo
    {
        return $this->belongsTo(ImportJob::class, 'import_job_id');
    }
}
