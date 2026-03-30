<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cache')) {
            return;
        }

        Schema::create('cache', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
            $table->integer('expiration')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache');
    }
};

