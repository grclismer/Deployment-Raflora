<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->unique()->after('last_name');
            }
            if (!Schema::hasColumn('users', 'address')) {
                $table->string('address')->nullable()->after('password');
            }
            if (!Schema::hasColumn('users', 'mobile_number')) {
                $table->string('mobile_number')->nullable()->after('address');
            }
            if (!Schema::hasColumn('users', 'role')) {
                $table->enum('role', ['client', 'admin', 'staff'])->default('client')->after('mobile_number');
            }
            if (!Schema::hasColumn('users', 'profile_image')) {
                $table->string('profile_image')->nullable()->after('role');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'first_name', 'last_name', 'username', 'address',
                'mobile_number', 'role', 'profile_image'
            ]);
        });
    }
};
