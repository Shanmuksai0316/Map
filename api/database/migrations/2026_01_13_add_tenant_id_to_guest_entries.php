<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasColumn('guest_entries', 'tenant_id')) {
            Schema::table('guest_entries', function (Blueprint $table) {
                $table->foreignId('tenant_id')->after('id')->nullable()->constrained()->cascadeOnDelete();
            });
        }

        Schema::table('guest_entries', function (Blueprint $table) {
            $table->time('check_in_time')->nullable()->change();
            $table->time('check_out_time')->nullable()->change();
        });

        // Add composite index if not exists
        if ($isSqlite) {
            DB::statement('CREATE INDEX IF NOT EXISTS guest_entries_tenant_id_student_id_index ON guest_entries (tenant_id, student_id)');
        } else {
            $indexExists = collect(DB::select("SELECT indexname FROM pg_indexes WHERE tablename = 'guest_entries' AND indexname = 'guest_entries_tenant_id_student_id_index'"))->isNotEmpty();
            if (! $indexExists) {
                Schema::table('guest_entries', function (Blueprint $table) {
                    $table->index(['tenant_id', 'student_id']);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('guest_entries', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn('tenant_id');
            $table->dropIndex(['tenant_id', 'student_id']);
        });
    }
};
