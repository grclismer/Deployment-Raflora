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
        Schema::table('quotations', function (Blueprint $table) {
            $table->unsignedInteger('version')->nullable();
            $table->decimal('raw_materials_sum', 10, 2)->nullable();
            $table->decimal('multiplier', 8, 2)->nullable();
            $table->string('labor_method')->nullable();
            $table->decimal('labor_rate', 10, 2)->nullable();
            $table->decimal('labor_amount', 10, 2)->nullable();
            $table->decimal('final_quoted_price', 10, 2)->nullable();
            $table->json('items_snapshot')->nullable();
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->decimal('ai_recommended_price', 12, 2)->nullable();
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->string('labor_method')->nullable();
            $table->decimal('labor_rate', 10, 2)->nullable();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('quotation_id')->nullable()->constrained('quotations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['quotation_id']);
            $table->dropColumn('quotation_id');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropColumn(['labor_method', 'labor_rate']);
        });

        Schema::table('booking_items', function (Blueprint $table) {
            $table->dropColumn('ai_recommended_price');
        });

        Schema::table('quotations', function (Blueprint $table) {
            $table->dropColumn([
                'version',
                'raw_materials_sum',
                'multiplier',
                'labor_method',
                'labor_rate',
                'labor_amount',
                'final_quoted_price',
                'items_snapshot',
            ]);
        });
    }
};
