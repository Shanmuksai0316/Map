<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Seeds default guard checklist template
     */
    public function up(): void
    {
        // This is a seeder-like migration that adds default checklist template for guards
        // It will be tenant-specific, so we'll add a template that gets copied on tenant creation
        
        // First, let's ensure the checklist_templates table has the 'role' column
        if (\Illuminate\Support\Facades\Schema::hasTable('checklist_templates')) {
            if (!\Illuminate\Support\Facades\Schema::hasColumn('checklist_templates', 'role')) {
                \Illuminate\Support\Facades\Schema::table('checklist_templates', function ($table) {
                    $table->string('role', 50)->nullable()->after('name');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No rollback needed for seeder data
    }
};

