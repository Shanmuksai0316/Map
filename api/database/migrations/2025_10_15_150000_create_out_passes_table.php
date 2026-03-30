<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('out_passes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('reason')->nullable();
            $table->boolean('overnight')->default(false);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->timestamp('requested_at');
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->text('note')->nullable();
            $table->string('idempotency_key')->unique();
            $table->unsignedBigInteger('decision_by')->nullable(); // Reference to central users table - no FK constraint;
            $table->timestamps();

            $table->index(['hostel_id', 'status']);
            $table->index(['student_id', 'requested_at']);
            $table->index('decision_by');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_passes');
    }
};

