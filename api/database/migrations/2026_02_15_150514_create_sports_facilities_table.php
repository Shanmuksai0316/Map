<?php

/**
 * Creates the sports_facilities table for the Sports Facilities CRUD in Campus Manager.
 *
 * Used by the SportsFacility model and SportsFacilityResource.
 * Idempotent: checks if the table already exists before creating (see Schema::hasTable guard below).
 */

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sports_facilities')) {
            return;
        }

        Schema::create('sports_facilities', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->nullable()->index();
            $table->foreignId('hostel_id')->nullable()->constrained('hostels')->nullOnDelete();
            $table->string('name');
            $table->string('type')->nullable();
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->integer('capacity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('rules')->nullable();
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sports_facilities');
    }
};
