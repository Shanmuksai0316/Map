<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename existing tenants table to tenants_legacy for backward compatibility
        Schema::create('tenants_legacy', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('addon_security')->default(false);
            $table->boolean('addon_sports')->default(false);
            $table->boolean('addon_laundry')->default(false);
            $table->jsonb('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants_legacy');
    }
};
