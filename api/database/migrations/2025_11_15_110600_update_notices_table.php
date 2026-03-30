<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        if (! Schema::hasTable('notices')) {
            return;
        }

        if (Schema::hasColumn('notices', 'created_by')) {
            Schema::table('notices', function (Blueprint $table): void {
                $table->renameColumn('created_by', 'created_by_user_id');
            });
        }

        Schema::table('notices', function (Blueprint $table) use ($isSqlite): void {
            if (! Schema::hasColumn('notices', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }

            if (! Schema::hasColumn('notices', 'campus_id')) {
                $table->foreignId('campus_id')->nullable()->after('tenant_id')->constrained()->nullOnDelete();
            }

            if (Schema::hasColumn('notices', 'content') && ! Schema::hasColumn('notices', 'body')) {
                $table->renameColumn('content', 'body');
            }

            if (Schema::hasColumn('notices', 'priority')) {
                $table->dropColumn('priority');
            }

            if (! Schema::hasColumn('notices', 'status')) {
                $table->string('status')->default('draft')->after('body');
            }

            if (! Schema::hasColumn('notices', 'audience')) {
                $table->string('audience')->default('all_students')->after('status');
            }

            if (! Schema::hasColumn('notices', 'channels')) {
                if ($isSqlite) {
                    $table->json('channels')->nullable()->after('audience');
                } else {
                    $table->jsonb('channels')->nullable()->after('audience');
                }
            }

            if (! Schema::hasColumn('notices', 'publish_at')) {
                $table->timestamp('publish_at')->nullable()->after('channels');
            }

            if (! Schema::hasColumn('notices', 'expires_at')) {
                $table->timestamp('expires_at')->nullable()->after('published_at');
            }

            if (! Schema::hasColumn('notices', 'attachment_url')) {
                $table->string('attachment_url')->nullable()->after('expires_at');
            }

            if (! $isSqlite && Schema::hasColumn('notices', 'is_published')) {
                $table->dropColumn('is_published');
            }

            if (Schema::hasColumn('notices', 'created_by_user_id')) {
                $table->unsignedBigInteger('created_by_user_id')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notices')) {
            return;
        }

        Schema::table('notices', function (Blueprint $table): void {
            if (Schema::hasColumn('notices', 'expires_at')) {
                $table->dropColumn('expires_at');
            }

            if (Schema::hasColumn('notices', 'attachment_url')) {
                $table->dropColumn('attachment_url');
            }

            if (Schema::hasColumn('notices', 'publish_at')) {
                $table->dropColumn('publish_at');
            }

            if (Schema::hasColumn('notices', 'channels')) {
                $table->dropColumn('channels');
            }

            if (Schema::hasColumn('notices', 'audience')) {
                $table->dropColumn('audience');
            }

            if (Schema::hasColumn('notices', 'status')) {
                $table->dropColumn('status');
            }

            if (! Schema::hasColumn('notices', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('priority');
            }

            if (! Schema::hasColumn('notices', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal')->after('title');
            }

            if (Schema::hasColumn('notices', 'campus_id')) {
                $table->dropConstrainedForeignId('campus_id');
            }

            if (Schema::hasColumn('notices', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }

            if (Schema::hasColumn('notices', 'body') && ! Schema::hasColumn('notices', 'content')) {
                $table->renameColumn('body', 'content');
            }

            if (Schema::hasColumn('notices', 'created_by_user_id')) {
                $table->renameColumn('created_by_user_id', 'created_by');
            }
        });
    }
};

