<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gate_devices')) {
            return;
        }

        Schema::table('gate_devices', function (Blueprint $table) {
            if (! Schema::hasColumn('gate_devices', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            if (! Schema::hasColumn('gate_devices', 'enrolled_by_user_id')) {
                $table->foreignId('enrolled_by_user_id')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('gate_devices')) {
            return;
        }

        Schema::table('gate_devices', function (Blueprint $table) {
            if (Schema::hasColumn('gate_devices', 'enrolled_by_user_id')) {
                $table->dropForeign(['enrolled_by_user_id']);
                $table->dropColumn('enrolled_by_user_id');
            }
        });

        Schema::table('gate_devices', function (Blueprint $table) {
            if (Schema::hasColumn('gate_devices', 'tenant_id')) {
                $table->dropIndex(['tenant_id']);
                $table->dropColumn('tenant_id');
            }
        });
    }
};

