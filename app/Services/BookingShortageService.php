<?php

namespace App\Services;

use App\Models\Booking;

/**
 * A booking's material lines checked against stock: what is still needed after dispatched units,
 * what is free after other bookings' holds, and the shortfall. Used by Admin Notifications (the
 * booking shortage alert) and by the booking shortage CSV and printable report built from it.
 * Read-only: booking lines are linked to inventory only by Admin's explicit actions.
 */
class BookingShortageService
{
    /**
     * Every line with a quantity, shortages first.
     *
     * @return list<array<string, mixed>>
     */
    public function itemsFor(Booking $booking): array
    {
        $shortageItems = [];
        // Units already dispatched to this booking, per item, still to be set against its lines.
        $dispatchedLeft = [];
        foreach ($booking->bookingItems as $bookingItem) {
            $required = (float) ($bookingItem->quantity ?? 0);
            if ($required <= 0) {
                continue;
            }

            $item = $bookingItem->inventoryItem;
            if ($item) {
                // Dispatched units have left current_stock and are at the event, so only the
                // rest of the line is still needed from stock.
                $dispatchedLeft[$item->id] ??= $item->dispatchedForBooking($booking->id);
                $alreadyDispatched = min($required, $dispatchedLeft[$item->id]);
                $dispatchedLeft[$item->id] -= $alreadyDispatched;
                $required -= $alreadyDispatched;
            }
            if (!$item) {
                $shortageItems[] = [
                    'inventory_item_id' => null,
                    'booking_item_id' => $bookingItem->id,
                    'name' => $bookingItem->item_name ?? 'Unmatched booking item',
                    'quoted_unit_price' => (float) ($bookingItem->quoted_unit_price ?? 0),
                    'required' => $required,
                    'current_stock' => 0,
                    'reserved_stock' => 0,
                    'net_available' => 0,
                    'shortfall' => $required,
                    'is_missing' => true,
                    'is_shortage' => true,
                ];
                continue;
            }

            // Stock held for other bookings: everything held minus what this booking itself
            // holds (InventoryItem::reservedForBooking), never minus what it merely needs.
            $reservedByOtherBookings = max(0.0, (float) $item->reserved_stock - $item->reservedForBooking($booking->id));
            $available = max(0.0, (float) $item->current_stock - $reservedByOtherBookings);
            $isShortage = $available < $required;

            $shortageItems[] = [
                'inventory_item_id' => $item->id,
                'name' => $item->name,
                'required' => $required,
                'current_stock' => (float) $item->current_stock,
                'reserved_stock' => $reservedByOtherBookings,
                'net_available' => $available,
                'shortfall' => max(0.0, $required - $available),
                'is_missing' => false,
                'is_shortage' => $isShortage,
            ];
        }

        if (!empty($shortageItems)) {
            usort($shortageItems, function (array $left, array $right): int {
                return ((int) ($right['is_shortage'] ?? false)) <=> ((int) ($left['is_shortage'] ?? false));
            });
        }

        return $shortageItems;
    }

    /**
     * The canvassing / procurement working list: shortage lines only (fully available materials
     * are left out). A line not in inventory is "To canvass" (find a supplier); a catalog item
     * short on stock is "To procure". Priority is High when none is free or the event is within 7 days.
     *
     * @return list<array{name: string, category: string, required: float, available: float, shortage: float, unit: string, priority: string, supplier: string, status: string}>
     */
    public function reportRowsFor(Booking $booking): array
    {
        $booking->loadMissing('bookingItems.inventoryItem');
        $inventoryById = $booking->bookingItems->pluck('inventoryItem')->filter()->keyBy('id');
        $eventSoon = $booking->event_date && $booking->event_date->copy()->endOfDay()->lte(now()->addDays(7));

        $rows = [];
        foreach ($this->itemsFor($booking) as $item) {
            if (!($item['is_shortage'] ?? false)) {
                continue;
            }

            $inventoryItem = $item['inventory_item_id'] ? $inventoryById->get($item['inventory_item_id']) : null;
            $available = (float) $item['net_available'];
            $supplier = $inventoryItem
                ? trim(implode(' · ', array_filter([$inventoryItem->supplier_name, $inventoryItem->supplier_contact_person, $inventoryItem->supplier_contact_number])))
                : '';

            $rows[] = [
                'name' => (string) $item['name'],
                'category' => $inventoryItem?->category ?: 'Not in inventory',
                'required' => (float) $item['required'],
                'available' => $available,
                'shortage' => (float) $item['shortfall'],
                'unit' => $inventoryItem?->unit ?: '',
                'priority' => ($available <= 0 || $eventSoon) ? 'High' : 'Normal',
                'supplier' => $supplier,
                'status' => $item['is_missing'] ? 'To canvass' : 'To procure',
            ];
        }

        return $rows;
    }
}
