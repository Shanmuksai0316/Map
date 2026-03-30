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
        Schema::create('offline_actions', function (Blueprint $table) {
            $table->id();
            $table->string('action_id')->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->string('action_type'); // 'gate_entry', 'gate_exit', 'attendance_update', 'visitor_log'
            $table->json('action_data'); // Encrypted action payload
            $table->string('device_id')->nullable();
            $table->string('status')->default('pending'); // 'pending', 'processing', 'completed', 'failed'
            $table->integer('retry_count')->default(0);
            $table->timestamp('queued_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable(); // Additional context
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'status', 'queued_at']);
            $table->index(['user_id', 'action_type']);
            $table->index(['status', 'queued_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offline_actions');
    }
};
