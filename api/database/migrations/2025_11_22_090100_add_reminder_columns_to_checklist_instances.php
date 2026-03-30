<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_instances', function (Blueprint $table): void {
            if (! Schema::hasColumn('checklist_instances', 'morning_reminded_at')) {
                $table->timestamp('morning_reminded_at')->nullable()->after('reviewed_at');
            }

            if (! Schema::hasColumn('checklist_instances', 'afternoon_reminded_at')) {
                $table->timestamp('afternoon_reminded_at')->nullable()->after('morning_reminded_at');
            }

            if (! Schema::hasColumn('checklist_instances', 'overdue_notified_at')) {
                $table->timestamp('overdue_notified_at')->nullable()->after('afternoon_reminded_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('checklist_instances', function (Blueprint $table): void {
            if (Schema::hasColumn('checklist_instances', 'morning_reminded_at')) {
                $table->dropColumn('morning_reminded_at');
            }

            if (Schema::hasColumn('checklist_instances', 'afternoon_reminded_at')) {
                $table->dropColumn('afternoon_reminded_at');
            }

            if (Schema::hasColumn('checklist_instances', 'overdue_notified_at')) {
                $table->dropColumn('overdue_notified_at');
            }
        });
    }
};

