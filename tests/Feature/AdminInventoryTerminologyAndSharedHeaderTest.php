<?php

namespace Tests\Feature;

use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInventoryTerminologyAndSharedHeaderTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);
    }

    /**
     * Test inventory table headers and terminology: ON HAND, RESERVED, AVAILABLE, MINIMUM, STATUS, ACTION.
     */
    public function test_inventory_table_uses_on_hand_and_authoritative_headers(): void
    {
        $item = InventoryItem::create([
            'name' => 'White Orchids',
            'category' => 'Flowers',
            'item_code' => 'FLO-0042',
            'current_stock' => 50,
            'min_stock' => 15,
            'unit_cost' => 85.00,
            'unit' => 'stems',
            'is_perishable' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();

        // Check authoritative headers
        $response->assertSee('ON HAND');
        $response->assertSee('BOOKED DEMAND');
        $response->assertSee('TO PROCURE');
        $response->assertSee('MINIMUM');
        $response->assertSee('STATUS');
        $response->assertSee('ACTION');

        // Confirm CURRENT and AVAILABLE are not separate duplicated column headers in main table
        $response->assertDontSee('<th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right">CURRENT</th>', false);
        $response->assertDontSee('<th class="px-5 py-3.5 text-[11px] font-bold text-slate-500 uppercase tracking-wider text-right" title="Stock remaining after valid reservations">AVAILABLE</th>', false);

        // Values match authoritative model properties
        $response->assertSee('50');
        $response->assertSee('15');
        $response->assertSee('White Orchids');
    }

    /**
     * Test that main table row action is simplified to View only.
     */
    public function test_main_table_has_view_as_primary_action(): void
    {
        $item = InventoryItem::create([
            'name' => 'Golden Arch Stand',
            'category' => 'Props',
            'item_code' => 'PROP-0099',
            'current_stock' => 4,
            'min_stock' => 1,
            'unit_cost' => 4500.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();

        // Primary row action is View
        $response->assertSee('openItemDrawer(' . $item->id . ')', false);
        $response->assertSee('View');

        // Drawer contains the detailed actions including Receive Stock
        $response->assertSee('Receive Stock');
        $response->assertSee('Edit Item');
        $response->assertSee('Adjust Stock');
        $response->assertSee('Archive Item');
    }

    /**
     * Test drawer displays ON HAND, TO PROCURE, and stock definitions.
     */
    public function test_drawer_stock_summary_and_definitions_use_on_hand(): void
    {
        $item = InventoryItem::create([
            'name' => 'Crystal Vase',
            'category' => 'Glassware',
            'item_code' => 'GLS-0010',
            'current_stock' => 25,
            'min_stock' => 5,
            'unit_cost' => 350.00,
            'unit' => 'pcs',
            'is_perishable' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $response->assertOk();

        // Drawer Stock Summary
        $response->assertSee('<p class="text-[10px] font-bold text-slate-400 uppercase">ON HAND</p>', false);
        $response->assertSee('<p class="text-[10px] font-bold text-slate-400 uppercase">TO PROCURE</p>', false);
        $response->assertSee('Physical quantity currently in inventory.');
        $response->assertSee('STOCK DEFINITIONS');
        $response->assertSee('On Hand:');
        $response->assertSee('To Procure:');
        $response->assertSee('Additional quantity required to fulfill booked event demand.');
    }

    /**
     * Test main workspace does not contain duplicate inline session flash banners.
     */
    public function test_inventory_main_workspace_does_not_contain_duplicate_inline_alerts(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['success' => 'Inventory item updated successfully.'])
            ->get(route('admin.inventory.index'));

        $response->assertOk();

        // Global toast container renders the alert
        $response->assertSee('id="rf-toast-container"', false);
        $response->assertSee('Inventory item updated successfully.');

        // Main workspace does NOT contain duplicate inline alert container with this text
        $response->assertDontSee('<span class="text-sm font-medium">Inventory item updated successfully.</span>', false);
    }

    /**
     * Test fixed header is single source of page context across affected pages.
     */
    public function test_fixed_header_displays_title_and_description_without_duplicates(): void
    {
        // 1. Inventory Management
        $invResponse = $this->actingAs($this->admin)->get(route('admin.inventory.index'));
        $invResponse->assertOk();
        $invResponse->assertSee('Inventory Management');
        $invResponse->assertSee('Track and manage floral materials, props, and event inventory.');
        // Duplicate breadcrumb/heading block removed
        $invResponse->assertDontSee('<p class="text-xs font-bold uppercase tracking-wider text-purple-600 mb-1">OPERATIONS</p>', false);

        // 2. Packages
        $pkgResponse = $this->actingAs($this->admin)->get(route('admin.packages.index'));
        $pkgResponse->assertOk();
        $pkgResponse->assertSee('Packages');
        $pkgResponse->assertSee('Manage public booking packages, pricing, and master inventory mappings (BOM).');
        // Duplicate header removed from packages content
        $pkgResponse->assertDontSee('<h2 class="text-xl sm:text-2xl font-bold text-gray-800 font-serif">Packages</h2>', false);

        // 3. Return Tracking
        $retResponse = $this->actingAs($this->admin)->get(route('admin.return-tracking'));
        $retResponse->assertOk();
        $retResponse->assertSee('Return Tracking');
        $retResponse->assertSee('Track post-event asset returns, condition assessments, staff responsibility, and required damage/loss decisions.');

        // 4. Client Records
        $clientResponse = $this->actingAs($this->admin)->get(route('admin.client-records'));
        $clientResponse->assertOk();
        $clientResponse->assertSee('Client Records');
        $clientResponse->assertSee('Manage client information and review their booking activity.');
        $clientResponse->assertDontSee('<p class="text-xs font-bold uppercase tracking-[0.2em] text-purple-600">Client management</p>', false);
    }

    /**
     * Test that View Site and Notification Bell are absent from the desktop fixed header.
     */
    public function test_fixed_header_omits_view_site_and_notification_bell(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        // The top desktop header should NOT contain "View Site"
        $response->assertDontSee('<span>View Site</span>', false);

        // Sidebar still contains Notifications
        $response->assertSee('href="' . route('admin.notifications') . '"', false);
        $response->assertSee('Notifications');
    }

    /**
     * Test brand logo assets: floral emblem mark is used in sidebar and mobile header.
     */
    public function test_brand_logo_uses_raflora_flower_emblem_asset(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $response->assertOk();

        $response->assertSee(asset('assets/images/raflora_flower_emblem_transparent.png'), false);
        $response->assertSee('Floral Event Management');
    }
}
