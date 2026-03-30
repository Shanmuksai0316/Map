<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Add new columns
            if (!Schema::hasColumn('students', 'erp_number')) {
                $table->string('erp_number')->unique()->nullable()->after('map_id');
            }
            if (!Schema::hasColumn('students', 'father_name')) {
                $table->string('father_name')->nullable()->after('email_address');
            }
            if (!Schema::hasColumn('students', 'father_mobile_number')) {
                $table->string('father_mobile_number')->nullable()->after('father_name');
            }
            if (!Schema::hasColumn('students', 'mother_name')) {
                $table->string('mother_name')->nullable()->after('father_mobile_number');
            }
            if (!Schema::hasColumn('students', 'mother_mobile_number')) {
                $table->string('mother_mobile_number')->nullable()->after('mother_name');
            }
            if (!Schema::hasColumn('students', 'medical_information')) {
                $table->text('medical_information')->nullable()->after('blood_group');
            }

            // Drop old columns if they exist
            if (Schema::hasColumn('students', 'roll_number')) {
                $table->dropColumn('roll_number');
            }
            if (Schema::hasColumn('students', 'assigned_room')) {
                $table->dropColumn('assigned_room');
            }
            if (Schema::hasColumn('students', 'occupancy_status')) {
                $table->dropColumn('occupancy_status');
            }
            if (Schema::hasColumn('students', 'medical_conditions')) {
                $table->dropColumn('medical_conditions');
            }
            if (Schema::hasColumn('students', 'allergies')) {
                $table->dropColumn('allergies');
            }
            if (Schema::hasColumn('students', 'disabilities')) {
                $table->dropColumn('disabilities');
            }
            if (Schema::hasColumn('students', 'regular_medications')) {
                $table->dropColumn('regular_medications');
            }
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Restore old columns
            $table->string('roll_number')->nullable();
            $table->string('assigned_room')->nullable();
            $table->enum('occupancy_status', ['vacant', 'occupied', 'reserved'])->default('vacant');
            $table->text('medical_conditions')->nullable();
            $table->text('allergies')->nullable();
            $table->text('disabilities')->nullable();
            $table->text('regular_medications')->nullable();

            // Drop new columns
            $table->dropColumn([
                'erp_number',
                'father_name',
                'father_mobile_number',
                'mother_name',
                'mother_mobile_number',
                'medical_information'
            ]);
        });
    }
};

