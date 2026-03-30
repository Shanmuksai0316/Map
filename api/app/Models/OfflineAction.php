<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineAction extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'offline_actions';

    /**
     * The database connection that should be used by the model.
     * Use default connection from .env (pgsql in production)
     */
    protected $connection = null;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'action_type',
        'payload',
        'client_timestamp',
        'processed_at',
        'status',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'payload' => 'array',
        'client_timestamp' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the user who performed the offline action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the tenant this action belongs to
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to filter by status
     */
    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to filter by action type
     */
    public function scopeActionType($query, string $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    /**
     * Scope to get recent actions
     */
    public function scopeRecent($query, int $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }
}
