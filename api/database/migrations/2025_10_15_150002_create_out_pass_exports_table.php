<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('out_pass_exports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('requested_by'); // Reference to central users table - no FK constraint
            $table->enum('status', ['pending', 'processing', 'completed', 'failed'])->default('pending');
            $table->string('file_path')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('out_pass_exports');
    }
};

