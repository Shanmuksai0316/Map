<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('checklist_instances')) {
            return;
        }

        if (!Schema::hasColumn('checklist_instances', 'due_at')) {
            Schema::table('checklist_instances', function (Blueprint $table) {
                $table->timestamp('due_at')->nullable()->after('reviewed_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('checklist_instances')) {
            return;
        }

        if (Schema::hasColumn('checklist_instances', 'due_at')) {
            Schema::table('checklist_instances', function (Blueprint $table) {
                $table->dropColumn('due_at');
            });
        }
    }
};

