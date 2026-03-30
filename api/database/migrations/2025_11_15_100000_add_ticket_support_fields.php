<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (! Schema::hasColumn('tickets', 'hostel_id')) {
                $table->foreignId('hostel_id')
                    ->nullable()
                    ->constrained()
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'reporter_student_id')) {
                $table->foreignId('reporter_student_id')
                    ->nullable()
                    ->constrained('students')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'reporter_user_id')) {
                $table->foreignId('reporter_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'assignee_user_id')) {
                $table->foreignId('assignee_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'photos')) {
                $table->json('photos')->nullable();
            }

            if (! Schema::hasColumn('tickets', 'sla_due_at')) {
                $table->timestamp('sla_due_at')->nullable();
            }

            if (! Schema::hasColumn('tickets', 'closed_at')) {
                $table->timestamp('closed_at')->nullable();
            }

            if (! Schema::hasColumn('tickets', 'created_by_user_id')) {
                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'updated_by_user_id')) {
                $table->foreignId('updated_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('tickets', 'location')) {
                $table->string('location')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table): void {
            if (Schema::hasColumn('tickets', 'updated_by_user_id')) {
                $table->dropForeign(['updated_by_user_id']);
                $table->dropColumn('updated_by_user_id');
            }

            if (Schema::hasColumn('tickets', 'created_by_user_id')) {
                $table->dropForeign(['created_by_user_id']);
                $table->dropColumn('created_by_user_id');
            }

            if (Schema::hasColumn('tickets', 'assignee_user_id')) {
                $table->dropForeign(['assignee_user_id']);
                $table->dropColumn('assignee_user_id');
            }

            if (Schema::hasColumn('tickets', 'reporter_user_id')) {
                $table->dropForeign(['reporter_user_id']);
                $table->dropColumn('reporter_user_id');
            }

            if (Schema::hasColumn('tickets', 'reporter_student_id')) {
                $table->dropForeign(['reporter_student_id']);
                $table->dropColumn('reporter_student_id');
            }

            if (Schema::hasColumn('tickets', 'hostel_id')) {
                $table->dropForeign(['hostel_id']);
                $table->dropColumn('hostel_id');
            }

            foreach (['photos', 'sla_due_at', 'closed_at', 'location'] as $column) {
                if (Schema::hasColumn('tickets', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};

