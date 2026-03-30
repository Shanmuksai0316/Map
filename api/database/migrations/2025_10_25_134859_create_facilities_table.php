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
        Schema::create('facilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->time('open_time');
            $table->time('close_time');
            $table->string('type')->default('general'); // e.g., 'sports', 'gym', 'courtyard', 'games_room'
            $table->integer('capacity')->default(1);
            $table->boolean('is_active')->default(true);
            $table->json('rules')->nullable(); // booking rules, restrictions, etc.
            $table->text('description')->nullable();
            $table->timestamps();

            // Indexes as per data dictionary (without tenant_id)
            $table->index(['hostel_id', 'name']);
            $table->index(['type', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('facilities');
    }
};
