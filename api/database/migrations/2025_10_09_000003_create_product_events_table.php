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
        Schema::create('product_events', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id'); // UUID from tenants table
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            if (Schema::hasTable('campuses')) {
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('campus_id')->nullable()->index();
            }

            if (Schema::hasTable('hostels')) {
            $table->foreignId('hostel_id')->nullable()->constrained('hostels')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('hostel_id')->nullable()->index();
            }

            if (Schema::hasTable('users')) {
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            } else {
                $table->unsignedBigInteger('user_id')->nullable()->index();
            }

            $table->string('role', 50)->nullable(); // Student, Warden, Rector, etc.
            $table->string('name', 100); // Event name from catalog (e.g., 'outpass.approved', 'attendance.marked')
            $table->string('entity_type', 100)->nullable(); // Polymorphic type
            $table->unsignedBigInteger('entity_id')->nullable(); // Polymorphic id
            $table->jsonb('properties')->nullable(); // Event-specific data
            $table->timestamp('happened_at'); // When the event occurred
            $table->timestamps();

            // Indexes for analytics queries
            $table->index(['tenant_id', 'name', 'happened_at']);
            $table->index(['tenant_id', 'hostel_id', 'name', 'happened_at']);
            $table->index(['tenant_id', 'campus_id', 'name', 'happened_at']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('happened_at'); // For time-series queries and partitioning
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_events');
    }
};

