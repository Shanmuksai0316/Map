<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds time verification fields for guard check-in/check-out functionality
     */
    public function up(): void
    {
        // Add time verification fields to out_passes
        Schema::table('out_passes', function (Blueprint $table) {
            if (!Schema::hasColumn('out_passes', 'actual_out_time')) {
                $table->timestamp('actual_out_time')->nullable()->after('valid_until');
            }
            if (!Schema::hasColumn('out_passes', 'actual_in_time')) {
                $table->timestamp('actual_in_time')->nullable()->after('actual_out_time');
            }
            if (!Schema::hasColumn('out_passes', 'verified_out_by')) {
                $table->foreignId('verified_out_by')->nullable()->after('actual_in_time')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('out_passes', 'verified_in_by')) {
                $table->foreignId('verified_in_by')->nullable()->after('verified_out_by')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Add time verification fields to leaves
        Schema::table('leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('leaves', 'actual_departure_time')) {
                $table->timestamp('actual_departure_time')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('leaves', 'actual_return_time')) {
                $table->timestamp('actual_return_time')->nullable()->after('actual_departure_time');
            }
            if (!Schema::hasColumn('leaves', 'verified_out_by')) {
                $table->foreignId('verified_out_by')->nullable()->after('actual_return_time')
                    ->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('leaves', 'verified_in_by')) {
                $table->foreignId('verified_in_by')->nullable()->after('verified_out_by')
                    ->constrained('users')->nullOnDelete();
            }
        });

        // Add leave_id to gate_entries for tracking
        Schema::table('gate_entries', function (Blueprint $table) {
            if (!Schema::hasColumn('gate_entries', 'leave_id')) {
                $table->foreignId('leave_id')->nullable()->after('outpass_id')
                    ->constrained('leaves')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            $table->dropColumn([
                'actual_out_time',
                'actual_in_time',
                'verified_out_by',
                'verified_in_by',
            ]);
        });

        Schema::table('leaves', function (Blueprint $table) {
            $table->dropColumn([
                'actual_departure_time',
                'actual_return_time',
                'verified_out_by',
                'verified_in_by',
            ]);
        });

        Schema::table('gate_entries', function (Blueprint $table) {
            $table->dropForeign(['leave_id']);
            $table->dropColumn('leave_id');
        });
    }
};

