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
        Schema::create('room_allocations', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id'); // UUID from central tenants table - required for cross-tenant queries
            
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_bed_id')->constrained('room_beds')->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['student_id', 'is_active']);
            $table->index(['room_bed_id', 'is_active']);
            $table->unique(['student_id', 'is_active'], 'room_allocations_unique_student_active');
            $table->unique(['room_bed_id', 'is_active'], 'room_allocations_unique_bed_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_allocations');
    }
};
