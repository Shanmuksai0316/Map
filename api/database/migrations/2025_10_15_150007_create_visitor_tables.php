<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guest visits
        Schema::create('guest_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_name');
            $table->string('visitor_phone')->nullable();
            $table->string('visitor_id_type')->nullable();
            $table->string('visitor_id_number')->nullable();
            $table->enum('status', ['pending', 'allowed', 'denied', 'completed'])->default('pending');
            $table->date('visiting_date');
            $table->time('entry_time')->nullable();
            $table->time('exit_time')->nullable();
            $table->text('purpose')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable(); // Reference to central users table - no FK constraint
            $table->timestamps();

            $table->index(['hostel_id', 'visiting_date', 'status']);
            $table->index(['student_id', 'visiting_date']);
        });

        // Visitor pre-registrations
        Schema::create('visitor_pre_registrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->string('visitor_name');
            $table->string('visitor_phone');
            $table->date('visit_date');
            $table->text('purpose')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['student_id', 'visit_date']);
        });

        // Visitor logs
        Schema::create('visitor_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guest_visit_id')->nullable()->constrained('guest_visits')->nullOnDelete();
            $table->foreignId('pre_registration_id')->nullable()->constrained('visitor_pre_registrations')->nullOnDelete();
            $table->string('action'); // entry, exit, approved, denied
            $table->unsignedBigInteger('logged_by')->nullable(); // Reference to central users table - no FK constraint
            $table->timestamp('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['guest_visit_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visitor_logs');
        Schema::dropIfExists('visitor_pre_registrations');
        Schema::dropIfExists('guest_visits');
    }
};

