<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('idempotency_keys')) {
            return;
        }

        // Add legacy 'key' column if missing to keep backward compatibility with tests/older code
        if (!Schema::hasColumn('idempotency_keys', 'key')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->string('key')->nullable();
                $table->index('key');
            });

            // Backfill from idem_key if present, else from key_hash
            if (Schema::hasColumn('idempotency_keys', 'idem_key')) {
                DB::table('idempotency_keys')
                    ->whereNull('key')
                    ->update(['key' => DB::raw('idem_key')]);
            } elseif (Schema::hasColumn('idempotency_keys', 'key_hash')) {
                DB::table('idempotency_keys')
                    ->whereNull('key')
                    ->update(['key' => DB::raw('key_hash')]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('idempotency_keys') && Schema::hasColumn('idempotency_keys', 'key')) {
            Schema::table('idempotency_keys', function (Blueprint $table) {
                $table->dropIndex(['key']);
                $table->dropColumn('key');
            });
        }
    }
};

