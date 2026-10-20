<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Safe additive backfill to confirm predefined package fallback inclusions
        // that were mistakenly created without confirmed_at = now()
        
        $items = \App\Models\BookingItem::whereNull('confirmed_at')
            ->where('is_ai_suggested', false)
            ->whereNull('inventory_item_id')
            ->where('notes', 'LIKE', 'From Package%')
            ->whereHas('booking', function ($query) {
                $query->whereNotNull('package_id');
            })
            ->get();

        foreach ($items as $item) {
            $item->confirmed_at = $item->created_at;
            // use DB facade or save without timestamps to avoid updating updated_at if possible
            // but typical eloquent save is fine here to track the modification
            $item->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Not cleanly reversible without a dedicated flag, but we can attempt to reverse 
        // by matching the same strict criteria where confirmed_at == created_at
        
        $items = \App\Models\BookingItem::whereNotNull('confirmed_at')
            ->whereColumn('confirmed_at', 'created_at')
            ->where('is_ai_suggested', false)
            ->whereNull('inventory_item_id')
            ->where('notes', 'LIKE', 'From Package%')
            ->whereHas('booking', function ($query) {
                $query->whereNotNull('package_id');
            })
            ->get();

        foreach ($items as $item) {
            $item->confirmed_at = null;
            $item->save();
        }
    }
};
