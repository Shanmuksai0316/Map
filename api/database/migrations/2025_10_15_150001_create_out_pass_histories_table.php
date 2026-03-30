<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('out_pass_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('out_pass_id')->constrained()->cascadeOnDelete();
            $table->string('action'); // created, approved, rejected, cancelled
            $table->unsignedBigInteger('actor_id')->nullable(); // Reference to central users table - no FK constraint
            $table->text('comment')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['out_pass_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_pass_histories');
    }
};

