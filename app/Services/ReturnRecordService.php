<?php

namespace App\Services;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\InventoryTransaction;
use App\Models\ReturnItem;
use Illuminate\Support\Facades\DB;

/**
 * The one way a booking's return record is created: from what was actually dispatched (the
 * inventory ledger), once per booking. Admin booking actions, Admin return tracking and the
 * Staff event page all use it, so they cannot build different item lists or a second record.
 */
class ReturnRecordService
{
    /**
     * Net dispatched quantity per reusable (non-perishable) inventory item, keyed by item id.
     *
     * @return array<int, float>
     */
    public function dispatchedQuantities(Booking $booking): array
    {
        return InventoryTransaction::with('inventoryItem')
            ->where('booking_id', $booking->id)
            ->whereIn('transaction_type', ['dispatch', 'dispatch_correction'])
            ->get()
            ->filter(fn ($tx) => $tx->inventoryItem && !$tx->inventoryItem->is_perishable)
            ->groupBy('inventory_item_id')
            ->map(fn ($txs) => abs((float) $txs->sum('quantity_change')))
            ->filter(fn ($qty) => $qty > 0)
            ->all();
    }

    /**
     * The booking's return record: the existing one, or a new one listing every dispatched
     * reusable item. The booking row is locked first, so concurrent requests create it once.
     * With nothing dispatched it returns null, unless $allowEmpty (an Admin closing a
     * zero-hardware audit).
     */
    public function openFor(Booking $booking, bool $allowEmpty = false): ?AssetReturn
    {
        return DB::transaction(function () use ($booking, $allowEmpty): ?AssetReturn {
            Booking::whereKey($booking->id)->lockForUpdate()->first();

            $existing = AssetReturn::where('booking_id', $booking->id)->latest()->orderByDesc('id')->first();
            if ($existing) {
                return $existing;
            }

            // Delivery orders are sold: nothing comes back unless the order was cancelled after dispatch.
            if ($booking->isDelivery() && $booking->status !== 'cancelled') {
                return null;
            }

            $dispatched = $this->dispatchedQuantities($booking);
            if ($dispatched === [] && !$allowEmpty) {
                return null;
            }

            $return = AssetReturn::create([
                'booking_id' => $booking->id,
                'assigned_staff_id' => $booking->staff_id,
                'status' => 'Pending',
                'total_damage_charge' => 0,
                'approval_status' => 'not_required',
            ]);

            foreach (array_keys($dispatched) as $inventoryItemId) {
                ReturnItem::create([
                    'return_id' => $return->id,
                    'inventory_item_id' => $inventoryItemId,
                    'quantity_returned' => 0,
                    'quantity_good' => 0,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'condition' => 'pending',
                    'final_amount' => 0,
                    'damage_charge' => 0,
                ]);
            }

            return $return;
        });
    }
}
