<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('idempotency_keys', 'user_id')) {
                $table->unsignedBigInteger('user_id')->nullable()->after('action');
            }
            if (!Schema::hasColumn('idempotency_keys', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            // keep columns to avoid destructive rollback
        });
    }
};

