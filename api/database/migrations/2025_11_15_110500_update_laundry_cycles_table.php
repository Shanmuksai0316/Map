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

        if (! Schema::hasTable('laundry_cycles')) {
            return;
        }

        Schema::table('laundry_cycles', function (Blueprint $table): void {
            if (! Schema::hasColumn('laundry_cycles', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            if (Schema::hasColumn('laundry_cycles', 'cycle_name') && ! Schema::hasColumn('laundry_cycles', 'machine_label')) {
                $table->renameColumn('cycle_name', 'machine_label');
            } elseif (! Schema::hasColumn('laundry_cycles', 'machine_label')) {
                $table->string('machine_label')->nullable()->after('hostel_id');
            }
        });

        if (Schema::hasColumn('laundry_cycles', 'cycle_date') && ! Schema::hasColumn('laundry_cycles', 'started_at')) {
            Schema::table('laundry_cycles', function (Blueprint $table): void {
                $table->renameColumn('cycle_date', 'started_at');
            });
        }

        if (Schema::hasColumn('laundry_cycles', 'started_at')) {
            if (! $isSqlite) {
                DB::statement("ALTER TABLE laundry_cycles ALTER COLUMN started_at TYPE TIMESTAMP(0) USING started_at::timestamp");
            }
        } else {
            Schema::table('laundry_cycles', function (Blueprint $table): void {
                $table->timestamp('started_at')->nullable()->after('machine_label');
            });
        }

        Schema::table('laundry_cycles', function (Blueprint $table): void {
            if (Schema::hasColumn('laundry_cycles', 'status')) {
                $table->dropColumn('status');
            }
        });

        Schema::table('laundry_cycles', function (Blueprint $table) use ($isSqlite): void {
            if (! Schema::hasColumn('laundry_cycles', 'status')) {
                $table->string('status')->default('scheduled')->after('machine_label');
            }

            if (! Schema::hasColumn('laundry_cycles', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('started_at');
            }

            if (! Schema::hasColumn('laundry_cycles', 'estimated_completion_at')) {
                $table->timestamp('estimated_completion_at')->nullable()->after('completed_at');
            }

            if (! Schema::hasColumn('laundry_cycles', 'actual_completion_at')) {
                $table->timestamp('actual_completion_at')->nullable()->after('estimated_completion_at');
            }

            if (! Schema::hasColumn('laundry_cycles', 'metadata')) {
                if ($isSqlite) {
                    $table->json('metadata')->nullable()->after('actual_completion_at');
                } else {
                    $table->jsonb('metadata')->nullable()->after('actual_completion_at');
                }
            }

            if (! Schema::hasColumn('laundry_cycles', 'cycle_notes')) {
                $table->text('cycle_notes')->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('laundry_cycles', 'operator_id')) {
                $table->foreignId('operator_id')->nullable()->after('cycle_notes')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('laundry_cycles')) {
            return;
        }

        Schema::table('laundry_cycles', function (Blueprint $table): void {
            if (Schema::hasColumn('laundry_cycles', 'operator_id')) {
                $table->dropConstrainedForeignId('operator_id');
            }

            foreach ([
                'cycle_notes',
                'metadata',
                'actual_completion_at',
                'estimated_completion_at',
                'completed_at',
            ] as $column) {
                if (Schema::hasColumn('laundry_cycles', $column)) {
                    $table->dropColumn($column);
                }
            }

            if (Schema::hasColumn('laundry_cycles', 'status')) {
                $table->dropColumn('status');
            }

            if (Schema::hasColumn('laundry_cycles', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });

        if (Schema::hasColumn('laundry_cycles', 'machine_label')) {
            Schema::table('laundry_cycles', function (Blueprint $table): void {
                $table->renameColumn('machine_label', 'cycle_name');
            });
        }

        if (Schema::hasColumn('laundry_cycles', 'started_at')) {
            if (! $isSqlite) {
                DB::statement("ALTER TABLE laundry_cycles ALTER COLUMN started_at TYPE DATE USING started_at::date");
            }
            Schema::table('laundry_cycles', function (Blueprint $table): void {
                $table->renameColumn('started_at', 'cycle_date');
            });
        }

        Schema::table('laundry_cycles', function (Blueprint $table): void {
            if (! Schema::hasColumn('laundry_cycles', 'status')) {
                $table->enum('status', ['scheduled', 'active', 'completed'])->default('scheduled');
            }
        });
    }
};

