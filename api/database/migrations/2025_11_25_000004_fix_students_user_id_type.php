<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // First, convert existing string user_id values to bigint
        // PostgreSQL requires explicit type casting
        DB::statement('ALTER TABLE students ALTER COLUMN user_id TYPE bigint USING user_id::bigint');
        
        // Add index for the user_id column if not exists
        Schema::table('students', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE students ALTER COLUMN user_id TYPE varchar(255) USING user_id::varchar');
        
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};

