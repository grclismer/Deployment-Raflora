<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->string('item_code')->nullable()->unique()->after('id');
            $table->string('image_path')->nullable()->after('item_code');
        });

        // Backfill item codes
        $items = DB::table('inventory_items')->whereNull('item_code')->get();
        foreach ($items as $item) {
            $prefix = strtoupper(substr($item->category ?? 'INV', 0, 3));
            $code = $prefix . '-' . str_pad($item->id, 4, '0', STR_PAD_LEFT);
            // Ensure unique just in case
            $i = 1;
            while (DB::table('inventory_items')->where('item_code', $code)->where('id', '!=', $item->id)->exists()) {
                $code = $prefix . '-' . str_pad($item->id, 4, '0', STR_PAD_LEFT) . '-' . $i++;
            }
            DB::table('inventory_items')->where('id', $item->id)->update(['item_code' => $code]);
        }
    }

    public function down(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->dropColumn(['item_code', 'image_path']);
        });
    }
};
