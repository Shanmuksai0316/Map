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
        Schema::create('gate_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('guard_id')->nullable(); // Reference to central users table - no FK constraint;
            $table->string('event'); // 'entry' or 'exit'
            $table->timestamp('occurred_at');
            $table->string('source')->default('manual'); // 'manual', 'device', 'mobile'
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'occurred_at']);
            $table->index(['guard_id', 'occurred_at']);
            $table->index(['event', 'occurred_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_entries');
    }
};