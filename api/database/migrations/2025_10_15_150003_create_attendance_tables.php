<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Attendance logs
        if (!Schema::hasTable('attendance_logs')) {
            Schema::create('attendance_logs', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id'); // Added tenant_id for multi-tenant isolation
                $table->foreignId('student_id')->constrained()->cascadeOnDelete();
                $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
                $table->date('attendance_date');
                $table->enum('status', ['present', 'absent', 'excused'])->default('present');
                $table->unsignedBigInteger('marked_by')->nullable(); // Reference to users table - no FK constraint
                $table->timestamp('marked_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->unique(['tenant_id', 'student_id', 'attendance_date']); // Updated unique index
                $table->index(['tenant_id', 'hostel_id', 'attendance_date']); // Updated index
            });
        }

        // Attendance sessions
        if (!Schema::hasTable('attendance_sessions')) {
            Schema::create('attendance_sessions', function (Blueprint $table) {
                $table->id();
                $table->string('tenant_id'); // Added tenant_id for multi-tenant isolation
                $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
                $table->date('session_date');
                $table->time('session_time');
                $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending');
                $table->unsignedBigInteger('started_by')->nullable(); // Reference to users table - no FK constraint
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'hostel_id', 'session_date']); // Updated index
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_sessions');
        Schema::dropIfExists('attendance_logs');
    }
};

