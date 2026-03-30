<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->timestamp('code_expires_at')->nullable()->after('code');
            $table->unsignedSmallInteger('code_attempts')->default(0)->after('code_expires_at');
            $table->timestamp('code_last_attempt_at')->nullable()->after('code_attempts');
        });
    }

    public function down(): void
    {
        Schema::table('parcels', function (Blueprint $table) {
            $table->dropColumn(['code_expires_at', 'code_attempts', 'code_last_attempt_at']);
        });
    }
};
