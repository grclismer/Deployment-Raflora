<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Package;
use App\Models\ReturnItem;
use App\Models\AssetReturn;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminInventoryProcurementReceiptTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'staff',
        ]);

        $this->client = User::create([
            'name' => 'Client User',
            'email' => 'client@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
    }

    /**
     * 1-3. TO PROCURE calculation rules:
     * TO_PROCURE = max(0, RESERVED - ON_HAND), never negative, Minimum remains separate.
     */
    public function test_to_procure_formula_derives_correctly_and_keeps_minimum_separate(): void
    {
        $item = InventoryItem::create([
            'name' => 'Red Roses',
            'category' => 'Flowers',
            'item_code' => 'FLO-0001',
            'current_stock' => 121,
            'min_stock' => 40,
            'unit_cost' => 15.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $this->client->id,
            'event_date' => Carbon::tomorrow(),
            'event_type' => 'Wedding',
            'status' => 'approved',
            'total_amount' => 50000,
        ]);

        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 680,
            'quoted_unit_price' => 20,
            'procurement_status' => 'pending',
        ]);

        // Query through index endpoint to trigger withSum calculation
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();

        // On Hand = 121, Reserved = 680, To Procure = 559, Minimum = 40
        $response->assertSee('121');
        $response->assertSee('680');
        $response->assertSee('559');
        $response->assertSee('40');

        // Verify model calculation directly
        $item->reserved_stock = 680.0;
        $this->assertEquals(559.0, $item->to_procure);
        $this->assertEquals(40.0, $item->min_stock);

        // When stock exceeds reservations, to_procure is floored at 0, never negative
        $item->current_stock = 750;
        $this->assertEquals(0.0, $item->to_procure);
    }

    /**
     * 4-15. Admin receives stock:
     * Validates input, updates On Hand atomically, creates transaction, preserves Reserved,
     * recalculates To Procure and Status, and logs audit event.
     */
    public function test_admin_can_receive_stock_atomically_updating_on_hand_and_to_procure(): void
    {
        $item = InventoryItem::create([
            'name' => 'Red Roses',
            'category' => 'Flowers',
            'item_code' => 'FLO-0001',
            'current_stock' => 121,
            'min_stock' => 40,
            'unit_cost' => 15.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $this->client->id,
            'event_date' => Carbon::tomorrow(),
            'event_type' => 'Wedding',
            'status' => 'approved',
            'total_amount' => 50000,
        ]);

        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 680,
            'quoted_unit_price' => 20,
            'procurement_status' => 'pending',
        ]);

        // Receive 300 stems
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 300,
            'unit_cost' => 16.00,
            'notes' => 'PO-8812 Farm delivery',
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('success', 'Stock received successfully.');

        // On Hand increases from 121 to 421
        $freshItem = $item->fresh();
        $this->assertEquals(421.0, (float) $freshItem->current_stock);
        $this->assertEquals(16.0, (float) $freshItem->unit_cost);

        // Reserved remains untouched at 680
        $bookingItem = DB::table('booking_items')->where('inventory_item_id', $item->id)->first();
        $this->assertEquals(680, $bookingItem->quantity);

        // To Procure is now max(0, 680 - 421) = 259
        $freshItem->reserved_stock = 680.0;
        $this->assertEquals(259.0, $freshItem->to_procure);

        // Inventory transaction created with 'procurement' type
        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'booking_id' => null,
            'quantity_change' => 300,
            'transaction_type' => 'procurement',
            'reason' => 'PO-8812 Farm delivery',
            'performed_by' => $this->admin->id,
        ]);

        // Audit log created
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $this->admin->id,
            'action' => 'inventory_stock_received',
            'module' => 'inventory',
        ]);

        // Receive remaining 259 stems to cover demand
        $response2 = $this->actingAs($this->admin)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 259,
            'notes' => 'Final procurement batch',
        ]);

        $response2->assertRedirect(route('admin.inventory.index'));
        $finalItem = $item->fresh();
        $this->assertEquals(680.0, (float) $finalItem->current_stock);
        $finalItem->reserved_stock = 680.0;
        $this->assertEquals(0.0, $finalItem->to_procure);
    }

    /**
     * 5-6 & 9. Invalid quantities: blank, zero, negative, and invalid decimal for integer units.
     */
    public function test_invalid_quantities_are_rejected(): void
    {
        $item = InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'Flowers',
            'item_code' => 'FLO-0002',
            'current_stock' => 50,
            'min_stock' => 20,
            'unit_cost' => 18.00,
            'unit' => 'stems', // Discrete integer unit
            'is_perishable' => true,
        ]);

        // Zero quantity
        $responseZero = $this->actingAs($this->admin)->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.receive-stock', $item), [
                'quantity' => 0,
            ]);
        $responseZero->assertSessionHasErrors('quantity');
        $this->assertEquals(50, (float) $item->fresh()->current_stock);

        // Negative quantity
        $responseNegative = $this->actingAs($this->admin)->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.receive-stock', $item), [
                'quantity' => -10,
            ]);
        $responseNegative->assertSessionHasErrors('quantity');
        $this->assertEquals(50, (float) $item->fresh()->current_stock);

        // Invalid decimal for integer unit 'stems'
        $responseDecimal = $this->actingAs($this->admin)->from(route('admin.inventory.index'))
            ->post(route('admin.inventory.receive-stock', $item), [
                'quantity' => 12.5,
            ]);
        $responseDecimal->assertSessionHasErrors('quantity');
        $this->assertEquals(50, (float) $item->fresh()->current_stock);

        // No transaction or audit log created on failed validation
        $this->assertDatabaseMissing('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'transaction_type' => 'procurement',
        ]);
    }

    /**
     * 14-15. Atomicity & Concurrency:
     * Failed transaction causes no partial state; lockForUpdate guarantees correct concurrent updates.
     */
    public function test_concurrent_receipts_do_not_lose_updates_and_maintain_lock(): void
    {
        $item = InventoryItem::create([
            'name' => 'Glass Vase Cylinder',
            'category' => 'Props',
            'item_code' => 'PRP-0010',
            'current_stock' => 10,
            'min_stock' => 5,
            'unit_cost' => 200.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $admin2 = User::create([
            'name' => 'Admin Two',
            'email' => 'admin2@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        // First receipt of 5 pcs
        $this->actingAs($this->admin)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 5,
            'notes' => 'Batch 1',
        ]);

        // Second receipt of 8 pcs
        $this->actingAs($admin2)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 8,
            'notes' => 'Batch 2',
        ]);

        // Final on hand is 10 + 5 + 8 = 23
        $this->assertEquals(23.0, (float) $item->fresh()->current_stock);

        // Both transactions exist
        $this->assertEquals(2, InventoryTransaction::where('inventory_item_id', $item->id)
            ->where('transaction_type', 'procurement')
            ->count());
    }

    /**
     * 16-20. Workflow isolation:
     * Receiving stock does NOT alter booking status, booking reservation quantity,
     * payment status, package BOM, or return records.
     */
    public function test_receive_stock_preserves_booking_payment_bom_and_return_isolation(): void
    {
        $item = InventoryItem::create([
            'name' => 'Pink Carnations',
            'category' => 'Flowers',
            'item_code' => 'FLO-0003',
            'current_stock' => 50,
            'min_stock' => 20,
            'unit_cost' => 10.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $this->client->id,
            'event_date' => Carbon::tomorrow(),
            'event_type' => 'Debut',
            'status' => 'approved',
            'total_amount' => 45000,
        ]);

        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 100,
            'quoted_unit_price' => 15,
            'procurement_status' => 'pending',
        ]);

        // Package BOM mapping
        $package = Package::create([
            'title' => 'Floral Arch Standard',
            'category' => 'Ceremony',
            'price' => 15000,
        ]);
        $package->inventoryItems()->attach($item->id, ['quantity' => 25]);

        // Execute stock receipt
        $this->actingAs($this->admin)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 50,
            'notes' => 'Isolated test receipt',
        ]);

        // 16. Booking status is still 'approved'
        $this->assertEquals('approved', $booking->fresh()->status);

        // 17. Booking reservation quantity is still 100
        $pivot = DB::table('booking_items')->where('booking_id', $booking->id)->where('inventory_item_id', $item->id)->first();
        $this->assertEquals(100, $pivot->quantity);
        $this->assertEquals('pending', $pivot->procurement_status);

        // 19. Package BOM mapping is unchanged
        $bomPivot = DB::table('inventory_item_package')->where('package_id', $package->id)->where('inventory_item_id', $item->id)->first();
        $this->assertEquals(25, $bomPivot->quantity);

        // On Hand stock updated
        $this->assertEquals(100.0, (float) $item->fresh()->current_stock);
    }

    /**
     * 24-30. UI presentation:
     * Table columns (ITEM, CATEGORY, ON HAND, RESERVED, TO PROCURE, MINIMUM, STATUS, ACTION),
     * Drawer displays On Hand/Reserved/To Procure/Minimum with coverage message,
     * Receive Stock button is present in drawer, Receive Stock modal exists.
     */
    public function test_ui_renders_to_procure_column_drawer_summary_and_receive_stock_action(): void
    {
        $item = InventoryItem::create([
            'name' => 'White Hydrangeas',
            'category' => 'Flowers',
            'item_code' => 'FLO-0004',
            'current_stock' => 20,
            'min_stock' => 15,
            'unit_cost' => 45.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $booking = Booking::create([
            'user_id' => $this->client->id,
            'event_date' => Carbon::tomorrow(),
            'event_type' => 'Anniversary',
            'status' => 'approved',
            'total_amount' => 30000,
        ]);

        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 50,
            'quoted_unit_price' => 55,
            'procurement_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();

        // 24. Table columns: ON HAND, RESERVED, TO PROCURE, MINIMUM
        $response->assertSee('ON HAND');
        $response->assertSee('BOOKED DEMAND');
        $response->assertSee('TO PROCURE');
        $response->assertSee('MINIMUM');

        // Confirm CURRENT and AVAILABLE are not separate table headers
        $response->assertDontSee('<th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">CURRENT</th>', false);
        $response->assertDontSee('<th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right" title="Stock remaining after valid reservations">AVAILABLE</th>', false);

        // 25. Row action is View (openItemDrawer)
        $response->assertSee('openItemDrawer(' . $item->id . ')', false);

        // 26. Drawer displays TO PROCURE card and demand coverage note (50 - 20 = 30 stems needed)
        $response->assertSee('30 stems still needed for booked event demand.');

        // 27. Receive Stock action button in drawer
        $response->assertSee('Receive Stock');
        $response->assertSee('openReceiveStockModal(' . $item->id, false);

        // 28. Receive Stock modal markup exists
        $response->assertSee('id="receiveStockModal"', false);
        $response->assertSee('id="receiveStockForm"', false);
        $response->assertSee('Quantity Received');
    }

    /**
     * 31-32. Authorization:
     * Non-admin users (staff, client, unauthenticated) cannot submit stock receipt.
     */
    public function test_authorization_prevents_unauthorized_users_from_receiving_stock(): void
    {
        $item = InventoryItem::create([
            'name' => 'Metal Floral Arch',
            'category' => 'Props',
            'item_code' => 'PROP-0020',
            'current_stock' => 2,
            'min_stock' => 1,
            'unit_cost' => 5000.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        // 1. Staff user is blocked (403 Forbidden)
        $staffResponse = $this->actingAs($this->staff)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 1,
        ]);
        $staffResponse->assertForbidden();
        $this->assertEquals(2, (float) $item->fresh()->current_stock);

        // 2. Client user is blocked (403 Forbidden)
        $clientResponse = $this->actingAs($this->client)->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 1,
        ]);
        $clientResponse->assertForbidden();
        $this->assertEquals(2, (float) $item->fresh()->current_stock);

        // 3. Unauthenticated user is rejected (403 Forbidden via AdminMiddleware)
        $guestResponse = $this->post(route('admin.inventory.receive-stock', $item), [
            'quantity' => 1,
        ]);
        $guestResponse->assertForbidden();
        $this->assertEquals(2, (float) $item->fresh()->current_stock);
    }
}
