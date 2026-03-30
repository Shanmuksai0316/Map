<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('ticket_comments')) {
            return;
        }

        Schema::table('ticket_comments', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_comments', 'content') && ! Schema::hasColumn('ticket_comments', 'body')) {
                $table->text('body')->nullable()->after('ticket_id');
            }

            if (! Schema::hasColumn('ticket_comments', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('ticket_id')->constrained()->nullOnDelete();
            }

            if (! Schema::hasColumn('ticket_comments', 'attachments')) {
                $table->json('attachments')->nullable()->after('body');
            }

            if (! Schema::hasColumn('ticket_comments', 'is_internal')) {
                $table->boolean('is_internal')->default(false)->after('attachments');
            }

            if (Schema::hasColumn('ticket_comments', 'author')) {
                $table->string('author')->nullable()->change();
            }
        });

        if (Schema::hasColumn('ticket_comments', 'content')) {
            DB::statement('UPDATE ticket_comments SET body = content WHERE body IS NULL');
            Schema::table('ticket_comments', function (Blueprint $table) {
                $table->dropColumn('content');
            });
        }

        Schema::table('ticket_comments', function (Blueprint $table) {
            if (Schema::hasColumn('ticket_comments', 'author')) {
                $table->dropColumn('author');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('ticket_comments')) {
            return;
        }

        Schema::table('ticket_comments', function (Blueprint $table) {
            if (! Schema::hasColumn('ticket_comments', 'author')) {
                $table->string('author')->nullable()->after('body');
            }

            if (! Schema::hasColumn('ticket_comments', 'content')) {
                $table->text('content')->nullable()->after('ticket_id');
            }

            if (Schema::hasColumn('ticket_comments', 'user_id')) {
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('ticket_comments', 'attachments')) {
                $table->dropColumn('attachments');
            }

            if (Schema::hasColumn('ticket_comments', 'is_internal')) {
                $table->dropColumn('is_internal');
            }
        });

        if (Schema::hasColumn('ticket_comments', 'body')) {
            DB::statement('UPDATE ticket_comments SET content = body WHERE content IS NULL');
            Schema::table('ticket_comments', function (Blueprint $table) {
                $table->dropColumn('body');
            });
        }
    }
};

