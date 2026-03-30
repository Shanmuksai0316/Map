<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->string('status')->default('queued')->after('payload');
            $table->unsignedInteger('attempts')->default(0)->after('status');
            $table->timestamp('next_retry_at')->nullable()->after('attempts');
            $table->timestamp('processed_at')->nullable()->after('next_retry_at');
            $table->text('last_error')->nullable()->after('processed_at');
        });
    }

    public function down(): void
    {
        Schema::table('webhook_logs', function (Blueprint $table) {
            $table->dropColumn(['status', 'attempts', 'next_retry_at', 'processed_at', 'last_error']);
        });
    }
};
