<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('checklist_templates')) {
            Schema::create('checklist_templates', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->string('role');
                $table->string('title');
                $table->jsonb('tasks');
                $table->boolean('active')->default(true);
                $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['tenant_id', 'role']);
            });
        }

        if (! Schema::hasTable('checklist_instances')) {
            Schema::create('checklist_instances', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->foreignId('template_id')->constrained('checklist_templates')->cascadeOnDelete();
                $table->date('date');
                $table->string('shift')->default('Daily');
                $table->string('role');
                $table->foreignId('assignee_user_id')->constrained('users')->cascadeOnDelete();
                $table->string('status')->default('Pending');
                $table->string('review_status')->nullable();
                $table->unsignedSmallInteger('total_tasks')->default(0);
                $table->unsignedSmallInteger('completed_tasks')->default(0);
                $table->timestamp('submitted_at')->nullable();
                $table->foreignId('manager_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('manager_note')->nullable();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'role']);
                $table->index(['tenant_id', 'date']);
                $table->index(['tenant_id', 'status']);
            });
        }

        if (! Schema::hasTable('checklist_items')) {
            Schema::create('checklist_items', function (Blueprint $table): void {
                $table->id();
                $table->uuid('tenant_id');
                $table->foreignId('instance_id')->constrained('checklist_instances')->cascadeOnDelete();
                $table->string('code');
                $table->string('label');
                $table->string('state')->default('Pending');
                $table->text('comment')->nullable();
                $table->json('photo_urls')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'instance_id']);
                $table->index(['instance_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_items');
        Schema::dropIfExists('checklist_instances');
        Schema::dropIfExists('checklist_templates');
    }
};

