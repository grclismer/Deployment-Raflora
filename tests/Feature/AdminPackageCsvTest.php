<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminPackageCsvTest extends TestCase
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
            'email' => 'admin@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
        ]);

        $this->client = User::create([
            'name' => 'Client User',
            'email' => 'client@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
    }

    /**
     * 1. Authorized user can export packages.
     */
    public function test_authorized_user_can_export_packages(): void
    {
        Package::create([
            'package_code' => 'PKG-WED-001',
            'title' => 'Wedding Deluxe',
            'category' => 'Wedding',
            'price' => 30000,
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.export.packages'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('packages_export_', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * 2. Export headers are correct.
     */
    public function test_export_headers_are_correct(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.export.packages'));

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $lines = explode("\n", trim(str_replace("\r", '', $content)));

        $this->assertEquals('package_code,package_name,category,description,price,is_active,included_items', $lines[0]);
    }

    /**
     * 3. Export includes package values.
     */
    public function test_export_includes_package_values(): void
    {
        Package::create([
            'package_code' => 'PKG-CORP-01',
            'title' => 'Corporate Gala',
            'category' => 'Corporate',
            'description' => 'VIP table decor',
            'price' => 18500.50,
            'is_active' => true,
            'included_items' => ['Centerpiece A', 'Centerpiece B'],
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.export.packages'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('PKG-CORP-01', $content);
        $this->assertStringContainsString('Corporate Gala', $content);
        $this->assertStringContainsString('Corporate', $content);
        $this->assertStringContainsString('VIP table decor', $content);
        $this->assertStringContainsString('18500.50', $content);
        $this->assertStringContainsString('Centerpiece A; Centerpiece B', $content);
    }

    /**
     * 4. Unauthorized user cannot export packages.
     */
    public function test_unauthorized_user_cannot_export_packages(): void
    {
        $guestResponse = $this->get(route('admin.packages.export.packages'));
        $guestResponse->assertRedirect(route('login'));

        $staffResponse = $this->actingAs($this->staff)->get(route('admin.packages.export.packages'));
        $staffResponse->assertForbidden();

        $clientResponse = $this->actingAs($this->client)->get(route('admin.packages.export.packages'));
        $clientResponse->assertForbidden();
    }

    /**
     * 5. Authorized user can download package template.
     */
    public function test_authorized_user_can_download_package_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.template.packages'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('packages_template.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('package_code,package_name,category,description,price,is_active,included_items', $content);
        $this->assertStringContainsString('PKG-WED-001', $content);
        $this->assertStringContainsString('Classic Wedding Package', $content);
    }

    /**
     * 6. Authorized user can download package materials template.
     */
    public function test_authorized_user_can_download_package_materials_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.template.materials'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('package_materials_template.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('package_code,item_code,quantity', $content);
        $this->assertStringContainsString('PKG-WED-001,FRE-0001,50', $content);
    }

    /**
     * 7. Authorized user can export BOM mappings.
     */
    public function test_authorized_user_can_export_bom_mappings(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-WED-002',
            'title' => 'Rustic Wedding',
            'category' => 'Wedding',
            'price' => 22000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-0010',
            'name' => 'White Lily',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 30,
            'min_stock' => 20,
            'is_perishable' => true,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 25]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.export.materials'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('package_materials_export_', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * 8. BOM export contains package identifier/item code/quantity.
     */
    public function test_bom_export_contains_package_identifier_item_code_and_quantity(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-BOM-01',
            'title' => 'Debut Grand',
            'category' => 'Debut',
            'price' => 35000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'PRO-0020',
            'name' => 'Candelabra Gold',
            'category' => 'Props',
            'unit' => 'pcs',
            'current_stock' => 15,
            'unit_cost' => 450,
            'min_stock' => 5,
            'is_perishable' => false,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 10]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.export.materials'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringContainsString('package_code,item_code,quantity', $content);
        $this->assertStringContainsString('PKG-BOM-01,PRO-0020,10', $content);
    }

    /**
     * 9. BOM export does not contain inventory stock data.
     */
    public function test_bom_export_does_not_contain_inventory_stock_data(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-SAFE-01',
            'title' => 'Safe Package',
            'category' => 'General',
            'price' => 12000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-9999',
            'name' => 'Secret Stock Flower',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 777,
            'unit_cost' => 88.88,
            'min_stock' => 99,
            'is_perishable' => true,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 5]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.export.materials'));

        $response->assertStatus(200);
        $content = $response->streamedContent();

        $lines = explode("\n", trim(str_replace("\r", '', $content)));
        $this->assertEquals('package_code,item_code,quantity', $lines[0]);
        $this->assertStringNotContainsString('current_stock', $content);
        $this->assertStringNotContainsString('unit_cost', $content);
        $this->assertStringNotContainsString('min_stock', $content);
        $this->assertStringNotContainsString('777', $content);
        $this->assertStringNotContainsString('88.88', $content);
    }

    /**
     * 10. Valid package creates new package.
     */
    public function test_valid_package_creates_new_package(): void
    {
        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-IMP-001,Modern Minimalist,Wedding,Subtle floral styling,16000.00,1,\"Bridal Bouquet; Boutonniere\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('packages', [
            'package_code' => 'PKG-IMP-001',
            'title' => 'Modern Minimalist',
            'category' => 'Wedding',
            'price' => 16000.00,
            'is_active' => true,
        ]);

        $package = Package::where('package_code', 'PKG-IMP-001')->first();
        $this->assertEquals(['Bridal Bouquet', 'Boutonniere'], $package->included_items);
    }

    /**
     * 11. Valid package updates existing package.
     */
    public function test_valid_package_updates_existing_package(): void
    {
        $existing = Package::create([
            'package_code' => 'PKG-UPD-001',
            'title' => 'Old Title',
            'category' => 'General',
            'price' => 10000,
            'is_active' => true,
        ]);

        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-UPD-001,Updated Premium Bundle,Luxury,New description,15500.00,1,\"Item 1; Item 2\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertEquals(1, Package::where('package_code', 'PKG-UPD-001')->count());
        $fresh = $existing->fresh();
        $this->assertEquals('Updated Premium Bundle', $fresh->title);
        $this->assertEquals('Luxury', $fresh->category);
        $this->assertEquals(15500.00, (float) $fresh->price);
    }

    /**
     * 12. Invalid package data rejected.
     */
    public function test_invalid_package_data_rejected(): void
    {
        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-INV-001,,Wedding,No Name,-500.00,1,\"Inclusions\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('packages', ['package_code' => 'PKG-INV-001']);
    }

    /**
     * 13. Duplicate package handling is deterministic (duplicate in file rejected).
     */
    public function test_duplicate_package_code_in_import_is_rejected(): void
    {
        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-DUP-001,Package First,Event,Desc 1,10000,1,\"Item\"\n"
             . "PKG-DUP-001,Package Second,Event,Desc 2,12000,1,\"Item\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('packages', ['package_code' => 'PKG-DUP-001']);
    }

    /**
     * 14. Import is atomic.
     */
    public function test_package_import_is_atomic(): void
    {
        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-ATOM-01,Valid Package,Event,Desc,10000,1,\"Item\"\n"
             . "PKG-ATOM-02,Invalid Package,Event,Desc,-500,1,\"Item\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        // Both rows must be absent due to atomicity rollback
        $this->assertDatabaseMissing('packages', ['package_code' => 'PKG-ATOM-01']);
        $this->assertDatabaseMissing('packages', ['package_code' => 'PKG-ATOM-02']);
    }

    /**
     * 15. Unauthorized user cannot import.
     */
    public function test_unauthorized_user_cannot_import_packages(): void
    {
        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-UNAUTH,Package,Event,Desc,10000,1,\"Item\"\n";
        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $guest = $this->post(route('admin.packages.import.packages'), ['csv_file' => $file]);
        $guest->assertRedirect(route('login'));

        $staff = $this->actingAs($this->staff)->post(route('admin.packages.import.packages'), ['csv_file' => $file]);
        $staff->assertForbidden();

        $client = $this->actingAs($this->client)->post(route('admin.packages.import.packages'), ['csv_file' => $file]);
        $client->assertForbidden();
    }

    /**
     * 16. Valid BOM row maps to existing InventoryItem.
     */
    public function test_valid_bom_row_maps_to_existing_inventory_item(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-BOM-OK',
            'title' => 'BOM Test Package',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-0050',
            'name' => 'Peonies Pink',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 120,
            'unit_cost' => 50,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-BOM-OK,FRE-0050,40\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('inventory_item_package', [
            'package_id' => $package->id,
            'inventory_item_id' => $item->id,
            'quantity' => 40,
        ]);
    }

    /**
     * 17. Pivot quantity saved correctly.
     */
    public function test_pivot_quantity_saved_correctly(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-QTY-01',
            'title' => 'Quantity Check',
            'category' => 'Event',
            'price' => 15000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'ACC-0010',
            'name' => 'Silk Ribbon White',
            'category' => 'Accessories',
            'unit' => 'meters',
            'current_stock' => 50,
            'unit_cost' => 15,
            'min_stock' => 5,
            'is_perishable' => false,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-QTY-01,ACC-0010,12.5\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('success');

        $this->assertEquals(12.5, (float) $package->fresh()->inventoryItems->first()->pivot->quantity);
    }

    /**
     * 18. Unknown item_code rejected.
     */
    public function test_unknown_item_code_rejected(): void
    {
        Package::create([
            'package_code' => 'PKG-UNK-ITEM',
            'title' => 'Package Exists',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-UNK-ITEM,NON-EXISTENT-CODE,20\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseCount('inventory_item_package', 0);
    }

    /**
     * 19. Unknown package rejected.
     */
    public function test_unknown_package_rejected(): void
    {
        InventoryItem::create([
            'item_code' => 'FRE-0099',
            'name' => 'Rose Red',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "NON-EXISTENT-PKG,FRE-0099,20\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseCount('inventory_item_package', 0);
    }

    /**
     * 20. Invalid quantity rejected.
     */
    public function test_invalid_quantity_rejected(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-INV-QTY',
            'title' => 'Inv Qty Package',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-0100',
            'name' => 'Rose Red',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-INV-QTY,FRE-0100,abc\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
    }

    /**
     * 21. Zero quantity rejected.
     */
    public function test_zero_quantity_rejected(): void
    {
        Package::create([
            'package_code' => 'PKG-ZERO-01',
            'title' => 'Zero Qty Package',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        InventoryItem::create([
            'item_code' => 'FRE-0101',
            'name' => 'Rose Red',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-ZERO-01,FRE-0101,0\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
    }

    /**
     * 22. Negative quantity rejected.
     */
    public function test_negative_quantity_rejected(): void
    {
        Package::create([
            'package_code' => 'PKG-NEG-01',
            'title' => 'Negative Qty Package',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        InventoryItem::create([
            'item_code' => 'FRE-0102',
            'name' => 'Rose Red',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-NEG-01,FRE-0102,-15\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
    }

    /**
     * 23. Duplicate package/item mapping rejected.
     */
    public function test_duplicate_package_item_mapping_rejected(): void
    {
        Package::create([
            'package_code' => 'PKG-DUP-MAP',
            'title' => 'Duplicate Map Package',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        InventoryItem::create([
            'item_code' => 'FRE-0103',
            'name' => 'Rose Red',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-DUP-MAP,FRE-0103,10\n"
             . "PKG-DUP-MAP,FRE-0103,20\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
    }

    /**
     * 24. Archived item rule enforced in BOM import.
     */
    public function test_archived_item_rule_enforced_in_bom_import(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-ARCH-TEST',
            'title' => 'Archived Test Package',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        $archivedItem = InventoryItem::create([
            'item_code' => 'FRE-ARCHIVED',
            'name' => 'Archived Daisy',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 0,
            'unit_cost' => 15,
            'min_stock' => 5,
            'is_perishable' => true,
        ]);
        $archivedItem->delete(); // soft delete

        // 1. Newly attaching an archived item must be rejected
        $csv = "package_code,item_code,quantity\n"
             . "PKG-ARCH-TEST,FRE-ARCHIVED,10\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');

        // 2. If item was historically attached, updating its quantity is permitted
        $package->inventoryItems()->attach($archivedItem->id, ['quantity' => 5]);

        $csv2 = "package_code,item_code,quantity\n"
              . "PKG-ARCH-TEST,FRE-ARCHIVED,12\n";
        $file2 = UploadedFile::fake()->createWithContent('materials.csv', $csv2);

        $response2 = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file2,
        ]);

        $response2->assertRedirect(route('admin.packages.index'));
        $response2->assertSessionHas('success');
        $this->assertEquals(12, (float) $package->fresh()->inventoryItems->first()->pivot->quantity);
    }

    /**
     * 25. BOM import is atomic.
     */
    public function test_bom_import_is_atomic(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-ATOM-BOM',
            'title' => 'Atomic BOM Package',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-0200',
            'name' => 'Valid Item',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-ATOM-BOM,FRE-0200,15\n"
             . "PKG-ATOM-BOM,UNKNOWN-ITEM,20\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.packages.index'));
        $response->assertSessionHas('error');
        // Rollback ensures row 1 is not saved
        $this->assertDatabaseMissing('inventory_item_package', ['package_id' => $package->id]);
    }

    /**
     * 26. Package import does not change current_stock.
     */
    public function test_package_import_does_not_change_current_stock(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'FRE-0300',
            'name' => 'Tulip Yellow',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 250,
            'unit_cost' => 35,
            'min_stock' => 50,
            'is_perishable' => true,
        ]);

        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-STOCK-CHK,Check Stock Package,Wedding,Desc,15000,1,\"Inclusions\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $this->assertEquals(250, (float) $item->fresh()->current_stock);
    }

    /**
     * 27. BOM import does not change current_stock.
     */
    public function test_bom_import_does_not_change_current_stock(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-BOM-STOCK',
            'title' => 'BOM Stock Safety',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-0301',
            'name' => 'Tulip Purple',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 180,
            'unit_cost' => 40,
            'min_stock' => 30,
            'is_perishable' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-BOM-STOCK,FRE-0301,60\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $this->assertEquals(180, (float) $item->fresh()->current_stock);
    }

    /**
     * 28. Package import does not create reservation.
     */
    public function test_package_import_does_not_create_reservation(): void
    {
        $initialTxCount = InventoryTransaction::count();

        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-RES-CHK,No Reservation Package,Wedding,Desc,15000,1,\"Inclusions\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $this->assertEquals($initialTxCount, InventoryTransaction::count());
    }

    /**
     * 29. Package import does not alter bookings.
     */
    public function test_package_import_does_not_alter_bookings(): void
    {
        $booking = Booking::create([
            'guest_name' => 'Guest Client',
            'guest_email' => 'client@example.com',
            'guest_phone' => '09123456789',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '10:00:00',
            'venue' => 'Grand Hotel',
            'status' => 'pending',
        ]);

        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-BK-CHK,Booking Unaltered,Wedding,Desc,15000,1,\"Inclusions\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $freshBooking = $booking->fresh();
        $this->assertEquals('pending', $freshBooking->status);
        $this->assertEquals('Grand Hotel', $freshBooking->venue);
    }

    /**
     * 30. Package import does not alter payments.
     */
    public function test_package_import_does_not_alter_payments(): void
    {
        $booking = Booking::create([
            'guest_name' => 'Debut Client',
            'guest_email' => 'debut@example.com',
            'guest_phone' => '09123456789',
            'event_type' => 'Debut',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '14:00:00',
            'venue' => 'Manila Hotel',
            'status' => 'confirmed',
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 15000,
            'reference_number' => 'REF-PAY-12345',
            'status' => 'verified',
            'payment_type' => 'downpayment',
        ]);

        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-PAY-CHK,Payment Unaltered,Debut,Desc,20000,1,\"Inclusions\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $freshPayment = $payment->fresh();
        $this->assertEquals(15000, (float) $freshPayment->amount);
        $this->assertEquals('verified', $freshPayment->status);
    }

    /**
     * 31. Existing substitute relationships remain intact.
     */
    public function test_existing_substitute_relationships_remain_intact(): void
    {
        $item1 = InventoryItem::create([
            'item_code' => 'FRE-SUB-01',
            'name' => 'Red Rose Local',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $item2 = InventoryItem::create([
            'item_code' => 'FRE-SUB-02',
            'name' => 'Red Rose Imported',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 80,
            'unit_cost' => 45,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $item1->substitutes()->attach($item2->id);

        $package = Package::create([
            'package_code' => 'PKG-SUB-TEST',
            'title' => 'Sub Test',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-SUB-TEST,FRE-SUB-01,30\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $this->assertTrue($item1->fresh()->substitutes->contains('id', $item2->id));
    }

    /**
     * 32. Existing booking relationships remain intact.
     */
    public function test_existing_booking_relationships_remain_intact(): void
    {
        $item = InventoryItem::create([
            'item_code' => 'FRE-BK-REL',
            'name' => 'Blue Hydrangea',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 50,
            'unit_cost' => 80,
            'min_stock' => 5,
            'is_perishable' => true,
        ]);

        $booking = Booking::create([
            'guest_name' => 'Corporate Client',
            'guest_email' => 'corp@example.com',
            'guest_phone' => '09123456789',
            'event_type' => 'Corporate',
            'event_date' => now()->addDays(15)->toDateString(),
            'event_time' => '09:00:00',
            'venue' => 'SMX',
            'status' => 'confirmed',
        ]);

        $booking->inventoryItems()->attach($item->id, ['quantity' => 20, 'quoted_unit_price' => 100]);

        $package = Package::create([
            'package_code' => 'PKG-BK-BOM',
            'title' => 'Package BOM Check',
            'category' => 'Corporate',
            'price' => 30000,
            'is_active' => true,
        ]);

        $csv = "package_code,item_code,quantity\n"
             . "PKG-BK-BOM,FRE-BK-REL,10\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $this->assertTrue($booking->fresh()->inventoryItems->contains('id', $item->id));
    }

    /**
     * 33. Detaching BOM does not delete InventoryItem.
     */
    public function test_detaching_bom_does_not_delete_inventory_item(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-DETACH-01',
            'title' => 'Detach Check',
            'category' => 'Event',
            'price' => 10000,
            'is_active' => true,
        ]);

        $itemA = InventoryItem::create([
            'item_code' => 'FRE-DET-A',
            'name' => 'Item A',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 20,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $itemB = InventoryItem::create([
            'item_code' => 'FRE-DET-B',
            'name' => 'Item B',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 25,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $package->inventoryItems()->attach([$itemA->id => ['quantity' => 10], $itemB->id => ['quantity' => 20]]);

        // Now import BOM with only Item A
        $csv = "package_code,item_code,quantity\n"
             . "PKG-DETACH-01,FRE-DET-A,15\n";

        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $response->assertSessionHas('success');

        // Item B is detached from package
        $this->assertFalse($package->fresh()->inventoryItems->contains('id', $itemB->id));
        // But Item B still safely exists in Inventory
        $this->assertDatabaseHas('inventory_items', ['id' => $itemB->id, 'deleted_at' => null]);
    }

    /**
     * 34. Exported package CSV can be re-imported.
     */
    public function test_exported_package_csv_can_be_reimported(): void
    {
        Package::create([
            'package_code' => 'PKG-RT-01',
            'title' => 'Round Trip Master',
            'category' => 'Wedding',
            'description' => 'A round trip test package',
            'price' => 28000,
            'is_active' => true,
            'included_items' => ['Bouquet', 'Boutonniere'],
        ]);

        $export = $this->actingAs($this->admin)->get(route('admin.packages.export.packages'));
        $content = $export->streamedContent();

        $file = UploadedFile::fake()->createWithContent('exported_packages.csv', $content);

        $import = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $file,
        ]);

        $import->assertRedirect(route('admin.packages.index'));
        $import->assertSessionHas('success');
    }

    /**
     * 35. Exported BOM CSV can be re-imported.
     */
    public function test_exported_bom_csv_can_be_reimported(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-RT-BOM',
            'title' => 'Round Trip BOM Package',
            'category' => 'Wedding',
            'price' => 28000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-RT-01',
            'name' => 'Tulip Round Trip',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 150,
            'unit_cost' => 35,
            'min_stock' => 15,
            'is_perishable' => true,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 45]);

        $export = $this->actingAs($this->admin)->get(route('admin.packages.export.materials'));
        $content = $export->streamedContent();

        $file = UploadedFile::fake()->createWithContent('exported_bom.csv', $content);

        $import = $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), [
            'csv_file' => $file,
        ]);

        $import->assertRedirect(route('admin.packages.index'));
        $import->assertSessionHas('success');
    }

    /**
     * 36. Round-trip does not create duplicates.
     */
    public function test_round_trip_does_not_create_duplicates(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-NODUP-01',
            'title' => 'No Duplicate Roundtrip',
            'category' => 'Debut',
            'price' => 31000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-NODUP-01',
            'name' => 'No Dup Rose',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 100,
            'unit_cost' => 25,
            'min_stock' => 10,
            'is_perishable' => true,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 30]);

        $pkgExport = $this->actingAs($this->admin)->get(route('admin.packages.export.packages'));
        $pkgFile = UploadedFile::fake()->createWithContent('pkg.csv', $pkgExport->streamedContent());
        $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), ['csv_file' => $pkgFile]);

        $bomExport = $this->actingAs($this->admin)->get(route('admin.packages.export.materials'));
        $bomFile = UploadedFile::fake()->createWithContent('bom.csv', $bomExport->streamedContent());
        $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), ['csv_file' => $bomFile]);

        $this->assertEquals(1, Package::where('package_code', 'PKG-NODUP-01')->count());
        $this->assertEquals(1, $package->fresh()->inventoryItems()->count());
    }

    /**
     * 37. Round-trip preserves quantities.
     */
    public function test_round_trip_preserves_quantities(): void
    {
        $package = Package::create([
            'package_code' => 'PKG-PRESERVE-01',
            'title' => 'Preserve Quantities Package',
            'category' => 'Wedding',
            'price' => 45000,
            'is_active' => true,
        ]);

        $item = InventoryItem::create([
            'item_code' => 'FRE-PRES-01',
            'name' => 'Preserved Rose',
            'category' => 'Flowers',
            'unit' => 'stems',
            'current_stock' => 200,
            'unit_cost' => 30,
            'min_stock' => 20,
            'is_perishable' => true,
        ]);

        $package->inventoryItems()->attach($item->id, ['quantity' => 75]);

        $bomExport = $this->actingAs($this->admin)->get(route('admin.packages.export.materials'));
        $bomFile = UploadedFile::fake()->createWithContent('bom.csv', $bomExport->streamedContent());
        $this->actingAs($this->admin)->post(route('admin.packages.import.materials'), ['csv_file' => $bomFile]);

        $this->assertEquals(75, (float) $package->fresh()->inventoryItems->first()->pivot->quantity);
    }

    /**
     * 38. Formula injection mitigation is present where appropriate.
     */
    public function test_formula_injection_mitigation_is_present(): void
    {
        // 1. Export escapes formula characters (=, +, -, @)
        Package::create([
            'package_code' => 'PKG-SEC-01',
            'title' => '=SUM(A1:A10)',
            'category' => '+VIPCategory',
            'description' => '-DANGEROUS',
            'price' => 15000,
            'is_active' => true,
        ]);

        $export = $this->actingAs($this->admin)->get(route('admin.packages.export.packages'));
        $content = $export->streamedContent();

        $this->assertStringContainsString("'=SUM(A1:A10)", $content);
        $this->assertStringContainsString("'+VIPCategory", $content);
        $this->assertStringContainsString("'-DANGEROUS", $content);

        // 2. Import strips leading single quote defense
        $csv = "package_code,package_name,category,description,price,is_active,included_items\n"
             . "PKG-SEC-02,'=CALC(),'+SafeCategory,'-SafeDesc,12000,1,\"Inclusions\"\n";

        $file = UploadedFile::fake()->createWithContent('packages.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), ['csv_file' => $file]);

        $pkg = Package::where('package_code', 'PKG-SEC-02')->first();
        $this->assertNotNull($pkg);
        $this->assertEquals('=CALC()', $pkg->title);
        $this->assertEquals('+SafeCategory', $pkg->category);
        $this->assertEquals('-SafeDesc', $pkg->description);
    }

    /**
     * 39. Unauthorized roles are rejected from templates and imports.
     */
    public function test_unauthorized_roles_are_rejected(): void
    {
        $csv = "package_code,item_code,quantity\nPKG-1,ITEM-1,10\n";
        $file = UploadedFile::fake()->createWithContent('materials.csv', $csv);

        // Template downloads
        $this->actingAs($this->staff)->get(route('admin.packages.template.packages'))->assertForbidden();
        $this->actingAs($this->staff)->get(route('admin.packages.template.materials'))->assertForbidden();

        // Export materials
        $this->actingAs($this->staff)->get(route('admin.packages.export.materials'))->assertForbidden();

        // Import materials
        $this->actingAs($this->staff)->post(route('admin.packages.import.materials'), ['csv_file' => $file])->assertForbidden();
    }

    /**
     * 40. Malformed CSV is rejected.
     */
    public function test_malformed_csv_is_rejected(): void
    {
        // 1. Non-CSV file
        $txtFile = UploadedFile::fake()->create('packages.txt', 10, 'text/plain');
        $respTxt = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $txtFile,
        ]);
        $respTxt->assertSessionHas('error');
        $respTxt->assertSessionHasErrors('csv_file');

        // 2. CSV missing required columns
        $badHeaderCsv = "package_code,package_name,wrong_column\nPKG-1,Title,Bad\n";
        $badHeaderFile = UploadedFile::fake()->createWithContent('bad_header.csv', $badHeaderCsv);
        $respHeader = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $badHeaderFile,
        ]);
        $respHeader->assertSessionHas('error');
        $respHeader->assertSessionHasErrors('csv_file');

        // 3. Row with mismatched column count
        $badRowCsv = "package_code,package_name,category,description,price,is_active,included_items\n"
                   . "PKG-1,Title,Category,Too,Many,Columns,Extra,Here\n";
        $badRowFile = UploadedFile::fake()->createWithContent('bad_row.csv', $badRowCsv);
        $respRow = $this->actingAs($this->admin)->post(route('admin.packages.import.packages'), [
            'csv_file' => $badRowFile,
        ]);
        $respRow->assertSessionHas('error');
        $respRow->assertSessionHasErrors('csv_file');
    }
}
