<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('idempotency_keys', 'action')) {
                $table->string('action')->nullable()->after('key');
            }
            if (!Schema::hasColumn('idempotency_keys', 'request_fingerprint')) {
                $table->json('request_fingerprint')->nullable()->after('tenant_id');
            }
            if (!Schema::hasColumn('idempotency_keys', 'response_snapshot')) {
                $table->json('response_snapshot')->nullable()->after('request_fingerprint');
            }
            if (!Schema::hasColumn('idempotency_keys', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('response_snapshot');
            }
            if (!Schema::hasColumn('idempotency_keys', 'created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            // Keep columns; no destructive rollback to avoid data loss in environments.
        });
    }
};

