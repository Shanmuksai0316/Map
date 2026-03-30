<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idempotency_keys')) {
            return;
        }

        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('idem_key')->unique();
            $table->string('action');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tenant_id')->nullable();
            $table->json('request_fingerprint')->nullable();
            $table->json('response_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();

            $table->index(['action', 'key']);
            $table->index(['expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};

