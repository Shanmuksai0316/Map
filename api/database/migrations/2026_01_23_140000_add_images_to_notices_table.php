<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notices')) {
            return;
        }

        Schema::table('notices', function (Blueprint $table): void {
            if (!Schema::hasColumn('notices', 'images')) {
                $table->json('images')->nullable()->after('attachment_url');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notices')) {
            return;
        }

        Schema::table('notices', function (Blueprint $table): void {
            if (Schema::hasColumn('notices', 'images')) {
                $table->dropColumn('images');
            }
        });
    }
};
