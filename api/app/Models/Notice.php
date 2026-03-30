<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notice extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'body',
        'content', // Legacy support
        'target_role',
        'tenant_id',
        'target_tenant_id', // Legacy support
        'hostel_id',
        'target_hostel_id', // Legacy support
        'campus_id',
        'created_by_user_id',
        'created_by', // Legacy support
        'status',
        'audience',
        'channels',
        'publish_at',
        'published_at',
        'expires_at',
        'attachment_url',
        'images',
    ];

    protected $casts = [
        'channels' => 'array',
        'publish_at' => 'datetime',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
        'images' => 'array',
    ];

    /**
     * Get the campus this notice is for.
     */
    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    /**
     * Get the hostel this notice is for (hostel_id).
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * Get the tenant this notice targets.
     */
    public function targetTenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'target_tenant_id');
    }

    /**
     * Get the hostel this notice targets.
     */
    public function targetHostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class, 'target_hostel_id');
    }

    public function attachments(): BelongsToMany
    {
        return $this->belongsToMany(Attachment::class, 'notice_attachments')->withTimestamps();
    }

    /**
     * Get the user who created this notice.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope to get active notices.
     */
    public function scopeActive($query)
    {
        $now = now();
        return $query
            ->where(function ($q) use ($now) {
                $q->whereNull('publish_at')->orWhere('publish_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }

    /**
     * Scope to filter by target role.
     */
    public function scopeForRole($query, string $role)
    {
        return $query->where(function ($q) use ($role) {
            $q->whereNull('target_role')
              ->orWhere('target_role', 'all')
              ->orWhere('target_role', $role);
        });
    }

    /**
     * Scope to filter by target tenant.
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where(function ($q) use ($tenantId) {
            $q->whereNull('target_tenant_id')
              ->orWhere('target_tenant_id', $tenantId);
        });
    }

    /**
     * Scope to filter by target hostel.
     */
    public function scopeForHostel($query, int $hostelId)
    {
        return $query->where(function ($q) use ($hostelId) {
            $q->whereNull('target_hostel_id')
              ->orWhere('target_hostel_id', $hostelId);
        });
    }

    /**
     * Check if the notice is currently active.
     */
    public function isActive(): bool
    {
        $now = now();
        $published = $this->publish_at ? $this->publish_at->lte($now) : true;
        $notExpired = $this->expires_at ? $this->expires_at->gte($now) : true;
        return $published && $notExpired;
    }

    public function publish(): void
    {
        $publishAt = $this->publish_at ?? now();

        $this->forceFill([
            'status' => 'published',
            'publish_at' => $publishAt,
            'published_at' => now(),
        ])->save();
    }

    public function scheduleFor(CarbonInterface $publishAt): void
    {
        $this->forceFill([
            'status' => 'scheduled',
            'publish_at' => $publishAt,
        ])->save();
    }

    public function shouldSendPush(): bool
    {
        $channels = $this->channels;

        if (is_string($channels) && $channels !== '') {
            $decoded = json_decode($channels, true);
            if (is_array($decoded)) {
                $channels = $decoded;
            }
        }

        if (!is_array($channels) || $channels === []) {
            // Backward-compatible default: published notices send push unless explicitly disabled.
            return true;
        }

        return in_array('push', $channels, true) || in_array('all', $channels, true);
    }
}
