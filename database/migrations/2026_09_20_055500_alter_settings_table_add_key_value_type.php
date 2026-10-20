<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->string('key')->nullable()->unique()->after('id');
            $table->json('value')->nullable()->after('key');
            $table->string('type')->default('string')->after('value');
        });

        // Seed initial downpayment percentage safely
        \App\Models\Setting::setSetting('downpayment_percentage', 50.0, 'float');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['key', 'value', 'type']);
        });
    }
};
