<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('guest_visits')) {
            return;
        }

        Schema::table('guest_visits', function (Blueprint $table): void {
            if (! Schema::hasColumn('guest_visits', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('guest_visits')) {
            return;
        }

        Schema::table('guest_visits', function (Blueprint $table): void {
            if (Schema::hasColumn('guest_visits', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};

