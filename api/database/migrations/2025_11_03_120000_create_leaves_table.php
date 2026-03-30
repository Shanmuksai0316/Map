<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leaves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unique_id')->unique(); // Auto-generated unique identifier
            $table->string('title'); // Title of the leave request
            $table->text('description');
            $table->string('reason_for_leave'); // Reason for leave
            $table->date('from_date');
            $table->date('to_date');
            $table->string('emergency_contact')->nullable(); // Optional emergency contact
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable(); // Reason if rejected
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
        Schema::dropIfExists('leaves');
    }
};

