<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gate_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_uuid')->unique();
            $table->string('name');
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamps();

            $table->index(['hostel_id', 'is_active']);
            $table->index('device_uuid');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_devices');
    }
};

