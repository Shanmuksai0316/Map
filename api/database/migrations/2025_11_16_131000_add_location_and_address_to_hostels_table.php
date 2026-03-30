<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hostels')) {
            return;
        }

        Schema::table('hostels', function (Blueprint $table): void {
            if (! Schema::hasColumn('hostels', 'location')) {
                $table->string('location')->nullable()->after('gender_mode');
            }

            if (! Schema::hasColumn('hostels', 'address')) {
                $table->jsonb('address')->nullable()->after('location');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('hostels')) {
            return;
        }

        Schema::table('hostels', function (Blueprint $table): void {
            if (Schema::hasColumn('hostels', 'address')) {
                $table->dropColumn('address');
            }

            if (Schema::hasColumn('hostels', 'location')) {
                $table->dropColumn('location');
            }
        });
    }
};

