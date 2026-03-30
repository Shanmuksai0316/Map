<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        Schema::table('students', function (Blueprint $table): void {
            if (! Schema::hasColumn('students', 'gender')) {
                $table->string('gender', 16)->nullable()->after('program');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('students')) {
            return;
        }

        Schema::table('students', function (Blueprint $table): void {
            if (Schema::hasColumn('students', 'gender')) {
                $table->dropColumn('gender');
            }
        });
    }
};

