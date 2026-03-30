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
        Schema::create('floors', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->integer('floor_number');
            $table->string('name')->nullable();
            $table->timestamps();
            
            $table->unique(['hostel_id', 'floor_number']);
            $table->index(['tenant_id', 'hostel_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('floors');
    }
};

