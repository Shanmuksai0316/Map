<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            // Curfew start time (e.g., "20:00" for 8 PM) - after this time, QR is required for entry
            if (!Schema::hasColumn('hostels', 'curfew_start')) {
                $table->time('curfew_start')->nullable()->after('curfew_time');
            }
            // Curfew end time (e.g., "06:00" for 6 AM)
            if (!Schema::hasColumn('hostels', 'curfew_end')) {
                $table->time('curfew_end')->nullable()->after('curfew_start');
            }
            // Whether QR verification is required during curfew hours
            if (!Schema::hasColumn('hostels', 'qr_required_during_curfew')) {
                $table->boolean('qr_required_during_curfew')->default(true)->after('curfew_end');
            }
            // Whether backup codes are enabled for this hostel
            if (!Schema::hasColumn('hostels', 'backup_codes_enabled')) {
                $table->boolean('backup_codes_enabled')->default(true)->after('qr_required_during_curfew');
            }
        });
    }

    public function down(): void
    {
        Schema::table('hostels', function (Blueprint $table) {
            $table->dropColumn([
                'curfew_start',
                'curfew_end',
                'qr_required_during_curfew',
                'backup_codes_enabled',
            ]);
        });
    }
};
