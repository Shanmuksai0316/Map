<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('import_jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id'); // UUID from tenants table
            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->string('kind');
            $table->string('status');
            $table->string('filename');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('inserted_rows')->default(0);
            $table->unsignedInteger('updated_rows')->default(0);
            $table->jsonb('meta')->nullable();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind']);
        });

        Schema::create('import_errors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('import_job_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('code');
            $table->string('message');
            $table->jsonb('row_snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_jobs');
    }
};
