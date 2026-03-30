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
        Schema::create('tenant_impersonation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('super_admin_id')->comment('Super Admin user ID who initiated impersonation');
            $table->string('tenant_id')->comment('Tenant UUID being accessed');
            $table->string('impersonated_user_id')->comment('Tenant admin user ID being impersonated');
            $table->timestamp('started_at')->comment('When impersonation started');
            $table->timestamp('ended_at')->nullable()->comment('When impersonation ended');
            $table->string('ip_address', 45)->comment('IP address of Super Admin');
            $table->text('reason')->nullable()->comment('Reason for impersonation (audit trail)');
            $table->timestamps();

            // Indexes for efficient querying
            $table->index('super_admin_id');
            $table->index('tenant_id');
            $table->index('started_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_impersonation_logs');
    }
};

