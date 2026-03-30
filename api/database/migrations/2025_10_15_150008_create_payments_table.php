<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('reference')->unique();
            $table->integer('amount_paise');
            $table->string('currency', 3)->default('INR');
            $table->enum('status', ['pending', 'confirmed', 'failed', 'refunded'])->default('pending');
            $table->string('mode')->nullable(); // cash, upi, card, bank, cheque
            $table->text('metadata')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

