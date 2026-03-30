<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table) {
            if (Schema::hasColumn('idempotency_keys', 'idem_key')) {
                $table->string('idem_key')->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        // Do not revert to NOT NULL to avoid breaking existing data
    }
};

