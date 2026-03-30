<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_hostel_creation_after_activation()
            RETURNS TRIGGER AS $$
            BEGIN
                IF (SELECT status FROM tenants WHERE id = NEW.tenant_id) = 'active' THEN
                    RAISE EXCEPTION 'Cannot add hostel to active tenant (structural changes locked)';
                END IF;
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ");

        DB::statement("DROP TRIGGER IF EXISTS trg_prevent_hostel_creation_after_activation ON hostels;");
        DB::statement("
            CREATE TRIGGER trg_prevent_hostel_creation_after_activation
            BEFORE INSERT ON hostels
            FOR EACH ROW EXECUTE FUNCTION prevent_hostel_creation_after_activation();
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_hostel_creation_after_activation ON hostels;');
        DB::statement('DROP FUNCTION IF EXISTS prevent_hostel_creation_after_activation;');
    }
};

