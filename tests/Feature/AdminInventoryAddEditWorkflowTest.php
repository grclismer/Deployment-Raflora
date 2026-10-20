<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryStock;
use App\Models\InventoryTransaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryAddEditWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Manager',
            'email' => 'admin-inventory-wf@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->staff = User::create([
            'name' => 'Staff Assistant',
            'email' => 'staff-inventory-wf@example.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
        ]);

        $this->client = User::create([
            'name' => 'Client Bride',
            'email' => 'client-inventory-wf@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
    }

    /**
     * Test 1: Admin can create a perishable inventory item.
     */
    public function test_admin_can_create_perishable_inventory_item(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Fresh Pink Rose',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 45.50,
            'initial_quantity' => 200,
            'reorder_level' => 30,
            'item_type' => 'perishable',
            'received_date' => '2026-10-05',
            'usable_life_value' => 5,
            'usable_life_unit' => 'days',
            'status' => 'active',
            'description' => 'Premium imported Ecuadorian pink roses.',
            'supplier_name' => 'Blooming Fields PH',
            'supplier_contact_person' => 'Ana Reyes',
            'supplier_contact_number' => '09171234567',
            'storage_location' => 'Chiller 1',
            'tags' => 'rose,pink,wedding,fresh',
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('success');

        // Verify InventoryItem
        $item = InventoryItem::where('name', 'Fresh Pink Rose')->first();
        $this->assertNotNull($item);
        $this->assertTrue((bool) $item->is_perishable);
        $this->assertSame('active', $item->status);
        $this->assertSame(200.0, (float) $item->current_stock);
        $this->assertSame(30.0, (float) $item->min_stock);
        $this->assertSame(45.50, (float) $item->unit_cost);
        $this->assertNotEmpty($item->item_code);
        $this->assertStringStartsWith('FRE', $item->item_code);

        // Verify InventoryStock record
        $stock = InventoryStock::where('inventory_item_id', $item->id)->first();
        $this->assertNotNull($stock);
        $this->assertSame('2026-10-05', $stock->received_date->toDateString());
        $this->assertSame(200.0, (float) $stock->quantity_received);
        $this->assertSame(200.0, (float) $stock->quantity_remaining);
        $this->assertSame(5, $stock->usable_life_value);
        $this->assertSame('days', $stock->usable_life_unit);
        $this->assertSame('2026-10-10', $stock->usable_until->toDateString());

        // Verify initial transaction
        $tx = InventoryTransaction::where('inventory_item_id', $item->id)->first();
        $this->assertNotNull($tx);
        $this->assertSame('procurement', $tx->transaction_type);
        $this->assertSame(200.0, (float) $tx->quantity_change);
        $this->assertSame($stock->id, $tx->inventory_stock_id);
    }

    /**
     * Test 2: Admin can create a non-perishable inventory item.
     */
    public function test_admin_can_create_non_perishable_inventory_item(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Brass Hexagon Arch Stand',
            'category' => 'Equipment',
            'unit' => 'pcs',
            'unit_cost' => 3500.00,
            'initial_quantity' => 4,
            'reorder_level' => 1,
            'item_type' => 'non_perishable',
            'received_date' => '2026-01-05',
            'usable_life_value' => 3,
            'usable_life_unit' => 'years',
            'status' => 'active',
        ]);

        $response->assertRedirect(route('admin.inventory.index'));

        $item = InventoryItem::where('name', 'Brass Hexagon Arch Stand')->first();
        $this->assertNotNull($item);
        $this->assertFalse((bool) $item->is_perishable);
        $this->assertSame(4.0, (float) $item->current_stock);
        $this->assertSame(3, $item->usable_life_value);
        $this->assertSame('years', $item->usable_life_unit);

        $stock = InventoryStock::where('inventory_item_id', $item->id)->first();
        $this->assertNotNull($stock);
        $this->assertSame('2026-01-05', $stock->received_date->toDateString());
        $this->assertSame('2029-01-05', $stock->usable_until->toDateString());
    }

    /**
     * Test 3: Optional fields can be omitted.
     */
    public function test_optional_fields_can_be_omitted(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Basic Floral Foam',
            'category' => 'Supplies',
            'unit' => 'block',
            'unit_cost' => 25.00,
            'received_date' => '2026-10-01',
            'usable_life_value' => 6,
            'usable_life_unit' => 'months',
            'item_type' => 'perishable',
            // Omitted: description, initial_quantity, reorder_level, supplier_name, supplier_contact_person, supplier_contact_number, storage_location, tags, image, substitute_ids
        ]);

        $response->assertRedirect(route('admin.inventory.index'));

        $item = InventoryItem::where('name', 'Basic Floral Foam')->first();
        $this->assertNotNull($item);
        $this->assertNull($item->description);
        $this->assertNull($item->supplier_name);
        $this->assertNull($item->storage_location);
        $this->assertSame(0.0, (float) $item->current_stock);
    }

    /**
     * Test 4: Item code cannot be manually overridden.
     */
    public function test_item_code_cannot_be_manually_overridden(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Spoofed Code Orchid',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 80.00,
            'received_date' => '2026-10-01',
            'usable_life_value' => 7,
            'usable_life_unit' => 'days',
            'item_type' => 'perishable',
            'item_code' => 'CUSTOM-HACK-999', // Client attempt to override
        ]);

        $response->assertRedirect(route('admin.inventory.index'));

        $item = InventoryItem::where('name', 'Spoofed Orchid')->orWhere('name', 'Spoofed Code Orchid')->first();
        $this->assertNotNull($item);
        $this->assertNotEquals('CUSTOM-HACK-999', $item->item_code);
        $this->assertStringStartsWith('FRE-', $item->item_code);
    }

    /**
     * Test 5: Edit does not arbitrarily overwrite current stock.
     */
    public function test_edit_does_not_arbitrarily_overwrite_current_stock(): void
    {
        $item = InventoryItem::create([
            'name' => 'White Hydrangea',
            'item_code' => 'FRE-0042',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 150,
            'min_stock' => 20,
            'unit_cost' => 60.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.inventory.update', $item), [
            'name' => 'White Hydrangea (Premium Dutch)',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 75.00,
            'reorder_level' => 25,
            'item_type' => 'perishable',
            'status' => 'active',
            'current_stock' => 9999, // Attempted direct stock overwrite!
        ]);

        $response->assertRedirect(route('admin.inventory.index'));

        $item->refresh();
        $this->assertSame('White Hydrangea (Premium Dutch)', $item->name);
        $this->assertSame(75.00, (float) $item->unit_cost);
        $this->assertSame(25.0, (float) $item->min_stock);
        // CRITICAL CHECK: Physical stock remains untouched!
        $this->assertSame(150.0, (float) $item->current_stock);
        $this->assertSame('FRE-0042', $item->item_code);
    }

    /**
     * Test 6: Stock adjustment remains transactional.
     */
    public function test_stock_adjustment_remains_transactional(): void
    {
        $item = InventoryItem::create([
            'name' => 'Glass Cylinder Vase 12"',
            'item_code' => 'DEC-0010',
            'category' => 'Decor',
            'unit' => 'pcs',
            'is_perishable' => false,
            'current_stock' => 50,
            'min_stock' => 10,
            'unit_cost' => 150.00,
            'status' => 'active',
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.adjust-stock', $item), [
            'new_stock' => 45,
            'reason' => 'Physical cycle count found 5 broken vases',
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('success');

        $item->refresh();
        $this->assertSame(45.0, (float) $item->current_stock);

        $this->assertDatabaseHas('inventory_transactions', [
            'inventory_item_id' => $item->id,
            'transaction_type' => 'adjustment',
            'quantity_change' => -5.0,
            'reason' => 'Physical cycle count found 5 broken vases',
        ]);
    }

    /**
     * Test 7: Invalid usable life is rejected.
     */
    public function test_invalid_usable_life_is_rejected(): void
    {
        // Zero usable life
        $resZero = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Bad Usable Life Item',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 10,
            'received_date' => '2026-10-01',
            'usable_life_value' => 0,
            'usable_life_unit' => 'days',
            'item_type' => 'perishable',
        ]);
        $resZero->assertSessionHasErrors('usable_life_value');

        // Negative usable life
        $resNeg = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Bad Usable Life Item 2',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 10,
            'received_date' => '2026-10-01',
            'usable_life_value' => -5,
            'usable_life_unit' => 'days',
            'item_type' => 'perishable',
        ]);
        $resNeg->assertSessionHasErrors('usable_life_value');

        // Invalid unit
        $resUnit = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Bad Unit Item',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 10,
            'received_date' => '2026-10-01',
            'usable_life_value' => 5,
            'usable_life_unit' => 'centuries',
            'item_type' => 'perishable',
        ]);
        $resUnit->assertSessionHasErrors('usable_life_unit');

        // Invalid date
        $resDate = $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Bad Date Item',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 10,
            'received_date' => 'not-a-date',
            'usable_life_value' => 5,
            'usable_life_unit' => 'days',
            'item_type' => 'perishable',
        ]);
        $resDate->assertSessionHasErrors('received_date');
    }

    /**
     * Test 8: Usable until is server-calculated.
     */
    public function test_usable_until_is_server_calculated(): void
    {
        $this->actingAs($this->admin)->post(route('admin.inventory.store'), [
            'name' => 'Baby Breath White',
            'category' => 'Fresh Flowers',
            'unit' => 'bunch',
            'unit_cost' => 120.00,
            'initial_quantity' => 10,
            'received_date' => '2026-10-05',
            'usable_life_value' => 10,
            'usable_life_unit' => 'days',
            'item_type' => 'perishable',
            'usable_until' => '2099-12-31', // Client spoof attempt!
        ]);

        $item = InventoryItem::where('name', 'Baby Breath White')->first();
        $this->assertNotNull($item);

        $stock = InventoryStock::where('inventory_item_id', $item->id)->first();
        $this->assertNotNull($stock);
        // Correct calculation: 2026-10-05 + 10 days = 2026-10-15
        $this->assertSame('2026-10-15', $stock->usable_until->toDateString());
    }

    /**
     * Test 9: Active and Inactive status persists and filters.
     */
    public function test_active_and_inactive_status_persists_and_filters(): void
    {
        $activeItem = InventoryItem::create([
            'name' => 'Active Item Red Rose',
            'item_code' => 'FRE-0081',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 50,
            'min_stock' => 10,
            'unit_cost' => 50,
            'status' => 'active',
        ]);

        $inactiveItem = InventoryItem::create([
            'name' => 'Discontinued Carnation',
            'item_code' => 'FRE-0082',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 0,
            'min_stock' => 0,
            'unit_cost' => 30,
            'status' => 'inactive',
        ]);

        // Filter active
        $responseActive = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['status' => 'active']));
        $responseActive->assertStatus(200);
        $responseActive->assertSee('Active Item Red Rose');
        $responseActive->assertDontSee('Discontinued Carnation');

        // Filter inactive
        $responseInactive = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['status' => 'inactive']));
        $responseInactive->assertStatus(200);
        $responseInactive->assertSee('Discontinued Carnation');
        $responseInactive->assertDontSee('Active Item Red Rose');

        // Update status of activeItem to inactive
        $this->actingAs($this->admin)->put(route('admin.inventory.update', $activeItem), [
            'name' => 'Active Item Red Rose',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 50,
            'item_type' => 'perishable',
            'status' => 'inactive',
        ]);

        $activeItem->refresh();
        $this->assertSame('inactive', $activeItem->status);
    }

    /**
     * Test 10: Substitutes still work.
     */
    public function test_substitutes_still_work(): void
    {
        $mainFlower = InventoryItem::create([
            'name' => 'Peony Coral Charm',
            'item_code' => 'FRE-0091',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 20,
            'min_stock' => 5,
            'unit_cost' => 150,
            'status' => 'active',
        ]);

        $subFlower1 = InventoryItem::create([
            'name' => 'Garden Rose David Austin',
            'item_code' => 'FRE-0092',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 40,
            'min_stock' => 10,
            'unit_cost' => 120,
            'status' => 'active',
        ]);

        $subFlower2 = InventoryItem::create([
            'name' => 'Ranunculus Salmon',
            'item_code' => 'FRE-0093',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 30,
            'min_stock' => 5,
            'unit_cost' => 95,
            'status' => 'active',
        ]);

        // Sync substitutes via update
        $this->actingAs($this->admin)->put(route('admin.inventory.update', $mainFlower), [
            'name' => 'Peony Coral Charm',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 150,
            'item_type' => 'perishable',
            'substitute_ids' => [$subFlower1->id, $subFlower2->id],
        ]);

        $mainFlower->refresh();
        $this->assertCount(2, $mainFlower->substitutes);
        $this->assertTrue($mainFlower->substitutes->contains($subFlower1->id));
        $this->assertTrue($mainFlower->substitutes->contains($subFlower2->id));

        // Clear substitutes
        $this->actingAs($this->admin)->put(route('admin.inventory.update', $mainFlower), [
            'name' => 'Peony Coral Charm',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'unit_cost' => 150,
            'item_type' => 'perishable',
            'substitute_ids' => [],
        ]);

        $mainFlower->refresh();
        $this->assertCount(0, $mainFlower->substitutes);
    }

    /**
     * Test 11: Non-admin users cannot mutate inventory.
     */
    public function test_non_admin_users_cannot_mutate_inventory(): void
    {
        $item = InventoryItem::create([
            'name' => 'Protected Item',
            'item_code' => 'PRT-0001',
            'category' => 'Props',
            'unit' => 'pcs',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 50,
            'status' => 'active',
        ]);

        // Unauthenticated guest
        $this->post(route('admin.inventory.store'), ['name' => 'Hacked Item'])
            ->assertRedirect(route('login'));

        // Client
        $this->actingAs($this->client)
            ->post(route('admin.inventory.store'), ['name' => 'Client Item'])
            ->assertForbidden();

        $this->actingAs($this->client)
            ->put(route('admin.inventory.update', $item), ['name' => 'Client Edit'])
            ->assertForbidden();

        $this->actingAs($this->client)
            ->post(route('admin.inventory.adjust-stock', $item), ['new_stock' => 999, 'reason' => 'Hack'])
            ->assertForbidden();

        // Staff
        $this->actingAs($this->staff)
            ->post(route('admin.inventory.store'), ['name' => 'Staff Item'])
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->put(route('admin.inventory.update', $item), ['name' => 'Staff Edit'])
            ->assertForbidden();

        $this->actingAs($this->staff)
            ->post(route('admin.inventory.adjust-stock', $item), ['new_stock' => 999, 'reason' => 'Staff adjust'])
            ->assertForbidden();
    }

    /**
     * Test 12: Perishable vs Non-perishable return eligibility distinction remains intact.
     */
    public function test_perishable_and_non_perishable_return_eligibility_distinction(): void
    {
        $perishableFlower = InventoryItem::create([
            'name' => 'Pink Carnation Fresh',
            'item_code' => 'FRE-0101',
            'category' => 'Fresh Flowers',
            'unit' => 'stem',
            'is_perishable' => true,
            'current_stock' => 100,
            'min_stock' => 10,
            'unit_cost' => 30,
            'status' => 'active',
        ]);

        $nonPerishableStand = InventoryItem::create([
            'name' => 'Gold Geometric Centerpiece Stand',
            'item_code' => 'PRP-0102',
            'category' => 'Props',
            'unit' => 'pcs',
            'is_perishable' => false,
            'current_stock' => 20,
            'min_stock' => 5,
            'unit_cost' => 500,
            'status' => 'active',
        ]);

        $booking = \App\Models\Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Grand Pavilion',
            'status' => 'confirmed',
            'total_quoted' => 10000,
        ]);

        $booking->inventoryItems()->attach([
            $perishableFlower->id => ['quantity' => 50, 'quoted_unit_price' => 40, 'procurement_status' => 'pending'],
            $nonPerishableStand->id => ['quantity' => 10, 'quoted_unit_price' => 600, 'procurement_status' => 'pending'],
        ]);

        // Query returnable / reusable items (per project rule: is_perishable == false)
        $returnEligibleItems = $booking->inventoryItems()
            ->where('is_perishable', false)
            ->get();

        $this->assertCount(1, $returnEligibleItems);
        $this->assertSame($nonPerishableStand->id, $returnEligibleItems->first()->id);

        $perishableItems = $booking->inventoryItems()
            ->where('is_perishable', true)
            ->get();

        $this->assertCount(1, $perishableItems);
        $this->assertSame($perishableFlower->id, $perishableItems->first()->id);
    }
}
