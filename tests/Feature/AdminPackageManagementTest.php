<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\InventoryItem;

class AdminPackageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_negative_price_rejected()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test Package',
            'category' => 'Test',
            'price' => -100,
        ]);
        $response->assertSessionHasErrors('price');
        $this->assertDatabaseCount('packages', 0);
    }

    public function test_zero_price_behavior_verified()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test Package',
            'category' => 'Test',
            'price' => 0,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('packages', ['price' => 0]);
    }

    public function test_positive_decimal_accepted()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test Package',
            'category' => 'Test',
            'price' => 99.99,
        ]);
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('packages', ['price' => 99.99]);
    }

    public function test_missing_category_rejected()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test Package',
            'price' => 100,
        ]);
        $response->assertSessionHasErrors('category');
    }

    public function test_missing_package_name_rejected()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'category' => 'Test',
            'price' => 100,
        ]);
        $response->assertSessionHasErrors('title');
    }

    public function test_generated_automatically_unique_and_readable()
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => '12 Premium Red Roses Square Box',
            'category' => 'Test',
            'price' => 100,
            'package_code' => 'HACKER-CODE' // attempt to override
        ]);
        $response->assertSessionHasNoErrors();
        
        $package = Package::first();
        $this->assertNotNull($package->package_code);
        $this->assertNotEquals('HACKER-CODE', $package->package_code);
        $this->assertEquals('12PRRSB', $package->package_code);
        
        // Test uniqueness
        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => '12 Premium Red Roses Square Box',
            'category' => 'Test',
            'price' => 100,
        ]);
        
        $packages = Package::all();
        $this->assertCount(2, $packages);
        $this->assertEquals('12PRRSB', $packages[0]->package_code);
        $this->assertEquals('12PRRSB1', $packages[1]->package_code);
    }

    public function test_package_name_edit_does_not_regenerate_code()
    {
        $package = Package::create(['title' => 'Old Title', 'category' => 'Cat', 'price' => 100]);
        $originalCode = $package->package_code;

        $response = $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'New Title',
            'category' => 'Cat',
            'price' => 100,
        ]);

        $package->refresh();
        $this->assertEquals('New Title', $package->title);
        $this->assertEquals($originalCode, $package->package_code);
    }

    public function test_bom_mapping_stored_and_rejected()
    {
        $item = InventoryItem::create(['name' => 'Test Item', 'category' => 'Cat', 'unit' => 'pcs']);
        $stemItem = InventoryItem::create(['name' => 'Rose', 'category' => 'Flowers', 'unit' => 'stems']);
        $liquidItem = InventoryItem::create(['name' => 'Water', 'category' => 'Materials', 'unit' => 'liters']);

        // Valid integer quantity for pcs
        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [$item->id => 5, $stemItem->id => 1]
        ]);
        $package = Package::where('title', 'Test BOM')->first();
        $this->assertEquals(5, $package->inventoryItems()->where('inventory_item_id', $item->id)->first()->pivot->quantity);
        $this->assertEquals(1, $package->inventoryItems()->where('inventory_item_id', $stemItem->id)->first()->pivot->quantity);

        // 0.56 stems rejected
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Dec',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [$stemItem->id => 0.56]
        ]);
        $response->assertSessionHasErrors('inventory_items.'.$stemItem->id);

        // Decimal quantity allowed for genuinely measurable units (not in integer units list)
        $responseDec = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Measurable',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [$liquidItem->id => 1.5]
        ]);
        $responseDec->assertSessionHasNoErrors();
        $packageDec = Package::where('title', 'Test BOM Measurable')->first();
        $this->assertEquals(1.5, $packageDec->inventoryItems()->first()->pivot->quantity);

        // Negative quantity rejected
        $responseNeg = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Neg',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [$item->id => -5]
        ]);
        $responseNeg->assertSessionHasErrors('inventory_items.'.$item->id);

        // Zero rejected
        $responseZero = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Zero Qty',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [$item->id => 0]
        ]);
        $responseZero->assertSessionHasErrors('inventory_items.'.$item->id);

        // Blank quantity rejected
        $responseBlank = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Blank Qty',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [$item->id => null]
        ]);
        // numeric rule handles this
        $responseBlank->assertSessionHasErrors('inventory_items.'.$item->id);

        // Invalid inventory item ID rejected
        $responseInvalid = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Invalid ID',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => [99999 => 1]
        ]);
        $responseInvalid->assertSessionHasErrors('inventory_items.99999');

        // Unselected is not synced
        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Test BOM Empty',
            'category' => 'Cat',
            'price' => 100,
            'inventory_items' => []
        ]);
        $packageEmpty = Package::where('title', 'Test BOM Empty')->first();
        $this->assertCount(0, $packageEmpty->inventoryItems);
    }

    public function test_update_no_change_and_real_change()
    {
        $package = Package::create([
            'title' => 'Title',
            'category' => 'Cat',
            'price' => 100,
            'description' => 'Desc',
            'is_active' => true,
        ]);

        // No change
        $response = $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'Title',
            'category' => 'Cat',
            'price' => 100,
            'description' => 'Desc',
            'is_active' => true,
        ]);
        
        $response->assertSessionHas('info', 'No changes were made to this package.');
        $response->assertSessionMissing('success');

        // Real change
        $response = $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'New Title',
            'category' => 'Cat',
            'price' => 100,
            'description' => 'Desc',
            'is_active' => true,
        ]);
        
        $response->assertSessionHas('success', 'Package updated successfully.');
        $response->assertSessionMissing('info');
    }

    public function test_package_creation_and_editing_does_not_alter_inventory_stock()
    {
        $item1 = InventoryItem::create([
            'name' => 'Red Roses',
            'item_code' => 'ROSE-001',
            'category' => 'Fresh Flowers',
            'unit' => 'stems',
            'current_stock' => 200,
            'unit_cost' => 15.00,
        ]);
        $item2 = InventoryItem::create([
            'name' => 'Floral Foam',
            'item_code' => 'FOAM-001',
            'category' => 'Supplies',
            'unit' => 'blocks',
            'current_stock' => 50,
            'unit_cost' => 25.00,
        ]);

        // 1. Create Package with BOM
        $responseCreate = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Romantic Bundle',
            'category' => 'Anniversary',
            'price' => 2500,
            'inventory_items' => [
                $item1->id => 50,
                $item2->id => 5,
            ],
        ]);
        $responseCreate->assertSessionHasNoErrors();

        // Verify stock is untouched
        $this->assertEquals(200, $item1->fresh()->current_stock);
        $this->assertEquals(50, $item2->fresh()->current_stock);

        $package = Package::where('title', 'Romantic Bundle')->first();
        $this->assertNotNull($package);
        $this->assertEquals(50, (float) $package->inventoryItems()->where('inventory_item_id', $item1->id)->first()->pivot->quantity);
        $this->assertEquals(5, (float) $package->inventoryItems()->where('inventory_item_id', $item2->id)->first()->pivot->quantity);

        // 2. Edit Package BOM quantities
        $responseEdit = $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'Romantic Bundle Deluxe',
            'category' => 'Anniversary',
            'price' => 3500,
            'inventory_items' => [
                $item1->id => 100, // increased requirement
                $item2->id => 10,
            ],
        ]);
        $responseEdit->assertSessionHas('success');

        // Verify stock remains untouched
        $this->assertEquals(200, $item1->fresh()->current_stock);
        $this->assertEquals(50, $item2->fresh()->current_stock);
        $this->assertEquals(100, (float) $package->fresh()->inventoryItems()->where('inventory_item_id', $item1->id)->first()->pivot->quantity);
    }

    public function test_same_inventory_item_can_belong_to_multiple_packages_with_independent_quantities()
    {
        $sharedItem = InventoryItem::create([
            'name' => 'White Lily',
            'item_code' => 'LILY-001',
            'category' => 'Fresh Flowers',
            'unit' => 'stems',
            'current_stock' => 150,
        ]);

        $pkg1 = Package::create(['title' => 'Pkg A', 'category' => 'Cat', 'price' => 1000]);
        $pkg2 = Package::create(['title' => 'Pkg B', 'category' => 'Cat', 'price' => 2000]);

        $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg1), [
            'title' => 'Pkg A',
            'category' => 'Cat',
            'price' => 1000,
            'inventory_items' => [$sharedItem->id => 10],
        ]);

        $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg2), [
            'title' => 'Pkg B',
            'category' => 'Cat',
            'price' => 2000,
            'inventory_items' => [$sharedItem->id => 30],
        ]);

        $this->assertEquals(10, (float) $pkg1->fresh()->inventoryItems()->where('inventory_item_id', $sharedItem->id)->first()->pivot->quantity);
        $this->assertEquals(30, (float) $pkg2->fresh()->inventoryItems()->where('inventory_item_id', $sharedItem->id)->first()->pivot->quantity);
        $this->assertEquals(150, $sharedItem->fresh()->current_stock);
    }

    public function test_removing_material_detaches_relationship_and_does_not_delete_inventory_item()
    {
        $itemA = InventoryItem::create(['name' => 'Item A', 'category' => 'Cat', 'unit' => 'pcs', 'current_stock' => 10]);
        $itemB = InventoryItem::create(['name' => 'Item B', 'category' => 'Cat', 'unit' => 'pcs', 'current_stock' => 20]);

        $package = Package::create(['title' => 'Detachable Pkg', 'category' => 'Cat', 'price' => 1000]);
        $package->inventoryItems()->sync([
            $itemA->id => ['quantity' => 2],
            $itemB->id => ['quantity' => 4],
        ]);

        $this->assertCount(2, $package->fresh()->inventoryItems);

        // Update with only Item A
        $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'Detachable Pkg',
            'category' => 'Cat',
            'price' => 1000,
            'inventory_items' => [$itemA->id => 2],
        ]);

        $package->refresh();
        $this->assertCount(1, $package->inventoryItems);
        $this->assertEquals($itemA->id, $package->inventoryItems->first()->id);

        // Ensure Item B still exists in database and is not deleted
        $this->assertDatabaseHas('inventory_items', ['id' => $itemB->id, 'deleted_at' => null]);
        $this->assertEquals(20, $itemB->fresh()->current_stock);
    }

    public function test_archived_inventory_item_cannot_be_newly_selected_for_package()
    {
        $activeItem = InventoryItem::create(['name' => 'Active Rose', 'category' => 'Flowers', 'unit' => 'stems']);
        $archivedItem = InventoryItem::create(['name' => 'Archived Orchid', 'category' => 'Flowers', 'unit' => 'stems']);
        $archivedItem->delete(); // soft delete

        // Attempt to create package with archived item
        $responseCreate = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Archived Test Pkg',
            'category' => 'Flowers',
            'price' => 1500,
            'inventory_items' => [$archivedItem->id => 10],
        ]);
        $responseCreate->assertSessionHasErrors('inventory_items.' . $archivedItem->id);
        $this->assertDatabaseMissing('packages', ['title' => 'Archived Test Pkg']);

        // Attempt to add archived item to an existing package
        $package = Package::create(['title' => 'Existing Pkg', 'category' => 'Flowers', 'price' => 1500]);
        $responseUpdate = $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'Existing Pkg',
            'category' => 'Flowers',
            'price' => 1500,
            'inventory_items' => [
                $activeItem->id => 5,
                $archivedItem->id => 10,
            ],
        ]);
        $responseUpdate->assertSessionHasErrors('inventory_items.' . $archivedItem->id);
    }

    public function test_existing_package_historical_relationship_with_archived_item_is_preserved()
    {
        $historicalItem = InventoryItem::create(['name' => 'Historical Flower', 'category' => 'Flowers', 'unit' => 'stems']);
        $package = Package::create(['title' => 'Vintage Pkg', 'category' => 'Vintage', 'price' => 5000]);
        $package->inventoryItems()->sync([$historicalItem->id => ['quantity' => 25]]);

        // Soft-delete the inventory item
        $historicalItem->delete();
        $this->assertSoftDeleted('inventory_items', ['id' => $historicalItem->id]);

        // Package still loads historical BOM via withoutGlobalScope SoftDeletingScope
        $package->refresh();
        $this->assertCount(1, $package->inventoryItems);
        $this->assertEquals(25, (float) $package->inventoryItems->first()->pivot->quantity);
        $this->assertEquals('Historical Flower', $package->inventoryItems->first()->name);

        // Updating other package details retains the archived item mapping
        $response = $this->actingAs($this->admin)->put(route('admin.packages.update', $package), [
            'title' => 'Vintage Pkg Updated',
            'category' => 'Vintage',
            'price' => 5500,
            'inventory_items' => [$historicalItem->id => 25],
        ]);
        $response->assertSessionHas('success');

        $package->refresh();
        $this->assertEquals('Vintage Pkg Updated', $package->title);
        $this->assertCount(1, $package->inventoryItems);
        $this->assertEquals(25, (float) $package->inventoryItems->first()->pivot->quantity);
    }

    public function test_unauthorized_users_cannot_manage_packages()
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $item = InventoryItem::create(['name' => 'Rose', 'category' => 'Flowers', 'unit' => 'stems']);

        // Guest attempts
        $this->get(route('admin.packages.create'))->assertRedirect(route('login'));
        $this->post(route('admin.packages.store'), ['title' => 'Test'])->assertRedirect(route('login'));

        // Client attempts
        $this->actingAs($clientUser)->get(route('admin.packages.create'))->assertForbidden();
        $this->actingAs($clientUser)->post(route('admin.packages.store'), ['title' => 'Test'])->assertForbidden();
    }

    public function test_package_views_render_current_inventory_information()
    {
        $item = InventoryItem::create([
            'name' => 'Carnations',
            'item_code' => 'CAR-001',
            'category' => 'Fresh Flowers',
            'unit' => 'stems',
            'current_stock' => 120,
        ]);

        $responseCreate = $this->actingAs($this->admin)->get(route('admin.packages.create'));
        $responseCreate->assertOk();
        $responseCreate->assertSee('Carnations');
        $responseCreate->assertSee('CAR-001');
        $responseCreate->assertSee('Available:');
        $responseCreate->assertSee('Selected Materials (Bill of Materials)');

        $package = Package::create(['title' => 'Carnation Pkg', 'category' => 'Flowers', 'price' => 1200]);
        $package->inventoryItems()->sync([$item->id => ['quantity' => 20]]);

        $responseEdit = $this->actingAs($this->admin)->get(route('admin.packages.edit', $package));
        $responseEdit->assertOk();
        $responseEdit->assertSee('Carnations');
        $responseEdit->assertSee('CAR-001');
        $responseEdit->assertSee('value="20"', false);
    }
}
