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
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 50); // razorpay, msg91, sendgrid
            $table->string('event_type', 100); // payment.captured, payment.failed, etc.
            $table->string('event_id', 255)->unique(); // For idempotency
            $table->boolean('valid_signature')->default(false);
            $table->jsonb('payload')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            // Indexes
            $table->index(['source', 'event_type', 'received_at']);
            $table->index('received_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};

