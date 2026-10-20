<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class AdminInventoryCsvTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    /**
     * 1. Authorized admin can download CSV template.
     */
    public function test_authorized_admin_can_download_csv_template(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.template'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inventory_template.csv', (string) $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString("name,category,is_perishable,current_stock,unit_cost,min_stock,unit", $content);
        $this->assertStringContainsString("Red Roses", $content);
        $this->assertStringContainsString("Flowers,1,200,15.00,50,stems", $content);
    }

    /**
     * 2. Authorized admin can export inventory CSV.
     */
    public function test_authorized_admin_can_export_inventory_csv(): void
    {
        InventoryItem::create([
            'name' => 'Tulip Pink',
            'category' => 'Flowers',
            'is_perishable' => true,
            'current_stock' => 80,
            'unit_cost' => 25.00,
            'min_stock' => 15,
            'unit' => 'stems',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.export'));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inventory_export_', (string) $response->headers->get('Content-Disposition'));
    }

    /**
     * 3. Export contains exact required header.
     */
    public function test_export_contains_exact_required_header(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.export'));

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $lines = explode("\n", trim(str_replace("\r", '', $content)));

        $this->assertEquals('name,category,is_perishable,current_stock,unit_cost,min_stock,unit', $lines[0]);
    }

    /**
     * 4. Export contains actual current inventory values.
     */
    public function test_export_contains_actual_current_inventory_values(): void
    {
        InventoryItem::create([
            'name' => 'Red Roses',
            'category' => 'Flowers',
            'is_perishable' => true,
            'current_stock' => 200,
            'unit_cost' => 15.00,
            'min_stock' => 50,
            'unit' => 'stems',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.inventory.export'));

        $response->assertStatus(200);
        $content = $response->streamedContent();
        $this->assertStringContainsString('Red Roses', $content);
        $this->assertStringContainsString('Flowers,1,200,15.00,50,stems', $content);
    }

    /**
     * 5. Authorized admin can upload valid CSV.
     */
    public function test_authorized_admin_can_upload_valid_csv(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Sunflower,Flowers,1,100,12.50,20,stems\n"
             . "Glass Vase,Props,0,30,150.00,5,pcs\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('inventory_items', ['name' => 'Sunflower']);
        $this->assertDatabaseHas('inventory_items', ['name' => 'Glass Vase']);
    }

    /**
     * 6. New CSV row creates inventory item.
     */
    public function test_new_csv_row_creates_inventory_item(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Hydrangea Blue,Flowers,1,75,35.00,10,stems\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $item = InventoryItem::where('name', 'Hydrangea Blue')->first();
        $this->assertNotNull($item);
        $this->assertEquals('Flowers', $item->category);
        $this->assertTrue($item->is_perishable);
        $this->assertEquals(75, (float) $item->current_stock);
        $this->assertEquals(35.00, (float) $item->unit_cost);
        $this->assertEquals(10, (float) $item->min_stock);
        $this->assertEquals('stems', $item->unit);
        $this->assertNotEmpty($item->item_code);
    }

    /**
     * 7. Existing CSV name updates existing inventory item.
     */
    public function test_existing_csv_name_updates_existing_inventory_item(): void
    {
        $existing = InventoryItem::create([
            'name' => 'Arch Stand Tall',
            'category' => 'Props',
            'is_perishable' => false,
            'current_stock' => 5,
            'unit_cost' => 500.00,
            'min_stock' => 2,
            'unit' => 'pcs',
        ]);

        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Arch Stand Tall,Props,0,12,550.00,3,pcs\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $this->assertEquals(1, InventoryItem::where('name', 'Arch Stand Tall')->count());
        $updated = $existing->fresh();
        $this->assertEquals(12, (float) $updated->current_stock);
        $this->assertEquals(550.00, (float) $updated->unit_cost);
        $this->assertEquals(3, (float) $updated->min_stock);
    }

    /**
     * 8. Duplicate item name inside CSV is rejected.
     */
    public function test_duplicate_item_name_inside_csv_is_rejected(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Carnation Pink,Flowers,1,50,10.00,10,stems\n"
             . "Carnation Pink,Flowers,1,60,11.00,15,stems\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Carnation Pink']);
    }

    /**
     * 9. Missing required header is rejected.
     */
    public function test_missing_required_header_is_rejected(): void
    {
        $csv = "item_name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Invalid Header Item,Flowers,1,50,10.00,10,stems\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Invalid Header Item']);
    }

    /**
     * 10. Invalid numeric current_stock is rejected.
     */
    public function test_invalid_numeric_current_stock_is_rejected(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Orchid White,Flowers,1,invalid_number,20.00,5,pots\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Orchid White']);
    }

    /**
     * 11. Negative current_stock is rejected.
     */
    public function test_negative_current_stock_is_rejected(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Orchid White,Flowers,1,-10,20.00,5,pots\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Orchid White']);
    }

    /**
     * 12. Invalid is_perishable value is rejected.
     */
    public function test_invalid_is_perishable_value_is_rejected(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Orchid White,Flowers,maybe,10,20.00,5,pots\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Orchid White']);
    }

    /**
     * 13. Invalid unit is rejected.
     */
    public function test_invalid_unit_is_rejected(): void
    {
        $longUnit = str_repeat('a', 55);
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Orchid White,Flowers,1,10,20.00,5,{$longUnit}\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $response->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Orchid White']);
    }

    /**
     * 14. CSV import is atomic: one invalid row causes all changes to roll back.
     */
    public function test_csv_import_is_atomic_one_invalid_row_rolls_back_all(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Valid Item A,Flowers,1,50,15.00,10,stems\n"
             . "Invalid Item B,Flowers,1,50,15.00,-5,stems\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        $response = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $response->assertRedirect(route('admin.inventory.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Valid Item A']);
        $this->assertDatabaseMissing('inventory_items', ['name' => 'Invalid Item B']);
    }

    /**
     * 15. Unauthenticated/unauthorized user cannot import.
     */
    public function test_unauthenticated_or_unauthorized_user_cannot_import(): void
    {
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Blocked Item,Flowers,1,50,15.00,10,stems\n";
        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);

        // Guest
        $guestResponse = $this->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);
        $guestResponse->assertRedirect();

        // Staff
        $staff = User::create([
            'name' => 'Staff User',
            'email' => 'staff@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
        ]);
        $staffResponse = $this->actingAs($staff)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);
        $staffResponse->assertStatus(403);

        // Client
        $client = User::create([
            'name' => 'Client User',
            'email' => 'client@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
        $clientResponse = $this->actingAs($client)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);
        $clientResponse->assertStatus(403);
    }

    /**
     * 16. Unauthenticated/unauthorized user cannot export.
     */
    public function test_unauthenticated_or_unauthorized_user_cannot_export(): void
    {
        // Guest
        $guestResponse = $this->get(route('admin.inventory.export'));
        $guestResponse->assertRedirect();

        // Staff
        $staff = User::create([
            'name' => 'Staff User 2',
            'email' => 'staff2@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
        ]);
        $staffResponse = $this->actingAs($staff)->get(route('admin.inventory.export'));
        $staffResponse->assertStatus(403);

        // Client
        $client = User::create([
            'name' => 'Client User 2',
            'email' => 'client2@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
        $clientResponse = $this->actingAs($client)->get(route('admin.inventory.export'));
        $clientResponse->assertStatus(403);
    }

    /**
     * 17. Exported CSV can be imported again without creating duplicates.
     */
    public function test_exported_csv_can_be_imported_again_without_creating_duplicates(): void
    {
        InventoryItem::create([
            'name' => 'Round Arch Frame',
            'category' => 'Props',
            'is_perishable' => false,
            'current_stock' => 4,
            'unit_cost' => 1200.00,
            'min_stock' => 1,
            'unit' => 'pcs',
        ]);
        InventoryItem::create([
            'name' => 'Baby Breath',
            'category' => 'Flowers',
            'is_perishable' => true,
            'current_stock' => 150,
            'unit_cost' => 8.50,
            'min_stock' => 30,
            'unit' => 'bundles',
        ]);

        $initialCount = InventoryItem::count();
        $this->assertEquals(2, $initialCount);

        // Export
        $exportResponse = $this->actingAs($this->admin)->get(route('admin.inventory.export'));
        $exportContent = $exportResponse->streamedContent();

        // Re-import exported content
        $file = UploadedFile::fake()->createWithContent('reimport.csv', $exportContent);
        $importResponse = $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $importResponse->assertRedirect(route('admin.inventory.index'));
        $importResponse->assertSessionHas('success');
        $this->assertEquals(2, InventoryItem::count());
    }

    /**
     * 18. Existing substitute relationships are not silently destroyed by CSV import.
     */
    public function test_existing_substitute_relationships_are_not_destroyed_by_csv_import(): void
    {
        $primaryItem = InventoryItem::create([
            'name' => 'Red Rose Premium',
            'category' => 'Flowers',
            'is_perishable' => true,
            'current_stock' => 100,
            'unit_cost' => 20.00,
            'min_stock' => 20,
            'unit' => 'stems',
        ]);

        $substituteItem = InventoryItem::create([
            'name' => 'Red Carnation Substitute',
            'category' => 'Flowers',
            'is_perishable' => true,
            'current_stock' => 80,
            'unit_cost' => 15.00,
            'min_stock' => 15,
            'unit' => 'stems',
        ]);

        $primaryItem->substitutes()->attach($substituteItem->id);
        $this->assertEquals(1, $primaryItem->substitutes()->count());

        // Update primaryItem via CSV
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Red Rose Premium,Flowers,1,120,22.00,25,stems\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);
        $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $reloaded = $primaryItem->fresh();
        $this->assertEquals(120, (float) $reloaded->current_stock);
        $this->assertEquals(1, $reloaded->substitutes()->count());
        $this->assertEquals($substituteItem->id, $reloaded->substitutes()->first()->id);
    }

    /**
     * 19. Existing booking/inventory relationships remain intact.
     */
    public function test_existing_booking_inventory_relationships_remain_intact(): void
    {
        $item = InventoryItem::create([
            'name' => 'Gold Chiavari Chair',
            'category' => 'Furniture',
            'is_perishable' => false,
            'current_stock' => 100,
            'unit_cost' => 150.00,
            'min_stock' => 20,
            'unit' => 'pcs',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'confirmed',
            'total_quoted' => 5000,
        ]);

        $booking->inventoryItems()->attach($item->id, [
            'quantity' => 50,
            'quoted_unit_price' => 180,
            'procurement_status' => 'confirmed',
        ]);

        // CSV update of item stock
        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Gold Chiavari Chair,Furniture,0,120,160.00,20,pcs\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);
        $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $this->assertEquals(120, (float) $item->fresh()->current_stock);
        $this->assertEquals(1, $booking->inventoryItems()->count());
        $this->assertEquals(50, $booking->inventoryItems()->first()->pivot->quantity);
    }

    /**
     * 20. CSV import does not create unintended booking or payment changes.
     */
    public function test_csv_import_does_not_create_unintended_booking_or_payment_changes(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(7)->toDateString(),
            'venue' => 'Garden Terrace',
            'status' => 'downpayment_verified',
            'total_quoted' => 12000,
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 6000,
            'payment_type' => 'downpayment',
            'reference_number' => 'REF-123456',
            'status' => 'verified',
        ]);

        $csv = "name,category,is_perishable,current_stock,unit_cost,min_stock,unit\n"
             . "Brand New Candleholder,Decor,0,40,75.00,10,pcs\n";

        $file = UploadedFile::fake()->createWithContent('inventory.csv', $csv);
        $this->actingAs($this->admin)->post(route('admin.inventory.import'), [
            'csv_file' => $file,
        ]);

        $freshBooking = $booking->fresh();
        $this->assertEquals('downpayment_verified', $freshBooking->status);
        $this->assertEquals(12000, (float) $freshBooking->total_quoted);

        $freshPayment = $payment->fresh();
        $this->assertEquals('verified', $freshPayment->status);
        $this->assertEquals(6000, (float) $freshPayment->amount);
    }

    /**
     * 21. Modernized inventory UI displays CSV Tools dropdown and primary Add Item action.
     */
    public function test_inventory_ui_displays_csv_tools_dropdown_and_add_item_action(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertStatus(200);
        $response->assertSee('CSV Tools');
        $response->assertSee('aria-haspopup="true"', false);
        $response->assertSee('aria-controls="csvToolsMenu"', false);
        $response->assertSee('Add Item');
        $response->assertSee(route('admin.inventory.create'));
        $response->assertSee(route('admin.inventory.export'));
        $response->assertSee(route('admin.inventory.template'));
        $response->assertSee('Download Inventory CSV');
        $response->assertSee('Download Template');
        $response->assertSee('Import Inventory CSV');
        $response->assertSee('Import Instructions');
    }

    /**
     * 22. Modernized inventory UI contains upload and instructions modals with authoritative columns.
     */
    public function test_inventory_ui_contains_upload_and_instructions_modals(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.inventory.index'));

        $response->assertStatus(200);
        // Upload modal
        $response->assertSee('id="uploadCsvModal"', false);
        $response->assertSee(route('admin.inventory.import'));
        $response->assertSee('name="csv_file"', false);

        // Instructions modal
        $response->assertSee('id="importInstructionsModal"', false);
        $response->assertSee('CSV Import Instructions');
        $response->assertSee('name,category,is_perishable,current_stock,unit_cost,min_stock,unit');
        $response->assertSee('Existing items are matched by name');
        $response->assertSee('New items are created automatically');
    }
}

