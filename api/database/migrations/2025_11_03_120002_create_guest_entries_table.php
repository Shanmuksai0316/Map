<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Guest Entry table - supports up to 4 guests per entry
        Schema::create('guest_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained()->cascadeOnDelete();
            $table->foreignId('hostel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unique_id')->unique(); // Auto-generated unique identifier
            $table->string('title')->default('Parents Visit'); // Title for guest entry
            $table->text('description');
            $table->json('guests'); // Array of up to 4 guests: [{name, phone, relationship, id_type, id_number}]
            $table->string('primary_contact_mobile'); // Primary contact mobile number
            $table->date('visit_date'); // Visit date
            $table->time('check_in_time'); // Check-in time
            $table->time('check_out_time'); // Check-out time
            $table->text('purpose_to_visit'); // Purpose to visit
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->text('rejection_reason')->nullable(); // Reason if rejected (with reason)
            $table->unsignedBigInteger('approved_by')->nullable(); // Reference to central users table - no FK constraint
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('submitted_at');
            $table->string('idempotency_key')->unique()->nullable();
            $table->timestamps();

            $table->index(['hostel_id', 'status']);
            $table->index(['student_id', 'submitted_at']);
            $table->index('visit_date');
            $table->index('unique_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guest_entries');
    }
};

