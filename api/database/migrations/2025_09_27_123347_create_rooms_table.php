<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id'); // UUID from central tenants table - required for cross-tenant queries
            
            $table->foreignId('campus_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('block_code', 16)->nullable();
            $table->string('floor_code', 16)->nullable();
            $table->string('number', 16);
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['hostel_id', 'number']);
            $table->index(['hostel_id', 'block_code', 'floor_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
