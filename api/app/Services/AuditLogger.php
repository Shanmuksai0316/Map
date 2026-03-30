<?php

/**
 * AuditLogger
 * 
 * Module: Audit
 * Purpose: Centralized audit logging service for tracking system activities
 * Key methods: log(), logEvent()
 * Policies: None (internal service)
 * @tenant-scope: Automatically includes tenant_id when available
 * Feature flags: None (core functionality)
 * Side effects: Writes to application logs
 * Owner: MAP Co-Pilot
 */

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AuditLogger
{
    /**
     * Log an audit event for a specific model
     * 
     * @param string $eventType Type of event (e.g., 'gate.out', 'ticket.created')
     * @param Model $auditable The model being audited
     * @param array $meta Additional metadata to include in the log
     * @return void
     * @tenant-scope: Automatically includes tenant_id from auditable model
     */
    public function log(string $eventType, Model $auditable, array $meta = []): void
    {
        $auditData = [
            'auditable_type' => $auditable->getMorphClass(),
            'auditable_id' => $auditable->getKey(),
            'tenant_id' => $auditable->tenant_id ?? null,
            'user_id' => auth()->id(),
            'meta' => $meta,
        ];
        
        // Log to application logs for debugging
        Log::info($eventType, $auditData);
        
        // Store in database for compliance and forensics
        try {
            \App\Models\AuditLog::create([
                'tenant_id' => $auditable->tenant_id ?? null,
                'user_id' => auth()->id(),
                'action' => $eventType,
                'auditable_type' => $auditable->getMorphClass(),
                'auditable_id' => $auditable->getKey(),
                'meta' => $meta,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the main operation if audit logging fails
            Log::error('Failed to write audit log to database', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Log a general audit event without a specific auditable model
     * 
     * @param string $eventType Type of event (e.g., 'user.login', 'system.error')
     * @param array $meta Additional metadata to include in the log
     * @param Model|null $user Optional user model for user_id context
     * @return void
     */
    public static function logEvent(string $eventType, array $meta = [], ?Model $user = null): void
    {
        $userId = $user?->id ?? auth()->id();
        $tenantId = $user?->tenant_id ?? auth()->user()?->tenant_id;
        
        Log::info($eventType, [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'meta' => $meta,
        ]);
        
        // Store in database
        try {
            \App\Models\AuditLog::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'action' => $eventType,
                'meta' => $meta,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to write audit log to database', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

