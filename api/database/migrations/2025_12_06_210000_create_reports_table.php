<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('reports')) {
            return;
        }

        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable();
            $table->string('name');
            $table->json('params')->nullable();
            $table->string('status')->default('queued');
            $table->string('storage_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reports');
    }
};

