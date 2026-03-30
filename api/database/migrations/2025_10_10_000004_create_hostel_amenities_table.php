<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hostel_amenities', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('hostel_id');
            $table->unsignedBigInteger('amenity_id');
            $table->timestamps();

            $table->unique(['hostel_id', 'amenity_id']);
            $table->index(['hostel_id', 'amenity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hostel_amenities');
    }
};
