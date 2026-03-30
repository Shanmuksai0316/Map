<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_beds', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id'); // UUID from central tenants table - required for cross-tenant queries
            
            $table->foreignId('room_id')->constrained('rooms')->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('code', 16);
            $table->string('status', 20)->default('available');
            $table->timestamp('occupied_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->jsonb('meta')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['room_id', 'code']);
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_beds');
    }
};
