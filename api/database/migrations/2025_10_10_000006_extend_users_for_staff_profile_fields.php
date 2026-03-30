<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('phone');
            }
            if (! Schema::hasColumn('users', 'profile_photo_path')) {
                $table->string('profile_photo_path')->nullable()->after('email');
            }
            if (! Schema::hasColumn('users', 'gender')) {
                $table->string('gender', 16)->nullable()->after('profile_photo_path');
            }
            if (! Schema::hasColumn('users', 'dob')) {
                $table->date('dob')->nullable()->after('gender');
            }
            if (! Schema::hasColumn('users', 'id_type')) {
                $table->string('id_type', 32)->nullable()->after('dob');
            }
            if (! Schema::hasColumn('users', 'id_number')) {
                $table->string('id_number', 64)->nullable()->after('id_type');
            }
            if (! Schema::hasColumn('users', 'address')) {
                $table->jsonb('address')->nullable()->after('id_number');
            }
            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 16)->default('Active')->after('address');
            }
            if (! Schema::hasColumn('users', 'date_of_joining')) {
                $table->date('date_of_joining')->nullable()->after('status');
            }
            if (! Schema::hasColumn('users', 'emergency_contact_name')) {
                $table->string('emergency_contact_name')->nullable()->after('date_of_joining');
            }
            if (! Schema::hasColumn('users', 'emergency_contact_phone')) {
                $table->string('emergency_contact_phone', 20)->nullable()->after('emergency_contact_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'username','profile_photo_path','gender','dob','id_type','id_number',
                'address','status','date_of_joining','emergency_contact_name','emergency_contact_phone',
            ] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
