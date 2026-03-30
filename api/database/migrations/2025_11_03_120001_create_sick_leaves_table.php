<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sick_leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unique_id')->unique(); // Auto-generated unique identifier
            $table->string('title'); // Title of the sick leave request
            $table->text('description');
            $table->string('illness'); // Type of illness
            $table->text('illness_details'); // Detailed description of illness
            $table->boolean('need_medical_attention')->default(false); // Toggle: "Do you need to see a doctor?"
            $table->boolean('contact_parents')->default(false); // Toggle: "Should we inform your parents?"
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
        Schema::dropIfExists('sick_leaves');
    }
};

