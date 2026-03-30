<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: This migration runs on TENANT databases
        // tenant_id is required for cross-tenant queries and ready checks
        Schema::create('hostels', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id'); // UUID from central tenants table - required for cross-tenant queries
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->string('code');
            $table->string('name');
            $table->string('gender_mode');
            $table->time('curfew_time');
            $table->boolean('overnight_enabled')->default(false);
            $table->time('visiting_start')->nullable();
            $table->time('visiting_end')->nullable();
            $table->jsonb('settings')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostels');
    }
};
