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
        Schema::create('pii_reveal_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // Enforce tenant isolation
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // Staff who revealed PII
            $table->unsignedBigInteger('student_id')->nullable(); // FK added post-create once students table exists
            $table->enum('pii_type', ['phone', 'guardian', 'medical']); // Type of PII revealed
            $table->string('ip_address', 45)->nullable(); // IP address of request
            $table->string('user_agent', 500)->nullable(); // User agent of request
            $table->timestamp('revealed_at'); // When PII was revealed
            $table->jsonb('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Indexes for audit queries
            $table->index(['tenant_id', 'user_id', 'revealed_at']);
            $table->index(['tenant_id', 'student_id']);
            $table->index(['tenant_id', 'pii_type', 'revealed_at']);
            $table->index('revealed_at'); // For retention queries
        });

        // Add FK separately so tests can run migrations even if students table is created later
        if (Schema::hasTable('students')) {
            Schema::table('pii_reveal_logs', function (Blueprint $table) {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('students')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pii_reveal_logs');
    }
};
