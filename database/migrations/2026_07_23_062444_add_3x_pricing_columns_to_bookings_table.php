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
        Schema::table('bookings', function (Blueprint $table) {
            $table->decimal('raw_materials_sum', 10, 2)->default(0)->after('status');
            $table->decimal('multiplier', 8, 2)->default(3.0)->after('raw_materials_sum');
            $table->decimal('final_quoted_price', 10, 2)->default(0)->after('multiplier');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['raw_materials_sum', 'multiplier', 'final_quoted_price']);
        });
    }
};
