<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            // Encrypted plain backup code for student app display.
            // Guard verification continues to use the hashed `backup_code`.
            if (!Schema::hasColumn('out_passes', 'backup_code_plain')) {
                $table->text('backup_code_plain')->nullable()->after('backup_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            $table->dropColumn(['backup_code_plain']);
        });
    }
};
