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
        Schema::create('gate_pass_scans', function (Blueprint $table) {
            $table->id();
            $table->string('scan_id')->unique();
            $table->string('tenant_id');
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('scanned_by_user_id');
            $table->string('qr_data'); // Encrypted QR data
            $table->string('scan_type'); // 'entry', 'exit'
            $table->string('gate_location')->nullable();
            $table->string('device_id')->nullable();
            $table->json('qr_metadata'); // Decrypted QR data
            $table->boolean('is_valid')->default(true);
            $table->string('rejection_reason')->nullable();
            $table->timestamp('scan_timestamp');
            $table->timestamps();
            
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('scanned_by_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['tenant_id', 'scan_timestamp']);
            $table->index(['student_id', 'scan_type']);
            $table->index(['scanned_by_user_id', 'scan_timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gate_pass_scans');
    }
};
