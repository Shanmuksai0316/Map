<?php

declare(strict_types=1);

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
        Schema::create('tenants', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('code')->unique();
            $table->string('name');
            $table->enum('status', ['provisioning', 'active', 'suspended', 'archived', 'deleted'])->default('provisioning');
            $table->timestamp('suspended_at')->nullable();
            $table->text('suspended_reason')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->string('subscription_plan', 50)->nullable();
            $table->decimal('subscription_amount', 10, 2)->nullable();
            $table->date('subscription_starts_at')->nullable();
            $table->date('subscription_ends_at')->nullable();
            $table->string('payment_mode', 20)->default('offline');
            $table->text('payment_notes')->nullable();
            $table->boolean('addon_security')->default(false);
            $table->boolean('addon_sports')->default(false);
            $table->boolean('addon_laundry')->default(false);
            $table->jsonb('settings')->nullable();
            $table->jsonb('data')->nullable(); // Required by stancl/tenancy
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};

