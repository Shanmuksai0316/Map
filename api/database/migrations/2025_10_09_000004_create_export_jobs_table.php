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
        Schema::create('export_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // UUID from tenants table
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('type', 100); // students, outpasses, attendance, gate_entries, payments
            $table->jsonb('filters')->nullable(); // Export filters/parameters
            $table->enum('status', ['Queued', 'Running', 'Ready', 'Failed'])->default('Queued');
            $table->string('file_url', 500)->nullable(); // S3 presigned URL
            $table->string('file_key', 500)->nullable(); // S3 key
            $table->unsignedInteger('total_rows')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable(); // 7 days after completion
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'type', 'status', 'created_at']);
            $table->index(['tenant_id', 'user_id', 'created_at']);
            $table->index('expires_at'); // For cleanup job
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('export_jobs');
    }
};

