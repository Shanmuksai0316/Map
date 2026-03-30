<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            if (!Schema::hasColumn('out_passes', 'returned_at')) {
                $table->timestamp('returned_at')->nullable()->after('expected_return');
            }
        });
    }

    public function down(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            if (Schema::hasColumn('out_passes', 'returned_at')) {
                $table->dropColumn('returned_at');
            }
        });
    }
};

