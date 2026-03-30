<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('tenant_id')->nullable(); // UUID from tenants table, nullable for Super Admin
            $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
            $table->string('phone')->unique();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('kind');
            $table->boolean('archived')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'kind']);
        });
        Schema::create('user_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->timestamps();

            $table->index(['user_id', 'role']);
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
