<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Guard: fail early if duplicates exist so we don't apply a broken unique constraint.
        $duplicates = DB::table('campuses')
            ->select('tenant_id', DB::raw('COUNT(*) as campus_count'))
            ->groupBy('tenant_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates->pluck('tenant_id')->implode(', ');
            throw new \RuntimeException("Cannot enforce single campus per tenant. Fix duplicate campuses for tenant_ids: {$list}");
        }

        Schema::table('campuses', function (Blueprint $table) {
            if (! $this->hasUniqueIndex()) {
                $table->unique('tenant_id', 'campuses_tenant_id_unique');
            }
        });

        // Block inserts when tenant is already active (structural lock)
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_campus_insert_after_activation()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (SELECT status FROM tenants WHERE id = NEW.tenant_id) = 'active' THEN
                    RAISE EXCEPTION 'Cannot add campus to active tenant';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("DROP TRIGGER IF EXISTS trg_prevent_campus_insert_after_activation ON campuses;");
        DB::statement("
            CREATE TRIGGER trg_prevent_campus_insert_after_activation
            BEFORE INSERT ON campuses
            FOR EACH ROW EXECUTE FUNCTION prevent_campus_insert_after_activation();
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_campus_insert_after_activation ON campuses;');
        DB::statement('DROP FUNCTION IF EXISTS prevent_campus_insert_after_activation;');

        Schema::table('campuses', function (Blueprint $table) {
            $table->dropUnique('campuses_tenant_id_unique');
        });
    }

    private function hasUniqueIndex(): bool
    {
        $exists = DB::select("
            SELECT 1
            FROM pg_indexes
            WHERE tablename = 'campuses'
            AND indexname = 'campuses_tenant_id_unique'
            LIMIT 1
        ");

        return !empty($exists);
    }
};

