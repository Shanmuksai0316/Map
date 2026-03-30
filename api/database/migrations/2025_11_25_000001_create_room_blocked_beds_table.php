<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_blocked_beds', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->foreignId('room_bed_id')->constrained('room_beds')->cascadeOnDelete();
            $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->index(['room_bed_id', 'effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_blocked_beds');
    }
};

