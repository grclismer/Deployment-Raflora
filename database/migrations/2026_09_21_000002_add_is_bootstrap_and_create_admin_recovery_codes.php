<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Add is_bootstrap flag to users table
        if (! Schema::hasColumn('users', 'is_bootstrap')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('is_bootstrap')->default(false)->after('role');
            });
        }

        // 2. Create admin_recovery_codes table
        if (! Schema::hasTable('admin_recovery_codes')) {
            Schema::create('admin_recovery_codes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('code_hash', 64);
                $table->boolean('is_used')->default(false);
                $table->timestamp('used_at')->nullable();
                $table->unsignedInteger('attempts')->default(0);
                $table->timestamp('generated_at')->useCurrent();
                $table->timestamps();

                $table->index(['user_id', 'is_used']);
            });
        }

        // 3. Update existing default seeded admin in local/production database if present
        DB::table('users')
            ->where('email', 'admin@raflora.com')
            ->where('role', 'admin')
            ->update([
                'is_bootstrap' => true,
                'email_verified_at' => null,
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_recovery_codes');

        if (Schema::hasColumn('users', 'is_bootstrap')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('is_bootstrap');
            });
        }
    }
};
