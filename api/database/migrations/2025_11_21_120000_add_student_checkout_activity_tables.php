<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table): void {
            $table->foreignId('preferred_hostel_id')->nullable()->after('hostel_id')->constrained('hostels')->nullOnDelete();
            $table->string('preferred_floor', 32)->nullable()->after('preferred_hostel_id');
            $table->string('preferred_room_type', 32)->nullable()->after('preferred_floor');
            $table->string('preferred_sharing', 32)->nullable()->after('preferred_room_type');
            $table->timestampTz('archived_at')->nullable()->after('updated_at');
            $table->text('archived_reason')->nullable()->after('archived_at');
            $table->foreignId('archived_by')->nullable()->after('archived_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('room_allocations', function (Blueprint $table): void {
            $table->timestamp('expected_checkout_at')->nullable()->after('effective_to');
            $table->timestamp('checkout_notified_at')->nullable()->after('expected_checkout_at');
            $table->string('checkout_status', 32)->default('pending')->after('checkout_notified_at');
        });

        Schema::create('checkout_checklists', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('room_allocation_id')->constrained('room_allocations')->cascadeOnDelete();
            $table->enum('status', ['draft', 'in_progress', 'completed', 'cancelled'])->default('draft');
            $table->boolean('inspection_passed')->nullable();
            $table->boolean('keys_collected')->nullable();
            $table->boolean('dues_cleared')->nullable();
            $table->json('photos')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('checkout_histories', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('room_allocation_id')->constrained('room_allocations')->cascadeOnDelete();
            $table->string('event', 64);
            $table->json('payload')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'event']);
        });

        Schema::create('hostel_change_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('hostel_id')->constrained('hostels')->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->json('changes');
            $table->string('reason', 255)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'hostel_id']);
        });

        Schema::create('activity_feed_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('type', 64);
            $table->string('channel', 32)->default('system');
            $table->morphs('related');
            $table->string('title');
            $table->text('body')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('visibility', ['internal', 'tenant', 'staff'])->default('tenant');
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'created_at']);
        });

        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->string('related_type')->nullable()->after('payload_json');
            $table->unsignedBigInteger('related_id')->nullable()->after('related_type');
        });
    }

    public function down(): void
    {
        Schema::table('notification_logs', function (Blueprint $table): void {
            $table->dropColumn(['related_type', 'related_id']);
        });

        Schema::dropIfExists('activity_feed_entries');
        Schema::dropIfExists('hostel_change_logs');
        Schema::dropIfExists('checkout_histories');
        Schema::dropIfExists('checkout_checklists');

        Schema::table('room_allocations', function (Blueprint $table): void {
            $table->dropColumn(['expected_checkout_at', 'checkout_notified_at', 'checkout_status']);
        });

        Schema::table('students', function (Blueprint $table): void {
            $table->dropForeign(['preferred_hostel_id']);
            $table->dropColumn(['preferred_hostel_id', 'preferred_floor', 'preferred_room_type', 'preferred_sharing']);
            $table->dropForeign(['archived_by']);
            $table->dropColumn(['archived_at', 'archived_reason', 'archived_by']);
        });
    }
};
