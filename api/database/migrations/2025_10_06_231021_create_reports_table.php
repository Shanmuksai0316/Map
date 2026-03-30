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
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index(); // UUID from tenants table
            $table->string('name'); // e.g. hostel_performance
            $table->jsonb('params'); // {"from":"...","to":"...","hostel_id":null}
            $table->string('status')->default('queued'); // queued|running|done|failed
            $table->string('storage_path')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};