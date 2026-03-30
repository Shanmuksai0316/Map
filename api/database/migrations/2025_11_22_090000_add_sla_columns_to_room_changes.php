<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('room_changes', function (Blueprint $table): void {
            if (! Schema::hasColumn('room_changes', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable()->after('submitted_at');
            }

            if (! Schema::hasColumn('room_changes', 'last_reminded_at')) {
                $table->timestamp('last_reminded_at')->nullable()->after('sla_due_at');
            }

            if (! Schema::hasColumn('room_changes', 'last_escalated_at')) {
                $table->timestamp('last_escalated_at')->nullable()->after('last_reminded_at');
            }

            $table->index(['status', 'sla_due_at'], 'room_changes_status_sla_due_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('room_changes', function (Blueprint $table): void {
            if (Schema::hasColumn('room_changes', 'status') && Schema::hasColumn('room_changes', 'sla_due_at')) {
                $table->dropIndex('room_changes_status_sla_due_at_index');
            }

            if (Schema::hasColumn('room_changes', 'sla_due_at')) {
                $table->dropColumn('sla_due_at');
            }

            if (Schema::hasColumn('room_changes', 'last_reminded_at')) {
                $table->dropColumn('last_reminded_at');
            }

            if (Schema::hasColumn('room_changes', 'last_escalated_at')) {
                $table->dropColumn('last_escalated_at');
            }
        });
    }
};

