<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\PackageImage;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPackageCatalogueAndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->clientUser = User::factory()->create(['role' => 'client']);
        $this->client = Client::firstOrCreate(
            ['email' => $this->clientUser->email],
            [
                'full_name' => $this->clientUser->name ?? 'Jane Client',
                'phone' => '09171234567',
            ]
        );
        Storage::fake('public');
    }

    public function test_01_admin_package_page_loads(): void
    {
        $pkg = Package::create([
            'title' => 'Ruby Celebration',
            'category' => 'Anniversary',
            'price' => 18000,
            'description' => 'A romantic celebration bundle',
            'is_active' => true,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.index'));

        $response->assertOk();
        $response->assertSee('Package Management');
        $response->assertSee('Ruby Celebration');
        $response->assertSee('Anniversary');
        $response->assertSee('View Archived');
        $response->assertSee('Add Package');
        $response->assertSee('Grid');
        $response->assertSee('Table');
    }

    public function test_02_admin_can_create_package(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Pastel Dream Debut',
            'category' => 'Debut',
            'price' => 28000,
            'description' => 'A soft floral package for debutantes',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Package created successfully.');

        $this->assertDatabaseHas('packages', [
            'title' => 'Pastel Dream Debut',
            'category' => 'Debut',
            'price' => 28000,
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    public function test_03_admin_can_edit_package(): void
    {
        $pkg = Package::create([
            'title' => 'Initial Title',
            'category' => 'Wedding',
            'price' => 20000,
            'description' => 'Initial description',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg), [
            'title' => 'Updated Deluxe Title',
            'category' => 'Wedding',
            'price' => 26000,
            'description' => 'Updated deluxe floral description',
            'is_active' => '1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Package updated successfully.');

        $pkg->refresh();
        $this->assertEquals('Updated Deluxe Title', $pkg->title);
        $this->assertEquals(26000.00, (float) $pkg->price);
        $this->assertEquals('Updated deluxe floral description', $pkg->description);
    }

    public function test_04_admin_can_create_package_with_category(): void
    {
        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Corporate Gala Package',
            'category' => 'Corporate',
            'price' => 35000,
        ]);

        $pkg = Package::where('title', 'Corporate Gala Package')->first();
        $this->assertNotNull($pkg);
        $this->assertEquals('Corporate', $pkg->category);
    }

    public function test_05_admin_can_update_category(): void
    {
        $pkg = Package::create([
            'title' => 'Versatile Floral Set',
            'category' => 'General',
            'price' => 15000,
        ]);

        $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg), [
            'title' => 'Versatile Floral Set',
            'category' => 'Birthday',
            'price' => 15000,
        ]);

        $pkg->refresh();
        $this->assertEquals('Birthday', $pkg->category);
    }

    public function test_06_client_facing_inclusions_persisted_correctly(): void
    {
        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Inclusion Test Package',
            'category' => 'Wedding',
            'price' => 22000,
            'included_items' => ['Bridal Bouquet', 'Groom Boutonniere', 'Stage Floral Arch'],
        ]);

        $pkg = Package::where('title', 'Inclusion Test Package')->first();
        $this->assertNotNull($pkg);
        $this->assertIsArray($pkg->included_items);
        $this->assertCount(3, $pkg->included_items);
        $this->assertContains('Bridal Bouquet', $pkg->included_items);
        $this->assertContains('Stage Floral Arch', $pkg->included_items);
    }

    public function test_07_bom_mappings_persist_correctly(): void
    {
        $rose = InventoryItem::create(['name' => 'White Rose', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 100]);
        $vase = InventoryItem::create(['name' => 'Glass Vase', 'category' => 'Vases', 'unit' => 'pcs', 'current_stock' => 20]);

        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'BOM Test Package',
            'category' => 'Wedding',
            'price' => 19000,
            'inventory_items' => [
                $rose->id => 24,
                $vase->id => 4,
            ],
        ]);

        $pkg = Package::where('title', 'BOM Test Package')->first();
        $this->assertNotNull($pkg);
        $this->assertCount(2, $pkg->inventoryItems);
        $this->assertTrue($pkg->inventoryItems->contains('id', $rose->id));
        $this->assertTrue($pkg->inventoryItems->contains('id', $vase->id));
    }

    public function test_08_bom_quantities_persist_correctly(): void
    {
        $rose = InventoryItem::create(['name' => 'Ecuadorian Rose', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 80]);

        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Quantity Check Package',
            'category' => 'Wedding',
            'price' => 25000,
            'inventory_items' => [
                $rose->id => 36,
            ],
        ]);

        $pkg = Package::where('title', 'Quantity Check Package')->first();
        $attached = $pkg->inventoryItems()->where('inventory_item_id', $rose->id)->first();
        $this->assertNotNull($attached);
        $this->assertEquals(36, (float) $attached->pivot->quantity);
    }

    public function test_09_editing_without_changes_does_not_clear_bom(): void
    {
        $rose = InventoryItem::create(['name' => 'Pink Carnation', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 50]);
        $pkg = Package::create(['title' => 'Carnation Bundle', 'category' => 'Flowers', 'price' => 5000]);
        $pkg->inventoryItems()->sync([$rose->id => ['quantity' => 15]]);

        $response = $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg), [
            'title' => 'Carnation Bundle',
            'category' => 'Flowers',
            'price' => 5000,
            'inventory_items' => [$rose->id => 15],
        ]);

        $response->assertSessionHas('info', 'No changes were made to this package.');

        $pkg->refresh();
        $this->assertCount(1, $pkg->inventoryItems);
        $this->assertEquals(15, (float) $pkg->inventoryItems->first()->pivot->quantity);
    }

    public function test_10_invalid_bom_item_ids_rejected(): void
    {
        $response = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Invalid BOM Package',
            'category' => 'Wedding',
            'price' => 10000,
            'inventory_items' => [99999 => 5],
        ]);

        $response->assertSessionHasErrors('inventory_items.99999');
        $this->assertDatabaseMissing('packages', ['title' => 'Invalid BOM Package']);
    }

    public function test_11_zero_or_negative_bom_quantities_rejected(): void
    {
        $item = InventoryItem::create(['name' => 'Sunflower', 'category' => 'Flowers', 'unit' => 'stems']);

        $responseZero = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Zero BOM Package',
            'category' => 'Wedding',
            'price' => 10000,
            'inventory_items' => [$item->id => 0],
        ]);
        $responseZero->assertSessionHasErrors('inventory_items.' . $item->id);

        $responseNeg = $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Negative BOM Package',
            'category' => 'Wedding',
            'price' => 10000,
            'inventory_items' => [$item->id => -3],
        ]);
        $responseNeg->assertSessionHasErrors('inventory_items.' . $item->id);
    }

    public function test_12_package_image_upload_works(): void
    {
        $file = UploadedFile::fake()->create('wedding_bundle.jpg', 120, 'image/jpeg');

        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Photo Package',
            'category' => 'Wedding',
            'price' => 15000,
            'images' => [$file],
        ]);

        $pkg = Package::where('title', 'Photo Package')->first();
        $this->assertNotNull($pkg);
        $this->assertCount(1, $pkg->images);
        $imageRecord = $pkg->images->first();
        Storage::disk('public')->assertExists($imageRecord->image_path);
    }

    public function test_13_package_image_replacement_works(): void
    {
        $file1 = UploadedFile::fake()->create('old_photo.jpg', 120, 'image/jpeg');
        $file2 = UploadedFile::fake()->create('new_photo.jpg', 120, 'image/jpeg');

        $this->actingAs($this->admin)->post(route('admin.packages.store'), [
            'title' => 'Replace Image Package',
            'category' => 'Wedding',
            'price' => 15000,
            'images' => [$file1],
        ]);

        $pkg = Package::where('title', 'Replace Image Package')->first();
        $oldImage = $pkg->images->first();
        $oldPath = $oldImage->image_path;

        $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg), [
            'title' => 'Replace Image Package',
            'category' => 'Wedding',
            'price' => 15000,
            'remove_images' => (string) $oldImage->id,
            'images' => [$file2],
        ]);

        $pkg->refresh();
        Storage::disk('public')->assertMissing($oldPath);
        $this->assertCount(1, $pkg->images);
        $newImage = $pkg->images->first();
        $this->assertNotEquals($oldPath, $newImage->image_path);
        Storage::disk('public')->assertExists($newImage->image_path);
    }

    public function test_14_archive_changes_is_active_to_false_and_is_archived_to_true(): void
    {
        $pkg = Package::create([
            'title' => 'Package to Archive',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.archive', $pkg));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Package archived successfully.');

        $pkg->refresh();
        $this->assertTrue($pkg->is_archived);
        $this->assertFalse($pkg->is_active);
    }

    public function test_15_restore_changes_is_active_to_true_and_is_archived_to_false(): void
    {
        $pkg = Package::create([
            'title' => 'Archived Package to Restore',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => false,
            'is_archived' => true,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.packages.restore', $pkg));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Package restored successfully.');

        $pkg->refresh();
        $this->assertFalse($pkg->is_archived);
        $this->assertTrue($pkg->is_active);
    }

    public function test_16_archived_package_does_not_appear_publicly(): void
    {
        Package::create([
            'title' => 'Public Active Bloom',
            'category' => 'Wedding',
            'price' => 15000,
            'is_active' => true,
            'is_archived' => false,
        ]);
        Package::create([
            'title' => 'Hidden Archived Bloom',
            'category' => 'Wedding',
            'price' => 15000,
            'is_active' => false,
            'is_archived' => true,
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertOk();
        $response->assertSee('Public Active Bloom');
        $response->assertDontSee('Hidden Archived Bloom');
    }

    public function test_17_archived_package_cannot_be_selected_for_new_preset_booking(): void
    {
        $archivedPkg = Package::create([
            'title' => 'Archived Template',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => false,
            'is_archived' => true,
        ]);

        // Public catalogue hides archived packages
        $response = $this->get(route('packages.index'));
        $response->assertDontSee('Archived Template');
    }

    public function test_18_existing_booking_retains_package_snapshot_after_package_edit(): void
    {
        $rose = InventoryItem::create(['name' => 'Red Rose', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 100, 'unit_cost' => 25]);
        $pkg = Package::create([
            'title' => 'Classic Red Romance',
            'category' => 'Wedding',
            'price' => 25000,
            'is_active' => true,
            'is_archived' => false,
            'included_items' => ['20 x Red Rose', 'Bridal Bouquet'],
        ]);
        $pkg->inventoryItems()->sync([$rose->id => ['quantity' => 20]]);

        // Client books preset package
        $response = $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $pkg->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Manila Cathedral',
            'guest_count' => 100,
            'table_count' => 10,
        ]);
        $response->assertRedirect();

        $booking = Booking::where('package_id', $pkg->id)->latest()->first();
        $this->assertNotNull($booking);
        $this->assertEquals(25000.00, (float) $booking->final_quoted_price);

        $initialBookingItem = $booking->bookingItems()->where('inventory_item_id', $rose->id)->first();
        $this->assertNotNull($initialBookingItem);
        $this->assertEquals(20, (float) $initialBookingItem->quantity);

        // Admin subsequently edits package BOM and price
        $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg), [
            'title' => 'Classic Red Romance Deluxe',
            'category' => 'Wedding',
            'price' => 32000,
            'inventory_items' => [$rose->id => 30],
        ]);

        // Historical booking MUST retain its snapshot!
        $booking->refresh();
        $this->assertEquals(25000.00, (float) $booking->final_quoted_price);
        $recheckedItem = $booking->bookingItems()->where('inventory_item_id', $rose->id)->first();
        $this->assertEquals(20, (float) $recheckedItem->quantity);
    }

    public function test_19_package_bom_change_applies_only_to_new_bookings(): void
    {
        $stem = InventoryItem::create(['name' => 'White Lily', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 50, 'unit_cost' => 30]);
        $pkg = Package::create([
            'title' => 'Lily Serenity',
            'category' => 'Wedding',
            'price' => 10000,
            'is_active' => true,
        ]);
        $pkg->inventoryItems()->sync([$stem->id => ['quantity' => 10]]);

        // Booking 1 created
        $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $pkg->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'San Agustin Church',
        ]);
        $booking1 = Booking::where('package_id', $pkg->id)->latest('id')->first();

        // Admin updates package BOM to 18 lilies
        $this->actingAs($this->admin)->put(route('admin.packages.update', $pkg), [
            'title' => 'Lily Serenity',
            'category' => 'Wedding',
            'price' => 14000,
            'inventory_items' => [$stem->id => 18],
        ]);

        // Booking 2 created
        $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $pkg->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(25)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Sofitel Grand Plaza',
        ]);
        $booking2 = Booking::where('package_id', $pkg->id)->latest('id')->first();

        $itemBooking1 = $booking1->bookingItems()->where('inventory_item_id', $stem->id)->first();
        $itemBooking2 = $booking2->bookingItems()->where('inventory_item_id', $stem->id)->first();

        $this->assertEquals(10, (float) $itemBooking1->quantity);
        $this->assertEquals(18, (float) $itemBooking2->quantity);
    }

    public function test_20_guest_preset_booking_still_receives_package_data(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $pkg = Package::create([
            'title' => 'Guest Preset Special',
            'category' => 'Debut',
            'price' => 20000,
            'is_active' => true,
            'is_archived' => false,
            'included_items' => ['Bouquet', 'Arch'],
        ]);

        $response = $this->post(route('guest.booking.store'), [
            'booking_type' => 'preset',
            'package_id' => $pkg->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '17:00',
            'end_time' => '22:00',
            'guest_name' => 'Maria Guest',
            'guest_email' => 'maria@example.com',
            'guest_phone' => '09181234567',
            'guest_address' => '123 Fake St, Makati',
            'venue' => 'Makati Shangri-La',
            'venue_city' => 'Makati City',
            'venue_specific' => 'Ballroom A',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $temp = TemporaryGuestBooking::where('package_id', $pkg->id)->first();
        $this->assertNotNull($temp);
        $this->assertEquals('Maria Guest', $temp->guest_name);
        $this->assertEquals($pkg->id, $temp->package_id);
    }

    public function test_21_guest_package_claim_still_works(): void
    {
        $stem = InventoryItem::create(['name' => 'Tulip', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 40, 'unit_cost' => 50]);
        $pkg = Package::create([
            'title' => 'Tulip Extravaganza',
            'category' => 'Anniversary',
            'price' => 30000,
            'is_active' => true,
        ]);
        $pkg->inventoryItems()->sync([$stem->id => ['quantity' => 25]]);

        $rawToken = 'guest-token-123456789012345678901234567890';
        $this->clientUser->update(['email_verified_at' => now()]);
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'booking_type' => 'preset',
            'package_id' => $pkg->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'guest_name' => 'Jane Client',
            'guest_email' => $this->clientUser->email,
            'guest_phone' => '09171234567',
            'guest_address' => '123 Fake St',
            'venue' => 'Grand Hyatt',
            'venue_city' => 'Taguig',
            'expires_at' => now()->addHours(24),
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));
        $response->assertRedirect();

        $claimedBooking = Booking::where('client_id', $this->client->id)->where('package_id', $pkg->id)->first();
        $this->assertNotNull($claimedBooking);
        $this->assertEquals(30000.00, (float) $claimedBooking->final_quoted_price);
        $claimedItem = $claimedBooking->bookingItems()->where('inventory_item_id', $stem->id)->first();
        $this->assertNotNull($claimedItem);
        $this->assertEquals(25, (float) $claimedItem->quantity);
    }

    public function test_22_package_without_bom_still_preserves_existing_fallback_behavior(): void
    {
        $pkgNoBom = Package::create([
            'title' => 'Pure Service Package',
            'category' => 'Corporate',
            'price' => 12000,
            'is_active' => true,
            'included_items' => ['Consultation', 'Venue Styling'],
        ]);

        $response = $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $pkgNoBom->id,
            'event_type' => 'corporate',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'BGC Taguig',
        ]);

        $response->assertRedirect();
        $booking = Booking::where('package_id', $pkgNoBom->id)->latest()->first();
        $this->assertNotNull($booking);

        // Fallback booking item should be created
        $fallbackItem = $booking->bookingItems()->where('inventory_item_id', null)->first();
        $this->assertNotNull($fallbackItem);
        $this->assertEquals('Consultation', $fallbackItem->item_name);
    }

    public function test_23_package_with_bom_plus_textual_inclusions_does_not_produce_unintended_duplicate_physical_inventory_requirements(): void
    {
        $rose = InventoryItem::create(['name' => 'Pink Rose', 'category' => 'Flowers', 'unit' => 'stems', 'current_stock' => 100, 'unit_cost' => 20]);
        $pkg = Package::create([
            'title' => 'Harmonious Wedding',
            'category' => 'Wedding',
            'price' => 25000,
            'is_active' => true,
            'included_items' => ['20 x Pink Rose', 'Bridal Bouquet'],
        ]);
        $pkg->inventoryItems()->sync([$rose->id => ['quantity' => 20]]);

        $response = $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $pkg->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Fernwood Gardens',
        ]);

        $response->assertRedirect();
        $booking = Booking::where('package_id', $pkg->id)->latest()->first();
        $this->assertNotNull($booking);

        // There should be exactly 1 item representing Pink Rose with quantity 20
        $pinkRoseItems = $booking->bookingItems()->where('inventory_item_id', $rose->id)->get();
        $this->assertCount(1, $pinkRoseItems);
        $this->assertEquals(20, (float) $pinkRoseItems->first()->quantity);

        // Non-physical/unmatched inclusion "Bridal Bouquet" exists as null-inventory booking item
        $bouquetItem = $booking->bookingItems()->where('item_name', 'Bridal Bouquet')->first();
        $this->assertNotNull($bouquetItem);
        $this->assertNull($bouquetItem->inventory_item_id);
    }

    public function test_24_admin_authorization_works(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index'));
        $response->assertOk();
    }

    public function test_25_non_admin_package_mutation_is_rejected(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $pkg = Package::create(['title' => 'Protected Pkg', 'category' => 'Test', 'price' => 1000]);

        // Client forbidden
        $this->actingAs($this->clientUser)->post(route('admin.packages.store'), ['title' => 'Hack'])->assertForbidden();
        $this->actingAs($this->clientUser)->put(route('admin.packages.update', $pkg), ['title' => 'Hack'])->assertForbidden();
        $this->actingAs($this->clientUser)->post(route('admin.packages.archive', $pkg))->assertForbidden();
        $this->actingAs($this->clientUser)->post(route('admin.packages.restore', $pkg))->assertForbidden();

        // Staff forbidden
        $this->actingAs($staff)->post(route('admin.packages.store'), ['title' => 'Hack'])->assertForbidden();
        $this->actingAs($staff)->put(route('admin.packages.update', $pkg), ['title' => 'Hack'])->assertForbidden();
        $this->actingAs($staff)->post(route('admin.packages.archive', $pkg))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.packages.restore', $pkg))->assertForbidden();
    }

    public function test_26_permanent_deletion_is_blocked_when_historical_package_dependencies_exist(): void
    {
        $pkgWithBooking = Package::create([
            'title' => 'Historical Package With Bookings',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
        ]);

        Booking::create([
            'client_id' => $this->client->id,
            'package_id' => $pkgWithBooking->id,
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Manila Hotel',
            'status' => 'confirmed',
        ]);

        // Attempt permanent delete on package with booking dependency
        $response = $this->actingAs($this->admin)->delete(route('admin.packages.destroy', $pkgWithBooking));

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertDatabaseHas('packages', ['id' => $pkgWithBooking->id]);

        // Clean package without any bookings CAN be permanently deleted
        $cleanPkg = Package::create([
            'title' => 'Clean Unreferenced Package',
            'category' => 'Test',
            'price' => 5000,
            'is_active' => true,
        ]);

        $deleteResponse = $this->actingAs($this->admin)->delete(route('admin.packages.destroy', $cleanPkg));
        $deleteResponse->assertRedirect();
        $deleteResponse->assertSessionHas('success', 'Package permanently deleted.');
        $this->assertDatabaseMissing('packages', ['id' => $cleanPkg->id]);
    }
}
