<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            // 4-digit backup code for manual entry (stored hashed)
            if (!Schema::hasColumn('out_passes', 'backup_code')) {
                $table->string('backup_code', 255)->nullable()->after('valid_until');
            }
            // When backup code was used (null = not used yet)
            if (!Schema::hasColumn('out_passes', 'backup_code_used_at')) {
                $table->timestamp('backup_code_used_at')->nullable()->after('backup_code');
            }
            // When QR was scanned (to prevent reuse)
            if (!Schema::hasColumn('out_passes', 'qr_scanned_at')) {
                $table->timestamp('qr_scanned_at')->nullable()->after('backup_code_used_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('out_passes', function (Blueprint $table) {
            $table->dropColumn(['backup_code', 'backup_code_used_at', 'qr_scanned_at']);
        });
    }
};
