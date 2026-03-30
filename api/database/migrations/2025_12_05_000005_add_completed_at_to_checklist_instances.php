<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('checklist_instances', 'completed_at')) {
            return;
        }

        Schema::table('checklist_instances', function (Blueprint $table) {
            $table->timestamp('completed_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        if (!Schema::hasColumn('checklist_instances', 'completed_at')) {
            return;
        }

        Schema::table('checklist_instances', function (Blueprint $table) {
            $table->dropColumn('completed_at');
        });
    }
};

