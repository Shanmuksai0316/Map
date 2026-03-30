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
        if (!Schema::hasTable('tenant_settings')) {
            Schema::create('tenant_settings', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id'); // UUID from tenants table
                $table->string('key');
                $table->text('value');
                $table->timestamps();
                
                $table->unique(['tenant_id', 'key']);
                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'key']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};