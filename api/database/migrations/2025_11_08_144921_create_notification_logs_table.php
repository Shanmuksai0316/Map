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
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->nullable()->index();
            $table->string('recipient', 255)->comment('Phone number or email');
            $table->enum('channel', ['sms', 'email', 'push'])->index();
            $table->string('template', 100)->nullable()->comment('Template identifier');
            $table->json('payload_json')->nullable()->comment('Notification payload');
            $table->enum('status', ['pending', 'sent', 'failed', 'delivered'])->default('pending')->index();
            $table->text('error')->nullable()->comment('Error message if failed');
            $table->timestamp('sent_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('created_at')->useCurrent();
            
            $table->index(['tenant_id', 'status', 'created_at']);
            $table->index(['recipient', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
