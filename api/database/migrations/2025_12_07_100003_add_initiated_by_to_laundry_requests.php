<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds initiated_by_user_id and total_clothes fields to laundry_requests table
     * to support Laundry Manager initiated requests.
     */
    public function up(): void
    {
        Schema::table('laundry_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('laundry_requests', 'initiated_by_user_id')) {
                $table->unsignedBigInteger('initiated_by_user_id')->nullable()->after('student_id');
            }
            if (!Schema::hasColumn('laundry_requests', 'total_clothes')) {
                $table->integer('total_clothes')->nullable()->after('bag_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laundry_requests', function (Blueprint $table) {
            $table->dropColumn(['initiated_by_user_id', 'total_clothes']);
        });
    }
};

