<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            if (!Schema::hasColumn('out_passes', 'expected_return')) {
                $table->timestamp('expected_return')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            if (Schema::hasColumn('out_passes', 'expected_return')) {
                $table->dropColumn('expected_return');
            }
        });
    }
};

