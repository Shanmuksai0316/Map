<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add delayed_notified_at for 72h SLA: when a request first becomes delayed,
     * we notify campus managers once and set this timestamp to avoid repeat notifications.
     */
    public function up(): void
    {
        if (Schema::hasTable('tickets')) {
            Schema::table('tickets', function (Blueprint $table): void {
                if (! Schema::hasColumn('tickets', 'delayed_notified_at')) {
                    $table->timestamp('delayed_notified_at')->nullable()->after('sla_deadline');
                }
            });
        }

        if (Schema::hasTable('laundry_requests')) {
            Schema::table('laundry_requests', function (Blueprint $table): void {
                if (! Schema::hasColumn('laundry_requests', 'delayed_notified_at')) {
                    $table->timestamp('delayed_notified_at')->nullable()->after('actual_completion_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tickets') && Schema::hasColumn('tickets', 'delayed_notified_at')) {
            Schema::table('tickets', fn (Blueprint $table) => $table->dropColumn('delayed_notified_at'));
        }
        if (Schema::hasTable('laundry_requests') && Schema::hasColumn('laundry_requests', 'delayed_notified_at')) {
            Schema::table('laundry_requests', fn (Blueprint $table) => $table->dropColumn('delayed_notified_at'));
        }
    }
};
