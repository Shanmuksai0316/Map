<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sports activities
        Schema::create('sports_activities', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Sports equipment
        Schema::create('sports_equipment', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->foreignId('activity_id')->nullable()->constrained('sports_activities')->nullOnDelete();
            $table->integer('quantity')->default(1);
            $table->integer('available')->default(1);
            $table->enum('condition', ['excellent', 'good', 'fair', 'poor'])->default('good');
            $table->timestamps();
        });

        // Sports enrollments
        Schema::create('sports_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('activity_id')->constrained('sports_activities')->cascadeOnDelete();
            $table->date('enrolled_at');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();

            $table->unique(['student_id', 'activity_id']);
        });

        // Sports equipment loans
        Schema::create('sports_equipment_loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('equipment_id')->constrained('sports_equipment')->cascadeOnDelete();
            $table->date('borrowed_at');
            $table->date('due_at');
            $table->date('returned_at')->nullable();
            $table->enum('status', ['borrowed', 'returned', 'overdue'])->default('borrowed');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'status']);
            $table->index(['equipment_id', 'borrowed_at']);
        });

        // Sports events
        Schema::create('sports_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('activity_id')->constrained('sports_activities')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('event_date');
            $table->time('event_time')->nullable();
            $table->string('venue')->nullable();
            $table->timestamps();

            $table->index(['activity_id', 'event_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sports_events');
        Schema::dropIfExists('sports_equipment_loans');
        Schema::dropIfExists('sports_enrollments');
        Schema::dropIfExists('sports_equipment');
        Schema::dropIfExists('sports_activities');
    }
};

