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
        Schema::create('parcels', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 255);
            $table->unsignedBigInteger('hostel_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('received_by_user_id')->comment('Warden who logged the parcel');
            $table->string('status', 32)->default('informed')->comment('informed = student notified, received = handover done');
            $table->string('code', 4)->comment('4-digit code for handover verification');
            $table->string('room_number', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('informed_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->unsignedBigInteger('received_verified_by_user_id')->nullable()->comment('Warden who entered code');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['hostel_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parcels');
    }
};
