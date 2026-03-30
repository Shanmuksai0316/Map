<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Adds acknowledgment fields for medical emergency tracking
     */
    public function up(): void
    {
        Schema::table('sick_leaves', function (Blueprint $table) {
            if (!Schema::hasColumn('sick_leaves', 'requires_medical_attention')) {
                $table->boolean('requires_medical_attention')->default(false)->after('notes');
            }
            if (!Schema::hasColumn('sick_leaves', 'acknowledged_at')) {
                $table->timestamp('acknowledged_at')->nullable()->after('requires_medical_attention');
            }
            if (!Schema::hasColumn('sick_leaves', 'acknowledged_by')) {
                $table->foreignId('acknowledged_by')->nullable()->after('acknowledged_at')
                    ->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sick_leaves', function (Blueprint $table) {
            $table->dropColumn([
                'requires_medical_attention',
                'acknowledged_at',
                'acknowledged_by',
            ]);
        });
    }
};

