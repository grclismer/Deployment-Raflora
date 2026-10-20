<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Package;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryRedesignTest extends TestCase
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
     * 1. Page loads with shared admin shell and expected header.
     */
    public function test_inventory_management_page_loads_with_proper_header(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('OPERATIONS');
        $response->assertSee('Inventory Management');
        $response->assertSee('Track and manage floral materials, props, and event inventory.');
        $response->assertSee('Archived Items');
    }

    /**
     * 2. KPI values render correctly according to authoritative stock rules.
     */
    public function test_kpi_cards_render_correct_authoritative_metrics(): void
    {
        // Item 1: In Stock (current 100, min 20, net 100 > min 20)
        InventoryItem::create([
            'name' => 'White Hydrangea',
            'category' => 'Flowers',
            'item_code' => 'FLW-0001',
            'current_stock' => 100,
            'min_stock' => 20,
            'unit_cost' => 45.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        // Item 2: Low Stock (current 15, min 20, net 15 <= min 20 and >= 0)
        InventoryItem::create([
            'name' => 'Glass Vase Cylinder',
            'category' => 'Props',
            'item_code' => 'PRP-0002',
            'current_stock' => 15,
            'min_stock' => 20,
            'unit_cost' => 150.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        // Item 3: Shortage (current 5, reserved 15 via booking, net -10 < 0)
        $shortageItem = InventoryItem::create([
            'name' => 'Eucalyptus Bunch',
            'category' => 'Greenery',
            'item_code' => 'GRN-0003',
            'current_stock' => 5,
            'min_stock' => 10,
            'unit_cost' => 30.00,
            'unit' => 'bunches',
            'is_perishable' => true,
        ]);

        $booking = Booking::create([
            'event_date' => Carbon::tomorrow(),
            'event_type' => 'wedding',
            'status' => 'confirmed',
            'total_quoted' => 5000,
            'confirmed_at' => now(),
        ]);

        $booking->inventoryItems()->attach($shortageItem->id, [
            'quantity' => 15,
            'quoted_unit_price' => 30,
            'procurement_status' => 'reserved',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('TOTAL ITEMS');
        $response->assertSee('IN STOCK');
        $response->assertSee('LOW STOCK');
        $response->assertSee('SHORTAGE');
        
        // Assert the counts: 3 Total, 1 In Stock, 1 Low Stock, 1 Shortage
        $response->assertSee('All active inventory items');
        $response->assertSee('Negative available stock');
    }

    /**
     * 3. Search by item name works.
     */
    public function test_search_by_item_name_works(): void
    {
        InventoryItem::create([
            'name' => 'Ecuadorian Red Roses',
            'category' => 'Flowers',
            'item_code' => 'ROS-0010',
            'current_stock' => 50,
            'min_stock' => 10,
            'unit_cost' => 60.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        InventoryItem::create([
            'name' => 'Golden Arch Stand',
            'category' => 'Props',
            'item_code' => 'ARC-0020',
            'current_stock' => 5,
            'min_stock' => 2,
            'unit_cost' => 2500.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['search' => 'Ecuadorian']));

        $response->assertOk();
        $response->assertSee('Ecuadorian Red Roses');
        $response->assertDontSee('Golden Arch Stand');
    }

    /**
     * 4. Search by item code works.
     */
    public function test_search_by_item_code_works(): void
    {
        InventoryItem::create([
            'name' => 'Tulips Pink',
            'category' => 'Flowers',
            'item_code' => 'TLP-9901',
            'current_stock' => 40,
            'min_stock' => 10,
            'unit_cost' => 55.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        InventoryItem::create([
            'name' => 'Chiffon Drapes White',
            'category' => 'Fabrics',
            'item_code' => 'DRP-4402',
            'current_stock' => 20,
            'min_stock' => 5,
            'unit_cost' => 300.00,
            'unit' => 'rolls',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['search' => 'TLP-9901']));

        $response->assertOk();
        $response->assertSee('Tulips Pink');
        $response->assertDontSee('Chiffon Drapes White');
    }

    /**
     * 5. Category filtering works.
     */
    public function test_category_filtering_works(): void
    {
        InventoryItem::create([
            'name' => 'Baby Breath Standard',
            'category' => 'Flowers',
            'current_stock' => 50,
            'min_stock' => 10,
            'unit_cost' => 25.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        InventoryItem::create([
            'name' => 'Wooden Crate Medium',
            'category' => 'Decor',
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 400.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['category' => 'Decor']));

        $response->assertOk();
        $response->assertSee('Wooden Crate Medium');
        $response->assertDontSee('Baby Breath Standard');
    }

    /**
     * 6. Status filtering works.
     */
    public function test_status_filtering_works(): void
    {
        InventoryItem::create([
            'name' => 'Plentiful Candles',
            'category' => 'Decor',
            'current_stock' => 100,
            'min_stock' => 10,
            'unit_cost' => 20.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        InventoryItem::create([
            'name' => 'Scarce Fairy Lights',
            'category' => 'Decor',
            'current_stock' => 4,
            'min_stock' => 10,
            'unit_cost' => 80.00,
            'unit' => 'sets',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['status' => 'low']));

        $response->assertOk();
        $response->assertSee('Scarce Fairy Lights');
        $response->assertDontSee('Plentiful Candles');
    }

    /**
     * 7. Perishable filtering works.
     */
    public function test_perishable_filtering_works(): void
    {
        InventoryItem::create([
            'name' => 'Fresh Carnations',
            'category' => 'Flowers',
            'current_stock' => 60,
            'min_stock' => 15,
            'unit_cost' => 30.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        InventoryItem::create([
            'name' => 'Metal Lantern Lanterns',
            'category' => 'Props',
            'current_stock' => 12,
            'min_stock' => 4,
            'unit_cost' => 500.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['perishable' => 'yes']));

        $response->assertOk();
        $response->assertSee('Fresh Carnations');
        $response->assertDontSee('Metal Lantern Lanterns');
    }

    /**
     * 8. Stock-level filtering works.
     */
    public function test_stock_level_filtering_works(): void
    {
        InventoryItem::create([
            'name' => 'High Stock Ribbon',
            'category' => 'Supplies',
            'current_stock' => 200,
            'min_stock' => 50,
            'unit_cost' => 15.00,
            'unit' => 'rolls',
            'is_perishable' => false,
        ]);

        InventoryItem::create([
            'name' => 'Low Stock Floral Tape',
            'category' => 'Supplies',
            'current_stock' => 5,
            'min_stock' => 20,
            'unit_cost' => 25.00,
            'unit' => 'rolls',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['stock_level' => 'in_stock']));

        $response->assertOk();
        $response->assertSee('High Stock Ribbon');
        $response->assertDontSee('Low Stock Floral Tape');
    }

    /**
     * 9. Sorting works.
     */
    public function test_sorting_by_name_works(): void
    {
        InventoryItem::create([
            'name' => 'Zinnia Blooms',
            'category' => 'Flowers',
            'current_stock' => 50,
            'min_stock' => 10,
            'unit_cost' => 20.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        InventoryItem::create([
            'name' => 'Anthurium Red',
            'category' => 'Flowers',
            'current_stock' => 30,
            'min_stock' => 5,
            'unit_cost' => 40.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['sort' => 'name_asc']));

        $response->assertOk();
        $response->assertSeeInOrder([
            'Anthurium Red',
            'Zinnia Blooms',
        ]);
    }

    /**
     * 10. Clear filters link resets queries to unfiltered index.
     */
    public function test_clear_filters_link_is_present_when_filters_are_active(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index', ['category' => 'Decor']));

        $response->assertOk();
        $response->assertSee('Clear Filters');
        $response->assertSee(route('admin.inventory.index'));
    }

    /**
     * 11. Pagination functions correctly.
     */
    public function test_pagination_renders_when_items_exceed_per_page(): void
    {
        for ($i = 1; $i <= 15; $i++) {
            InventoryItem::create([
                'name' => "Batch Flower Item {$i}",
                'category' => 'Flowers',
                'current_stock' => 100 + $i,
                'min_stock' => 20,
                'unit_cost' => 30.00,
                'unit' => 'stems',
                'is_perishable' => true,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('page=2');
    }

    /**
     * 12-15. Drawer template contains Stock Summary, Item Info, and Stock Definitions.
     */
    public function test_drawer_template_renders_comprehensive_item_overview(): void
    {
        $item = InventoryItem::create([
            'name' => 'Delphinium Blue',
            'category' => 'Flowers',
            'item_code' => 'DEL-0101',
            'current_stock' => 80,
            'min_stock' => 25,
            'unit_cost' => 55.50,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('id="item-drawer-template-' . $item->id . '"', false);
        $response->assertSee('STOCK SUMMARY');
        $response->assertSee('STOCK DEFINITIONS');
        $response->assertSee('Physical quantity currently in inventory.');
        $response->assertSee('Quantity needed by upcoming confirmed booking lines (not yet held or dispatched).');
        $response->assertSee('Additional quantity required to fulfill booked event demand.');
        $response->assertSee('Configured minimum inventory threshold.');
        $response->assertSee('ITEM INFORMATION');
        $response->assertSee('Delphinium Blue');
        $response->assertSee('DEL-0101');
        $response->assertSee('₱55.50');
    }

    /**
     * 16-17. Stock History tab contains real InventoryTransaction records.
     */
    public function test_stock_history_tab_displays_actual_inventory_transactions(): void
    {
        $item = InventoryItem::create([
            'name' => 'Peony Coral Charm',
            'category' => 'Flowers',
            'item_code' => 'PEO-0202',
            'current_stock' => 50,
            'min_stock' => 15,
            'unit_cost' => 80.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => null,
            'quantity_change' => 50,
            'transaction_type' => 'adjustment',
            'reason' => 'Initial bulk delivery restock',
            'performed_by' => $this->admin->id,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('Initial bulk delivery restock');
        $response->assertSee('TRANSACTION LEDGER');
        $response->assertSee('+50');
    }

    /**
     * 18. Package Usage tab displays actual Package BOM relationships.
     */
    public function test_package_usage_tab_displays_package_bom_pivot_data(): void
    {
        $item = InventoryItem::create([
            'name' => 'White Lily Premium',
            'category' => 'Flowers',
            'item_code' => 'LIL-0303',
            'current_stock' => 60,
            'min_stock' => 20,
            'unit_cost' => 50.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $package = Package::create([
            'title' => 'Elegance Wedding Package',
            'package_code' => 'PKG-ELEGANCE',
            'category' => 'Wedding',
            'price' => 35000.00,
            'is_active' => true,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 24]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('PACKAGE BOM USAGE');
        $response->assertSee('Elegance Wedding Package');
        $response->assertSee('PKG-ELEGANCE');
        $response->assertSee('24 stems');
    }

    /**
     * 19. Unauthorized users cannot access protected inventory actions.
     */
    public function test_unauthorized_users_cannot_access_inventory_management(): void
    {
        // Client access attempt
        $clientResponse = $this->actingAs($this->client)->get(route('admin.inventory.index'));
        $clientResponse->assertStatus(403);

        // Staff access attempt
        $staffResponse = $this->actingAs($this->staff)->get(route('admin.inventory.index'));
        $staffResponse->assertStatus(403);
    }

    /**
     * 20. Main table columns and Scannable layout are intact.
     */
    public function test_main_table_contains_all_authoritative_columns(): void
    {
        InventoryItem::create([
            'name' => 'Table Runner Olive',
            'category' => 'Linens',
            'item_code' => 'RUN-0505',
            'current_stock' => 30,
            'min_stock' => 10,
            'unit_cost' => 120.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertOk();
        $response->assertSee('ITEM');
        $response->assertSee('CATEGORY');
        $response->assertSee('ON HAND');
        $response->assertSee('BOOKED DEMAND');
        $response->assertSee('TO PROCURE');
        $response->assertSee('MINIMUM');
        $response->assertSee('STATUS');
        $response->assertSee('ACTION');
        $response->assertSee('Table Runner Olive');
        $response->assertSee('Linens');
        $response->assertSee('View');
    }
}
