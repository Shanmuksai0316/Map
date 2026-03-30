<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds relation (relation to student) and ID proof fields to guest_visits table.
     */
    public function up(): void
    {
        Schema::table('guest_visits', function (Blueprint $table) {
            if (!Schema::hasColumn('guest_visits', 'relation')) {
                $table->string('relation', 100)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('guest_visits', 'id_proof_type')) {
                $table->string('id_proof_type', 50)->nullable()->after('relation');
            }
            if (!Schema::hasColumn('guest_visits', 'id_proof_number')) {
                $table->string('id_proof_number', 100)->nullable()->after('id_proof_type');
            }
            if (!Schema::hasColumn('guest_visits', 'description')) {
                $table->text('description')->nullable()->after('exit_time');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('guest_visits', function (Blueprint $table) {
            $table->dropColumn(['relation', 'id_proof_type', 'id_proof_number', 'description']);
        });
    }
};

