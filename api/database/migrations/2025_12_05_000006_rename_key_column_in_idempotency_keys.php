<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('idempotency_keys') && Schema::hasColumn('idempotency_keys', 'key')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->renameColumn('key', 'idem_key');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('idempotency_keys') && Schema::hasColumn('idempotency_keys', 'idem_key')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->renameColumn('idem_key', 'key');
            });
        }
    }
};

