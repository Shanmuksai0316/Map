<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            // Canonical column
            $table->string('key')->unique();
            // Legacy/aux columns retained for compatibility
            $table->string('key_hash', 64)->nullable()->unique()->comment('SHA256 hash of Idempotency-Key header');
            $table->string('request_fingerprint', 255)->nullable()->comment('Hash of request method + path + body');
            $table->json('response_json')->nullable()->comment('Cached response payload');
            $table->integer('status_code')->nullable()->comment('HTTP status code of cached response');
            $table->timestamp('first_seen_at')->useCurrent();
            $table->timestamp('expires_at')->nullable()->comment('TTL: 24 hours from first_seen_at');
            
            $table->index('expires_at');
            $table->index(['key', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
