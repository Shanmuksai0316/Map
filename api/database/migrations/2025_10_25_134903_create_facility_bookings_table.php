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
        Schema::create('facility_bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facility_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->datetime('start_at');
            $table->datetime('end_at');
            $table->enum('status', ['active', 'cancelled', 'no_show', 'completed'])->default('active');
            $table->string('purpose')->nullable(); // e.g., 'football practice', 'basketball game'
            $table->integer('participants')->default(1);
            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes as per data dictionary (without tenant_id)
            $table->unique(['facility_id', 'start_at', 'end_at']); // Prevent overlapping bookings
            $table->index(['student_id', 'status']);
            $table->index(['start_at', 'status']);
            $table->index(['facility_id', 'start_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facility_bookings');
    }
};
