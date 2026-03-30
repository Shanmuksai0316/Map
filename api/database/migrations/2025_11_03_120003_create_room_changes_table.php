<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unique_id')->unique(); // Auto-generated unique identifier
            $table->string('title')->default('Room Change Request'); // Title for room change
            $table->text('description');
            $table->string('preferred_room_number')->nullable(); // Preferred room number
            $table->string('preferred_floor')->nullable(); // Preferred floor
            $table->enum('sharing_preference', ['single', 'double', 'triple', 'quad'])->nullable(); // Sharing preference
            $table->date('date_required')->nullable(); // Date when room change is required
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable(); // Reason if rejected (with reason)
            $table->unsignedBigInteger('approved_by')->nullable(); // Reference to central users table - no FK constraint
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at');
            $table->string('idempotency_key')->unique()->nullable();
            $table->timestamps();

            $table->index(['hostel_id', 'status']);
            $table->index(['student_id', 'submitted_at']);
            $table->index('unique_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_changes');
    }
};

