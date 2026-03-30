<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void {
        if (Schema::hasTable('push_device_tokens')) {
            // In case older envs already have it; be idempotent
            return;
        }

        Schema::create('push_device_tokens', function (Blueprint $t) {
            $t->id();
            $t->string('tenant_id')->index();
            $t->unsignedBigInteger('user_id')->index();
            $t->string('device_id')->index();
            $t->string('device_type')->index();
            $t->string('token', 512);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();

            $t->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }
    
    public function down(): void { 
        Schema::dropIfExists('push_device_tokens'); 
    }
};
