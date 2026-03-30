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
        Schema::table('rooms', function (Blueprint $table) {
            if (!Schema::hasColumn('rooms', 'floor_id')) {
                $table->foreignId('floor_id')->nullable()->after('hostel_id')->constrained('floors')->nullOnDelete();
            }
            if (!Schema::hasColumn('rooms', 'capacity')) {
                $table->integer('capacity')->default(1)->after('room_no');
            }
            if (!Schema::hasColumn('rooms', 'room_type')) {
                $table->string('room_type')->default('single')->after('capacity'); // single, double, triple, quad
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            if (Schema::hasColumn('rooms', 'floor_id')) {
                $table->dropConstrainedForeignId('floor_id');
            }
            if (Schema::hasColumn('rooms', 'capacity')) {
                $table->dropColumn('capacity');
            }
            if (Schema::hasColumn('rooms', 'room_type')) {
                $table->dropColumn('room_type');
            }
        });
    }
};

