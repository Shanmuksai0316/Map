<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NOTE: This migration runs on TENANT databases
        // tenant_id is required for cross-tenant queries
        Schema::create('students', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id'); // UUID from central tenants table - required for cross-tenant queries
            $table->string('user_id'); // Reference to central database user - no foreign key constraint
            $table->foreignId('hostel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('map_student_id')->unique();
            $table->string('student_uid');
            $table->string('roll_no')->nullable();
            $table->string('program')->nullable();
            $table->unsignedTinyInteger('year_of_study')->nullable();
            $table->unsignedSmallInteger('admission_year')->nullable();
            $table->boolean('hostel_fee_paid')->default(false);
            $table->string('payment_mode')->nullable(); // cash|upi|card|bank|cheque
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->string('payment_reference')->nullable(); // Receipt/Transaction no
            $table->text('payment_notes')->nullable();
            $table->text('guardian')->nullable(); // Encrypted - stored as text, not JSONB
            $table->text('medical_notes')->nullable(); // Encrypted - stored as text, not JSONB
            $table->jsonb('correspondence_address')->nullable();
            $table->timestamps();

            $table->index('tenant_id');
            $table->unique(['tenant_id', 'student_uid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
