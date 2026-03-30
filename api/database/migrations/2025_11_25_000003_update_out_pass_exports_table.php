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

        Schema::table('out_pass_exports', function (Blueprint $table) use ($isSqlite) {
            // Add tenant_id if missing
            if (!Schema::hasColumn('out_pass_exports', 'tenant_id')) {
                $table->uuid('tenant_id')->nullable()->after('id');
                $table->index('tenant_id');
            }
            
            // Add filters column if missing
            if (!Schema::hasColumn('out_pass_exports', 'filters')) {
                if ($isSqlite) {
                    $table->json('filters')->nullable()->after('status');
                } else {
                    $table->jsonb('filters')->nullable()->after('status');
                }
            }
            
            // Add error column if missing
            if (!Schema::hasColumn('out_pass_exports', 'error')) {
                $table->text('error')->nullable()->after('file_path');
            }
        });
        
        // Update the status enum to include 'complete' (model uses 'complete' not 'completed')
        if (! $isSqlite) {
            DB::statement("ALTER TABLE out_pass_exports ALTER COLUMN status TYPE varchar(50)");
            DB::statement("ALTER TABLE out_pass_exports DROP CONSTRAINT IF EXISTS out_pass_exports_status_check");
            DB::statement("ALTER TABLE out_pass_exports ADD CONSTRAINT out_pass_exports_status_check CHECK (status IN ('pending', 'processing', 'complete', 'completed', 'failed'))");
        }
    }

    public function down(): void
    {
        $isSqlite = DB::getDriverName() === 'sqlite';

        Schema::table('out_pass_exports', function (Blueprint $table) {
            if (Schema::hasColumn('out_pass_exports', 'filters')) {
                $table->dropColumn('filters');
            }
            if (Schema::hasColumn('out_pass_exports', 'error')) {
                $table->dropColumn('error');
            }
        });
        
        if (! $isSqlite) {
            DB::statement("ALTER TABLE out_pass_exports DROP CONSTRAINT IF EXISTS out_pass_exports_status_check");
            DB::statement("ALTER TABLE out_pass_exports ADD CONSTRAINT out_pass_exports_status_check CHECK (status IN ('pending', 'processing', 'completed', 'failed'))");
        }
    }
};

