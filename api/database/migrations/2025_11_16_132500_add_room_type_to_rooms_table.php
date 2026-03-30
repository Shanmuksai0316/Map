<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        Schema::table('rooms', function (Blueprint $table): void {
            if (! Schema::hasColumn('rooms', 'room_type')) {
                $table->string('room_type')->nullable()->after('capacity');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('rooms')) {
            return;
        }

        Schema::table('rooms', function (Blueprint $table): void {
            if (Schema::hasColumn('rooms', 'room_type')) {
                $table->dropColumn('room_type');
            }
        });
    }
};

