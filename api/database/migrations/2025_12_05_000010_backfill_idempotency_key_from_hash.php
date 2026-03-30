<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('idempotency_keys')) {
            return;
        }

        if (Schema::hasColumn('idempotency_keys', 'key')) {
            $source = null;
            if (Schema::hasColumn('idempotency_keys', 'key_hash')) {
                $source = 'key_hash';
            } elseif (Schema::hasColumn('idempotency_keys', 'idem_key')) {
                $source = 'idem_key';
            }

            if ($source) {
                DB::table('idempotency_keys')
                    ->whereNull('key')
                    ->update(['key' => DB::raw($source)]);
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};

