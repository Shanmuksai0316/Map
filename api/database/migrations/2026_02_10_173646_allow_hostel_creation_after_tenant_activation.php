<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Allow hostel creation after tenant activation.
 *
 * The original migration (2025_12_04_000002) installed a PostgreSQL trigger
 * that prevented inserting hostels when the parent tenant was already active.
 * Business requirement changed: Super Admin must be able to add new hostels
 * to an active tenant (college expansion scenario).
 *
 * This migration drops that trigger while keeping the single-campus-per-tenant
 * unique constraint intact (campuses_tenant_id_unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP TRIGGER IF EXISTS trg_prevent_campus_insert_after_activation ON campuses;');
        // Keep the function around so down() can recreate the trigger easily,
        // but the trigger itself is removed — hostels can now be added at any time.

        // Note: We are NOT touching the hostels table trigger (there is none).
        // The original trigger was on the *campuses* table, not hostels.
        // Hostels were already creatable post-activation at the DB level;
        // the restriction was only in the onboarding wizard UI.
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Re-create the trigger to block campus inserts after activation
        DB::statement("
            CREATE OR REPLACE FUNCTION prevent_campus_insert_after_activation()
            RETURNS TRIGGER AS \$\$
            BEGIN
                IF (SELECT status FROM tenants WHERE id = NEW.tenant_id) = 'active' THEN
                    RAISE EXCEPTION 'Cannot add campus to active tenant';
                END IF;
                RETURN NEW;
            END;
            \$\$ LANGUAGE plpgsql;
        ");

        DB::statement("
            CREATE TRIGGER trg_prevent_campus_insert_after_activation
            BEFORE INSERT ON campuses
            FOR EACH ROW EXECUTE FUNCTION prevent_campus_insert_after_activation();
        ");
    }
};
