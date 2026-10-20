<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ReservedStockIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected InventoryItem $item;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->item = InventoryItem::create([
            'name' => 'Test Item',
            'category' => 'prop',
            'current_stock' => 100,
            'unit_cost' => 50,
            'unit' => 'piece',
            'min_stock' => 10,
        ]);
        
        $this->booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 1000,
        ]);
    }
    
    protected function recordTransaction(string $type, float $change)
    {
        return InventoryTransaction::create([
            'inventory_item_id' => $this->item->id,
            'booking_id' => $this->booking->id,
            'transaction_type' => $type,
            'quantity_change' => $change,
            'performed_by' => User::factory()->create()->id,
            'reason' => 'Test transaction',
        ]);
    }

    public function test_valid_reservation_and_release_calculations()
    {
        $this->recordTransaction('booking_lock', 20); // lock 20
        $this->assertEquals(20, $this->item->fresh()->reserved_stock);
        $this->assertEquals(80, $this->item->fresh()->net_available);
        
        $this->recordTransaction('booking_release', 5); // release 5
        $this->assertEquals(15, $this->item->fresh()->reserved_stock);
        $this->assertEquals(85, $this->item->fresh()->net_available);
    }
    
    public function test_valid_dispatch_and_correction_calculations()
    {
        $this->recordTransaction('booking_lock', 30);
        
        // Dispatch is negative change
        $this->recordTransaction('dispatch', -10);
        
        $this->assertEquals(20, $this->item->fresh()->reserved_stock);
        
        // Dispatch correction (e.g., returned or adjusted) could be positive or negative
        // If we dispatch another 5:
        $this->recordTransaction('dispatch_correction', -5);
        
        $this->assertEquals(15, $this->item->fresh()->reserved_stock);
    }

    public function test_negative_net_reservation_detection_and_safe_handling()
    {
        // 1. Create inconsistent state
        $this->recordTransaction('booking_lock', 10);
        $this->recordTransaction('dispatch', -15); // Dispatched more than locked!
        
        // 2. Expect Log::error to be called
        Log::shouldReceive('error')
            ->atLeast()->once()
            ->withArgs(function ($message) {
                return str_contains($message, 'Inconsistent ledger state') &&
                       str_contains($message, 'Net reservation cannot be negative');
            });

        // 3. Check fallback behavior
        // Net reservation would be: 10 (lock) - 0 (release) - 15 (abs dispatch) = -5
        // Safe fallback logic now correctly floors at 0.0.
        
        $item = $this->item->fresh();
        
        $this->assertEquals(0, $item->reserved_stock);
        
        // Since we merely mocked the transaction array and bypassed the DispatchService,
        // physical current_stock was not decremented by 15. Thus, current_stock remains 100.
        // Available = 100 - 0 = 100.
        $this->assertEquals(100, $item->net_available);
    }
    
    public function test_zero_lock_with_releases_forces_restriction()
    {
        // 1. Create inconsistent state: no lock, but release exists
        $this->recordTransaction('booking_release', 10);
        
        Log::shouldReceive('error')->atLeast()->once();

        // Net reservation: 0 (lock) - 10 (release) - 0 (dispatch) = -10
        // Correct fallback: max(0, -10) = 0.
        
        $item = $this->item->fresh();
        
        $this->assertEquals(0, $item->reserved_stock);
        $this->assertEquals(100, $item->net_available);
    }
}
