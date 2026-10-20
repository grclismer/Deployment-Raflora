<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use App\Models\AdminAlert;
use App\Models\InventoryTransaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CheckTieredInventoryShortages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:check-tiered-shortages';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Perform tiered inventory checks (weekly at 4 weeks, daily during final week) and alert for shortages.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Tiered Inventory Shortage Check...');

        $today = now()->startOfDay();

        // 1. Find eligible bookings
        $eligibleStatuses = ['downpayment_received', 'confirmed'];
        
        $bookings = Booking::whereIn('status', $eligibleStatuses)
            ->whereNotNull('event_date')
            ->with(['bookingItems.inventoryItem'])
            ->get();

        $this->info("Found {$bookings->count()} eligible bookings to evaluate.");

        $alertsCreated = 0;

        foreach ($bookings as $booking) {
            $eventDate = Carbon::parse($booking->event_date)->startOfDay();
            $daysUntilEvent = (int) $today->diffInDays($eventDate, false);
            // - exactly 28, 21, 14 days before (weekly)
            // - between 1 and 7 days inclusive before (daily)
            // Event day (0 days) is intentionally excluded per step 13A specification until business decision is made.
            $isEligibleDay = $daysUntilEvent === 28 || 
                             $daysUntilEvent === 21 || 
                             $daysUntilEvent === 14 || 
                             ($daysUntilEvent >= 1 && $daysUntilEvent <= 7);

            if (!$isEligibleDay) {
                continue;
            }

            $this->line("Booking #{$booking->id} is {$daysUntilEvent} days away. Checking shortages...");

            // 3. Evaluate shortages
            foreach ($booking->bookingItems as $bItem) {
                // Skip if item is unconfirmed or has no linked inventory
                // We use confirmed_at check or simply rely on the fact that if it's confirmed booking, items should be confirmed.
                // But let's check for inventoryItem linkage and positive quantity.
                if (!$bItem->inventoryItem || (float) $bItem->quantity <= 0) {
                    continue;
                }
                
                // Do not alert on cancelled items
                if ($bItem->status === 'cancelled') {
                    continue;
                }

                $inv = $bItem->inventoryItem;
                $required = (float) $bItem->quantity;

                // Existing authoritative calculation logic
                $locked = (float) InventoryTransaction::query()
                    ->where('booking_id', $booking->id)
                    ->where('inventory_item_id', $inv->id)
                    ->where('transaction_type', 'booking_lock')
                    ->where('quantity_change', '<', 0)
                    ->sum('quantity_change');
                    
                $released = (float) InventoryTransaction::query()
                    ->where('booking_id', $booking->id)
                    ->where('inventory_item_id', $inv->id)
                    ->where('transaction_type', 'booking_release')
                    ->where('quantity_change', '>', 0)
                    ->sum('quantity_change');

                $netLocked = abs($locked) - $released;
                $remaining = max(0.0, $required - $netLocked);
                
                if ($remaining <= 0) {
                    continue; // fully locked/reserved
                }

                $available = (float) $inv->net_available;
                $hasShortage = $available < $remaining;

                if ($hasShortage) {
                    $shortfall = $remaining - $available;
                    
                    // 4. Create or reuse AdminAlert
                    $alert = AdminAlert::firstOrCreate([
                        'type' => 'inventory_shortage',
                        'booking_id' => $booking->id,
                        'inventory_item_id' => $inv->id,
                        'is_read' => false,
                    ], [
                        'title' => 'Stock Shortage: Booking #' . $booking->id,
                        'message' => sprintf(
                            'Booking #%d requires %s units of "%s", but only %s are available after reservations. Shortfall: %s units.',
                            $booking->id,
                            number_format($required, 0),
                            $inv->name,
                            number_format($available, 0),
                            number_format($shortfall, 0)
                        ),
                    ]);

                    if ($alert->wasRecentlyCreated) {
                        $alertsCreated++;
                        $this->info("Created shortage alert for Booking #{$booking->id} -> Item: {$inv->name}");
                    }
                }
            }
        }

        $this->info("Tiered check complete. Created {$alertsCreated} new unread alerts.");
    }
}
