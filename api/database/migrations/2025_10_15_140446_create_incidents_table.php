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
        Schema::create('incidents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reporter_user_id')->nullable(); // Reference to central users table - no FK constraint
            $table->foreignId('reporter_student_id')->nullable()->constrained('students')->nullOnDelete();
            $table->string('title');
            $table->text('description');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->enum('status', ['open', 'investigating', 'resolved', 'closed'])->default('open');
            $table->unsignedBigInteger('assigned_to_user_id')->nullable(); // Reference to central users table - no FK constraint
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index(['hostel_id', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['assigned_to_user_id', 'status']);
            $table->index(['opened_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidents');
    }
};