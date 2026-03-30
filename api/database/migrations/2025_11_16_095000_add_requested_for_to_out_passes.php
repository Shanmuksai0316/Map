<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('out_passes')) {
            return;
        }

        Schema::table('out_passes', function (Blueprint $table): void {
            if (! Schema::hasColumn('out_passes', 'requested_for')) {
                $table->date('requested_for')->nullable()->after('requested_at');
                $table->index(['tenant_id', 'requested_for'], 'out_passes_tenant_id_requested_for_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('out_passes')) {
            return;
        }

        Schema::table('out_passes', function (Blueprint $table): void {
            if (Schema::hasColumn('out_passes', 'requested_for')) {
                $table->dropIndex('out_passes_tenant_id_requested_for_idx');
                $table->dropColumn('requested_for');
            }
        });
    }
};

