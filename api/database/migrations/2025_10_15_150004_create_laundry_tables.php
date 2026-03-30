<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Laundry requests
        Schema::create('laundry_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->integer('quantity')->default(1);
            $table->enum('status', ['pending', 'picked_up', 'in_process', 'ready', 'delivered'])->default('pending');
            $table->date('requested_date');
            $table->date('pickup_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['hostel_id', 'status']);
            $table->index(['student_id', 'requested_date']);
        });

        // Laundry cycles
        Schema::create('laundry_cycles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('cycle_name');
            $table->date('cycle_date');
            $table->enum('status', ['scheduled', 'active', 'completed'])->default('scheduled');
            $table->timestamps();

            $table->index(['hostel_id', 'cycle_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_cycles');
        Schema::dropIfExists('laundry_requests');
    }
};

