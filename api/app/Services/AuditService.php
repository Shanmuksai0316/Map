<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuditService
{
    public function log(string $action, ?Model $entity = null, array $metadata = []): void
    {
        try {
            $request = request();
            $user = auth()->user();

            AuditLog::create([
                'user_id' => $user?->id,
                'tenant_id' => $user?->tenant_id ?? $this->resolveTenantId($entity),
                'action' => $action,
                'entity_type' => $entity ? $entity->getMorphClass() : null,
                'entity_id' => $entity?->getKey(),
                'metadata' => $this->sanitizeMetadata($metadata),
                'ip_address' => $request instanceof Request ? $request->ip() : null,
                'user_agent' => $request instanceof Request ? $request->userAgent() : null,
                'occurred_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditService failed to log event', [
                'action' => $action,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveTenantId(?Model $entity): ?string
    {
        if (! $entity) {
            return null;
        }

        return $entity->tenant_id ?? $entity->tenantId ?? null;
    }

    private function sanitizeMetadata(array $metadata): array
    {
        $redactKeys = ['phone', 'email', 'mobile', 'contact', 'address', 'otp', 'token'];

        foreach ($metadata as $key => $value) {
            if (in_array($key, $redactKeys, true)) {
                $metadata[$key] = '[REDACTED]';
            }
        }

        return $metadata;
    }
}

