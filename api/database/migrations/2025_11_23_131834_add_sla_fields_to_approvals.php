<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add SLA tracking fields to out_passes (2h SLA)
        Schema::table('out_passes', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('requested_at');
            $table->timestamp('sla_breached_at')->nullable()->after('sla_due_at');
            $table->timestamp('sla_warning_sent_at')->nullable()->after('sla_breached_at');
        });

        // Add SLA tracking fields to leaves (4h SLA)
        Schema::table('leaves', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('submitted_at');
            $table->timestamp('sla_breached_at')->nullable()->after('sla_due_at');
            $table->timestamp('sla_warning_sent_at')->nullable()->after('sla_breached_at');
        });

        // Add SLA tracking fields to sick_leaves (4h SLA)
        Schema::table('sick_leaves', function (Blueprint $table) {
            $table->timestamp('sla_due_at')->nullable()->after('submitted_at');
            $table->timestamp('sla_breached_at')->nullable()->after('sla_due_at');
            $table->timestamp('sla_warning_sent_at')->nullable()->after('sla_breached_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove SLA tracking fields from out_passes
        Schema::table('out_passes', function (Blueprint $table) {
            $table->dropColumn(['sla_due_at', 'sla_breached_at', 'sla_warning_sent_at']);
        });

        // Remove SLA tracking fields from leaves
        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn(['sla_due_at', 'sla_breached_at', 'sla_warning_sent_at']);
        });

        // Remove SLA tracking fields from sick_leaves
        Schema::table('sick_leaves', function (Blueprint $table) {
            $table->dropColumn(['sla_due_at', 'sla_breached_at', 'sla_warning_sent_at']);
        });
    }
};
