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
        Schema::table('return_items', function (Blueprint $table) {
            $table->decimal('quantity_good', 12, 2)->default(0)->after('quantity_returned');
            $table->decimal('quantity_damaged', 12, 2)->default(0)->after('quantity_good');
            $table->decimal('quantity_lost', 12, 2)->default(0)->after('quantity_damaged');
        });

        // Safe forward backfill for existing return_items records
        DB::table('return_items')->where('condition', 'good')->update([
            'quantity_good' => DB::raw('quantity_returned'),
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
        ]);

        DB::table('return_items')->where('condition', 'damaged')->update([
            'quantity_good' => 0,
            'quantity_damaged' => DB::raw('quantity_returned'),
            'quantity_lost' => 0,
        ]);

        DB::table('return_items')->where('condition', 'lost')->update([
            'quantity_good' => 0,
            'quantity_damaged' => 0,
            'quantity_lost' => DB::raw('quantity_returned'),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('return_items', function (Blueprint $table) {
            $table->dropColumn(['quantity_good', 'quantity_damaged', 'quantity_lost']);
        });
    }
};
