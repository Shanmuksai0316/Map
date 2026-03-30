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

        if (! Schema::hasTable('laundry_requests')) {
            return;
        }

        Schema::table('laundry_requests', function (Blueprint $table) use ($isSqlite): void {
            if (! Schema::hasColumn('laundry_requests', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            if (! Schema::hasColumn('laundry_requests', 'laundry_cycle_id')) {
                $table->foreignId('laundry_cycle_id')->nullable()->after('hostel_id')->constrained('laundry_cycles')->nullOnDelete();
            }

            if (! Schema::hasColumn('laundry_requests', 'service_type')) {
                $table->string('service_type')->default('wash_fold')->after('laundry_cycle_id');
            }

            if (! Schema::hasColumn('laundry_requests', 'bag_count')) {
                $table->integer('bag_count')->default(1)->after('service_type');
            }

            if (! Schema::hasColumn('laundry_requests', 'weight_kg')) {
                $table->decimal('weight_kg', 5, 2)->nullable()->after('bag_count');
            }

            if (! Schema::hasColumn('laundry_requests', 'ready_at')) {
                $table->timestamp('ready_at')->nullable()->after('status');
            }

            if (! Schema::hasColumn('laundry_requests', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('ready_at');
            }

            if (! Schema::hasColumn('laundry_requests', 'metadata')) {
                if ($isSqlite) {
                    $table->json('metadata')->nullable()->after('notes');
                } else {
                    $table->jsonb('metadata')->nullable()->after('notes');
                }
            }
        });

        // Rename legacy date columns to timestamp counterparts
        if (Schema::hasColumn('laundry_requests', 'requested_date') && ! Schema::hasColumn('laundry_requests', 'requested_at')) {
            DB::statement('ALTER TABLE laundry_requests RENAME COLUMN requested_date TO requested_at');
        }
        if (Schema::hasColumn('laundry_requests', 'pickup_date') && ! Schema::hasColumn('laundry_requests', 'picked_up_at')) {
            DB::statement('ALTER TABLE laundry_requests RENAME COLUMN pickup_date TO picked_up_at');
        }
        if (Schema::hasColumn('laundry_requests', 'delivery_date') && ! Schema::hasColumn('laundry_requests', 'delivered_at')) {
            DB::statement('ALTER TABLE laundry_requests RENAME COLUMN delivery_date TO delivered_at');
        }

        // Convert renamed columns to timestamps
        foreach (['requested_at', 'picked_up_at', 'delivered_at'] as $column) {
            if (Schema::hasColumn('laundry_requests', $column)) {
                if (! $isSqlite) {
                    DB::statement("ALTER TABLE laundry_requests ALTER COLUMN {$column} TYPE TIMESTAMP USING {$column}::timestamp");
                }
            }
        }

        // Update status enum
        if (! $isSqlite) {
            DB::statement('ALTER TABLE laundry_requests DROP CONSTRAINT IF EXISTS laundry_requests_status_check');
            DB::statement("ALTER TABLE laundry_requests ALTER COLUMN status TYPE VARCHAR(20)");
            DB::statement("ALTER TABLE laundry_requests ADD CONSTRAINT laundry_requests_status_check CHECK (status IN ('pending','scheduled','collected','washing','drying','ready','delivered','completed','cancelled','lost','damaged'))");
        }

        Schema::table('laundry_requests', function (Blueprint $table): void {
            $table->index(['tenant_id', 'hostel_id', 'status']);
            $table->index(['tenant_id', 'student_id', 'requested_at']);
        });
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('laundry_requests')) {
            return;
        }

        Schema::table('laundry_requests', function (Blueprint $table): void {
            foreach (['metadata', 'completed_at', 'ready_at', 'weight_kg', 'bag_count', 'service_type', 'laundry_cycle_id', 'tenant_id'] as $column) {
                if (Schema::hasColumn('laundry_requests', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (! $isSqlite) {
            DB::statement('ALTER TABLE laundry_requests DROP CONSTRAINT IF EXISTS laundry_requests_status_check');
            DB::statement("ALTER TABLE laundry_requests ADD CONSTRAINT laundry_requests_status_check CHECK (status IN ('pending','picked_up','in_process','ready','delivered'))");
        }

        // Rename timestamp columns back to dates if they exist
        if (Schema::hasColumn('laundry_requests', 'requested_at')) {
            if (! $isSqlite) {
                DB::statement("ALTER TABLE laundry_requests ALTER COLUMN requested_at TYPE DATE USING requested_at::date");
            }
            DB::statement('ALTER TABLE laundry_requests RENAME COLUMN requested_at TO requested_date');
        }
        if (Schema::hasColumn('laundry_requests', 'picked_up_at')) {
            if (! $isSqlite) {
                DB::statement("ALTER TABLE laundry_requests ALTER COLUMN picked_up_at TYPE DATE USING picked_up_at::date");
            }
            DB::statement('ALTER TABLE laundry_requests RENAME COLUMN picked_up_at TO pickup_date');
        }
        if (Schema::hasColumn('laundry_requests', 'delivered_at')) {
            if (! $isSqlite) {
                DB::statement("ALTER TABLE laundry_requests ALTER COLUMN delivered_at TYPE DATE USING delivered_at::date");
            }
            DB::statement('ALTER TABLE laundry_requests RENAME COLUMN delivered_at TO delivery_date');
        }
    }
};

