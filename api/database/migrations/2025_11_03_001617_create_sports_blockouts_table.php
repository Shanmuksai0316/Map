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
        Schema::create('sports_blockouts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // Enforce tenant isolation
            $table->unsignedBigInteger('facility_id');
            $table->timestamp('start_at'); // Start time of blockout
            $table->timestamp('end_at'); // End time of blockout
            $table->string('reason', 500)->nullable(); // Reason for blockout (maintenance, event, etc.)
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade'); // Sports Manager who created it
            $table->jsonb('metadata')->nullable(); // Additional context
            $table->timestamps();

            // Indexes
            $table->index(['tenant_id', 'facility_id', 'start_at', 'end_at']);
            $table->index(['tenant_id', 'start_at', 'end_at']); // For checking overlapping blockouts
            $table->index(['tenant_id', 'created_by']);
        });

        if (Schema::hasTable('sports_facilities')) {
            Schema::table('sports_blockouts', function (Blueprint $table) {
                $table->foreign('facility_id')
                    ->references('id')
                    ->on('sports_facilities')
                    ->cascadeOnDelete();
            });
        } elseif (Schema::hasTable('facilities')) {
            Schema::table('sports_blockouts', function (Blueprint $table) {
                $table->foreign('facility_id')
                    ->references('id')
                    ->on('facilities')
                    ->cascadeOnDelete();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sports_blockouts');
    }
};
