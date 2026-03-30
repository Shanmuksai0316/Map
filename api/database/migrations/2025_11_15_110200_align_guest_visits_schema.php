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

        if (! Schema::hasTable('guest_visits')) {
            return;
        }

        if (Schema::hasColumn('guest_visits', 'visitor_name') && ! Schema::hasColumn('guest_visits', 'name')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN visitor_name TO name');
        }

        if (Schema::hasColumn('guest_visits', 'visitor_phone') && ! Schema::hasColumn('guest_visits', 'phone')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN visitor_phone TO phone');
        }

        if (Schema::hasColumn('guest_visits', 'visiting_date') && ! Schema::hasColumn('guest_visits', 'visit_date')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN visiting_date TO visit_date');
        }

        if (Schema::hasColumn('guest_visits', 'purpose') && ! Schema::hasColumn('guest_visits', 'whom_to_meet')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN purpose TO whom_to_meet');
        }

        if (Schema::hasColumn('guest_visits', 'approved_by') && ! Schema::hasColumn('guest_visits', 'allowed_by_user_id')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN approved_by TO allowed_by_user_id');
        }

        Schema::table('guest_visits', function (Blueprint $table) {
            if (! Schema::hasColumn('guest_visits', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            if (! Schema::hasColumn('guest_visits', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('guest_visits', 'allowed_by_user_id')) {
                $table->foreignId('allowed_by_user_id')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('guest_visits', 'allowed_at')) {
                $table->timestamp('allowed_at')->nullable()->after('allowed_by_user_id');
            }

            if (! Schema::hasColumn('guest_visits', 'denied_by_user_id')) {
                $table->foreignId('denied_by_user_id')->nullable()->after('allowed_at')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('guest_visits', 'denied_at')) {
                $table->timestamp('denied_at')->nullable()->after('denied_by_user_id');
            }

            if (! Schema::hasColumn('guest_visits', 'entry_time')) {
                $table->time('entry_time')->nullable()->after('visit_date');
            }

            if (! Schema::hasColumn('guest_visits', 'exit_time')) {
                $table->time('exit_time')->nullable()->after('entry_time');
            }

            if (! Schema::hasColumn('guest_visits', 'status')) {
                $table->string('status', 32)->default('pre_registered')->after('whom_to_meet');
            }
        });

        if (! $isSqlite && Schema::hasColumn('guest_visits', 'status')) {
            DB::statement('ALTER TABLE guest_visits DROP CONSTRAINT IF EXISTS guest_visits_status_check');
            DB::statement("ALTER TABLE guest_visits ALTER COLUMN status TYPE VARCHAR(32)");
            DB::statement("ALTER TABLE guest_visits ALTER COLUMN status SET DEFAULT 'pre_registered'");
            DB::statement("ALTER TABLE guest_visits ADD CONSTRAINT guest_visits_status_check CHECK (status IN ('pre_registered','pending','allowed','approved','denied','cancelled','completed'))");
        }

        Schema::table('guest_visits', function (Blueprint $table) {
            if (Schema::hasColumn('guest_visits', 'visit_date')) {
                $table->index(['tenant_id', 'hostel_id', 'visit_date']);
            }
        });
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('guest_visits')) {
            return;
        }

        Schema::table('guest_visits', function (Blueprint $table) {
            if (Schema::hasColumn('guest_visits', 'created_by_user_id')) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            }

            if (Schema::hasColumn('guest_visits', 'allowed_by_user_id')) {
                $table->dropForeign(['allowed_by_user_id']);
                $table->dropColumn('allowed_by_user_id');
            }

            if (Schema::hasColumn('guest_visits', 'allowed_at')) {
                $table->dropColumn('allowed_at');
            }

            if (Schema::hasColumn('guest_visits', 'denied_by_user_id')) {
                $table->dropForeign(['denied_by_user_id']);
                $table->dropColumn('denied_by_user_id');
            }

            if (Schema::hasColumn('guest_visits', 'denied_at')) {
                $table->dropColumn('denied_at');
            }

        });

        DB::statement('DROP INDEX IF EXISTS guest_visits_tenant_id_hostel_id_visit_date_index');
        DB::statement('DROP INDEX IF EXISTS guest_visits_tenant_id_index');

        if (Schema::hasColumn('guest_visits', 'name')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN name TO visitor_name');
        }

        if (Schema::hasColumn('guest_visits', 'phone')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN phone TO visitor_phone');
        }

        if (Schema::hasColumn('guest_visits', 'visit_date')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN visit_date TO visiting_date');
        }

        if (Schema::hasColumn('guest_visits', 'whom_to_meet')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN whom_to_meet TO purpose');
        }

        if (! $isSqlite && Schema::hasColumn('guest_visits', 'status')) {
            DB::statement('ALTER TABLE guest_visits DROP CONSTRAINT IF EXISTS guest_visits_status_check');
            DB::statement("ALTER TABLE guest_visits ALTER COLUMN status SET DEFAULT 'pre_registered'");
            DB::statement("ALTER TABLE guest_visits ADD CONSTRAINT guest_visits_status_check CHECK (status IN ('pre_registered','allowed','denied','completed'))");
        }

        if (Schema::hasColumn('guest_visits', 'allowed_by_user_id')) {
            DB::statement('ALTER TABLE guest_visits RENAME COLUMN allowed_by_user_id TO approved_by');
        }
    }
};

