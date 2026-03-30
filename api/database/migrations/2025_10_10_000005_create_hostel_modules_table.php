<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_modules', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('hostel_id');
            $table->string('module_key', 64); // e.g., 'laundry_module', 'attendance_module'
            $table->timestamps();

            $table->unique(['hostel_id', 'module_key']);
            $table->index(['hostel_id', 'module_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_modules');
    }
};
