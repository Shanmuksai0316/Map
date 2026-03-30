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

        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (!Schema::hasColumn('idempotency_keys', 'key')) {
                $table->string('key')->nullable();
            }
            if (!Schema::hasColumn('idempotency_keys', 'key_hash')) {
                $table->string('key_hash', 64)->nullable();
            }
        });

        // Backfill key from key_hash or idem_key if key is null
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

        // Ensure index on key
        $this->ensureIndex('idempotency_keys', ['key']);
    }

    public function down(): void
    {
        // No-op: keep columns for compatibility
    }

    private function ensureIndex(string $table, array $columns): void
    {
        $indexName = $table . '_' . implode('_', $columns) . '_index';

        if (DB::getDriverName() === 'sqlite') {
            $columnList = implode(', ', array_map(static fn ($c) => '"' . $c . '"', $columns));
            DB::statement("CREATE INDEX IF NOT EXISTS {$indexName} ON {$table} ({$columnList})");
            return;
        }

        $hasIndex = collect(DB::select("SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ?", [
            $table,
            $indexName,
        ]))->isNotEmpty();

        if (!$hasIndex && Schema::hasTable($table)) {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        }
    }
};

