<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendance_room_submissions')) {
            Schema::create('attendance_room_submissions', function (Blueprint $table): void {
                $table->id();
                $table->string('tenant_id')->nullable(); // Changed to string to support UUID tenant IDs
                $table->unsignedBigInteger('attendance_session_id');
                $table->unsignedBigInteger('room_id');
                $table->unsignedBigInteger('submitted_by');
                $table->timestamp('submitted_at');
                $table->timestamps();

                $table->index(['attendance_session_id', 'room_id'], 'attendance_room_submissions_session_room_idx');
                $table->index('tenant_id', 'attendance_room_submissions_tenant_idx');

                // Use constrained() only if the tables exist in all deployments
                $table->foreign('attendance_session_id')
                    ->references('id')
                    ->on('attendance_sessions')
                    ->onDelete('cascade');

                $table->foreign('room_id')
                    ->references('id')
                    ->on('rooms')
                    ->onDelete('cascade');

                $table->foreign('submitted_by')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_room_submissions');
    }
};

