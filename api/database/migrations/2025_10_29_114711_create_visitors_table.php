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
        Schema::create('visitors', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('visitor_name');
            $table->string('visitor_phone', 20);
            $table->enum('visitor_id_type', ['aadhar', 'pan', 'driving_license', 'passport', 'other']);
            $table->string('visitor_id_number', 50);
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('guard_id');
            $table->text('purpose');
            $table->integer('expected_duration')->nullable()->comment('Duration in minutes');
            $table->string('vehicle_number', 20)->nullable();
            $table->integer('accompanying_persons')->default(0);
            $table->text('notes')->nullable();
            $table->enum('status', ['pending', 'allowed', 'denied', 'exited'])->default('pending');
            $table->date('visit_date');
            $table->timestamp('allowed_at')->nullable();
            $table->unsignedBigInteger('allowed_by')->nullable();
            $table->timestamp('denied_at')->nullable();
            $table->unsignedBigInteger('denied_by')->nullable();
            $table->text('denial_reason')->nullable();
            $table->timestamp('exited_at')->nullable();
            $table->unsignedBigInteger('exited_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Foreign key constraints
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');

            if (Schema::hasTable('students')) {
            $table->foreign('student_id')->references('id')->on('students')->onDelete('cascade');
            }

            $table->foreign('guard_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('allowed_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('denied_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('exited_by')->references('id')->on('users')->onDelete('set null');

            // Indexes
            $table->index(['tenant_id', 'visit_date']);
            $table->index(['tenant_id', 'status']);
            $table->index(['student_id', 'visit_date']);
            $table->index(['guard_id', 'visit_date']);
            $table->index('visitor_phone');
            $table->index('visitor_id_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('visitors');
    }
};