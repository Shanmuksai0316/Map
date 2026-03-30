<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gate_entries')) {
            return;
        }

        Schema::table('gate_entries', function (Blueprint $table): void {
            if (! Schema::hasColumn('gate_entries', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('gate_entries', 'campus_id')) {
                $table->foreignId('campus_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('gate_entries', 'hostel_id')) {
                $table->foreignId('hostel_id')->nullable()->after('campus_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('gate_entries', 'outpass_id')) {
                $table->foreignId('outpass_id')->nullable()->after('student_id')->constrained('out_passes')->nullOnDelete();
            }

            if (! Schema::hasColumn('gate_entries', 'direction')) {
                $table->string('direction', 8)->default('out')->after('event');
            }

            if (! Schema::hasColumn('gate_entries', 'method')) {
                $table->string('method', 16)->default('manual')->after('direction');
            }

            if (! Schema::hasColumn('gate_entries', 'verified')) {
                $table->boolean('verified')->default(false)->after('method');
            }

            if (! Schema::hasColumn('gate_entries', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verified');
            }

            if (! Schema::hasColumn('gate_entries', 'guard_user_id')) {
                $table->foreignId('guard_user_id')->nullable()->after('guard_id')->constrained('users')->nullOnDelete();
            }

            if (! Schema::hasColumn('gate_entries', 'client_reference')) {
                $table->string('client_reference')->nullable()->after('source');
            }

            if (! Schema::hasColumn('gate_entries', 'was_offline')) {
                $table->boolean('was_offline')->default(false)->after('client_reference');
            }

            if (! Schema::hasColumn('gate_entries', 'synced_at')) {
                $table->timestamp('synced_at')->nullable()->after('was_offline');
            }

            if (! Schema::hasColumn('gate_entries', 'late_minutes')) {
                $table->integer('late_minutes')->nullable()->after('synced_at');
            }

            if (! Schema::hasColumn('gate_entries', 'metadata')) {
                $table->jsonb('metadata')->nullable()->after('notes');
            }
        });

        Schema::table('gate_entries', function (Blueprint $table): void {
            $table->index(['tenant_id', 'hostel_id']);
            $table->index(['tenant_id', 'created_at']);
            $table->index(['tenant_id', 'event']);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gate_entries')) {
            return;
        }

        Schema::table('gate_entries', function (Blueprint $table): void {
            foreach (['tenant_id', 'campus_id', 'hostel_id', 'outpass_id', 'direction', 'method', 'verified', 'verified_at', 'guard_user_id', 'client_reference', 'was_offline', 'synced_at', 'late_minutes', 'metadata'] as $column) {
                if (Schema::hasColumn('gate_entries', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

