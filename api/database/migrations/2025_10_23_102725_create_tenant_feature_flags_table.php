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
        Schema::create('tenant_feature_flags', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature_key'); // 'security_module', 'sports_module', 'laundry_module'
            $table->boolean('is_enabled')->default(false);
            $table->json('config')->nullable(); // Feature-specific configuration
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->unsignedBigInteger('enabled_by_user_id')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('enabled_by_user_id')->references('id')->on('users')->onDelete('set null');
            $table->unique(['tenant_id', 'feature_key']);
            $table->index(['tenant_id', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_feature_flags');
    }
};
