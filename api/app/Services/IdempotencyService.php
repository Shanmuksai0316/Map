<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class IdempotencyService
{
    private const DEFAULT_TTL_MINUTES = 60 * 24; // 24h

    /**
     * Ensure an idempotency key has not been used for this action.
     *
     * @throws \RuntimeException when a duplicate key is detected.
     */
    public function assertUnique(string $action, ?string $key = null, ?int $userId = null, ?string $tenantId = null, array $fingerprint = []): string
    {
        if (!Schema::hasTable('idempotency_keys')) {
            Schema::create('idempotency_keys', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->string('action');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->uuid('tenant_id')->nullable();
                $table->json('request_fingerprint')->nullable();
                $table->json('response_snapshot')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();
            });
        }

        $key = $key ?: Str::uuid()->toString();
        $now = Carbon::now();
        $tenantId = Str::isUuid((string) $tenantId) ? $tenantId : null;

        $payload = [
            'key' => $key,
            'action' => $action,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'request_fingerprint' => json_encode($fingerprint),
            'expires_at' => $now->copy()->addMinutes(self::DEFAULT_TTL_MINUTES),
            'created_at' => $now,
        ];

        if (Schema::hasColumn('idempotency_keys', 'idem_key')) {
            $payload['idem_key'] = $key;
        }

        $existing = DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('action', $action)
            ->where(function ($query) use ($now) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->first();

        if ($existing) {
            throw new \RuntimeException("Duplicate request detected for action '{$action}'. Please retry with a new Idempotency-Key.");
        }

        DB::table('idempotency_keys')->insert($payload);

        return $key;
    }

    /**
     * Optionally persist a response snapshot for observability/debug.
     */
    public function storeResponse(string $action, string $key, array $response): void
    {
        DB::table('idempotency_keys')
            ->where('key', $key)
            ->where('action', $action)
            ->update([
                'response_snapshot' => json_encode($response),
            ]);
    }
}

