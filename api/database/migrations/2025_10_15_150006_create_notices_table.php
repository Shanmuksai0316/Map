<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hostel_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('content');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->unsignedBigInteger('created_by'); // Reference to central users table - no FK constraint
            $table->timestamps();
            $table->softDeletes();

            $table->index(['hostel_id', 'is_published', 'published_at']);
        });

        // Notice attachments
        Schema::create('notice_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notice_id')->constrained()->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type')->nullable();
            $table->integer('file_size')->nullable();
            $table->timestamps();

            $table->index('notice_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notice_attachments');
        Schema::dropIfExists('notices');
    }
};

