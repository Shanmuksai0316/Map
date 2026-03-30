<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('push_device_tokens', function (Blueprint $t) {
            if (!Schema::hasColumn('push_device_tokens', 'platform')) {
                $t->string('platform', 32)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('push_device_tokens', 'meta')) {
                $t->json('meta')->nullable()->after('device_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('push_device_tokens', function (Blueprint $t) {
            if (Schema::hasColumn('push_device_tokens', 'meta')) {
                $t->dropColumn('meta');
            }

            if (Schema::hasColumn('push_device_tokens', 'platform')) {
                $t->dropColumn('platform');
            }
        });
    }
};
