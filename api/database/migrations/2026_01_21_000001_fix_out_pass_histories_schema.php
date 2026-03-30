<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Add missing tenant_id column
        if (!Schema::hasColumn('out_pass_histories', 'tenant_id')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->foreignId('tenant_id')->nullable()->after('id')->index();
            });
        }

        // Rename actor_id to acted_by
        if (Schema::hasColumn('out_pass_histories', 'actor_id') && !Schema::hasColumn('out_pass_histories', 'acted_by')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->renameColumn('actor_id', 'acted_by');
            });
        }

        // Rename action to to_status
        if (Schema::hasColumn('out_pass_histories', 'action') && !Schema::hasColumn('out_pass_histories', 'to_status')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->renameColumn('action', 'to_status');
            });
        }

        // Add from_status
        if (!Schema::hasColumn('out_pass_histories', 'from_status')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->string('from_status')->nullable()->after('to_status');
            });
        }

        // Rename comment to note
        if (Schema::hasColumn('out_pass_histories', 'comment') && !Schema::hasColumn('out_pass_histories', 'note')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->renameColumn('comment', 'note');
            });
        }

        // Rename occurred_at to changed_at
        if (Schema::hasColumn('out_pass_histories', 'occurred_at') && !Schema::hasColumn('out_pass_histories', 'changed_at')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->renameColumn('occurred_at', 'changed_at');
            });
        }

        // Add missing timeline columns
        if (!Schema::hasColumn('out_pass_histories', 'timeline_label')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->string('timeline_label')->nullable()->after('note');
            });
        }

        if (!Schema::hasColumn('out_pass_histories', 'timeline_description')) {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->text('timeline_description')->nullable()->after('timeline_label');
            });
        }

        // Add indexes for performance (wrapped in try-catch to handle pre-existing indexes)
        try {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->index(['tenant_id', 'changed_at']);
            });
        } catch (\Throwable $e) {
            // Index may already exist
        }

        try {
            Schema::table('out_pass_histories', function (Blueprint $table) {
                $table->index(['to_status']);
            });
        } catch (\Throwable $e) {
            // Index may already exist
        }
    }

    public function down(): void
    {
        Schema::table('out_pass_histories', function (Blueprint $table) {
            // Reverse the changes
            $table->dropIndex(['tenant_id', 'changed_at']);
            $table->dropIndex(['to_status']);
            
            $table->dropColumn(['tenant_id', 'from_status', 'timeline_label', 'timeline_description']);
            
            $table->renameColumn('acted_by', 'actor_id');
            $table->renameColumn('to_status', 'action');
            $table->renameColumn('note', 'comment');
            $table->renameColumn('changed_at', 'occurred_at');
        });
    }
};
