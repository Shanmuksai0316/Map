<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('data_retention_policies', function (Blueprint $table) {
            $table->id();
            $table->string('entity'); // e.g. audit_logs, product_events, export_jobs
            $table->unsignedInteger('retention_days');
            $table->string('tenant_id')->nullable(); // null = all tenants
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_retention_policies');
    }
};
