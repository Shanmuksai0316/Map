<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, boolean, integer, json
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('key');
        });
        
        // Insert default settings
        DB::table('system_settings')->insert([
            [
                'key' => 'feature_flags.onboarding_v2',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable the simplified multi-step onboarding wizard',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature_flags.super_admin_staff_mgmt',
                'value' => 'false',
                'type' => 'boolean',
                'description' => 'Allow Super Admin to manage staff across tenants',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature_flags.sms_events',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable MSG91 SMS notifications',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'feature_flags.super_admin_reports',
                'value' => 'true',
                'type' => 'boolean',
                'description' => 'Enable Super Admin reports feature',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_subscription_plan',
                'value' => 'Basic',
                'type' => 'string',
                'description' => 'Default subscription plan for new tenants',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'default_subscription_duration',
                'value' => '12',
                'type' => 'integer',
                'description' => 'Default subscription duration in months',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

