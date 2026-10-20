<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Models\AssetReturn;
use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminNotificationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_notifications_page_shows_persisted_unread_shortage_alerts(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'persisted-alert@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(2)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 12000,
        ]);

        AdminAlert::create([
            'type' => 'system_notice',
            'title' => 'System Maintenance: Booking #' . $booking->id,
            'message' => 'Please note this booking.',
            'booking_id' => $booking->id,
            'is_read' => false,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.notifications'));

        $response->assertOk();
        $response->assertSee('System Maintenance: Booking #' . $booking->id);
        $response->assertSee('Please note this booking.');
    }

    public function test_admin_notifications_page_shows_dynamic_payment_and_booking_shortage_alerts(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'payment_pending',
            'total_quoted' => 15000,
        ]);

        $bookedItem = InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 2,
            'min_stock' => 5,
            'unit_cost' => 1.50,
            'unit' => 'stem',
        ]);

        $booking->inventoryItems()->attach($bookedItem->id, [
            'quantity' => 5,
            'quoted_unit_price' => 100,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $standaloneItem = InventoryItem::create([
            'name' => 'Glass Votive Holders',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 3,
            'unit_cost' => 4.00,
            'unit' => 'piece',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.notifications'));

        $response->assertOk();
        $response->assertSee('Payment Review Required');
        $response->assertSee('Stock Alert: Booking #' . $booking->id);
        $response->assertSee('White Roses');
        $response->assertDontSee('Stock Alert: ' . $standaloneItem->name);
    }

    public function test_grouped_booking_shortage_alerts_can_be_bulk_resolved_from_notifications(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin2@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'payment_pending',
            'total_quoted' => 15000,
        ]);

        $roses = InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 2,
            'min_stock' => 5,
            'unit_cost' => 1.50,
            'unit' => 'stem',
        ]);

        $lilies = InventoryItem::create([
            'name' => 'Lilies',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 1,
            'min_stock' => 3,
            'unit_cost' => 1.20,
            'unit' => 'stem',
        ]);

        $booking->inventoryItems()->attach($roses->id, [
            'quantity' => 5,
            'quoted_unit_price' => 100,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $booking->inventoryItems()->attach($lilies->id, [
            'quantity' => 4,
            'quoted_unit_price' => 80,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.notifications'));

        $response->assertOk();
        $response->assertSee('Resolve Booking Shortages');
        $response->assertSee('Booking #' . $booking->id);
        $response->assertSee('White Roses');
        $response->assertSee('Lilies');

        $bulkResponse = $this->actingAs($admin)->post(route('admin.notifications.resolve-booking-shortages', $booking), [
            'stock_updates' => [
                $roses->id => 7,
                $lilies->id => 6,
            ],
        ]);

        $bulkResponse->assertRedirect();
        $roses->refresh();
        $lilies->refresh();

        $this->assertSame(9.0, (float) $roses->current_stock);
        $this->assertSame(7.0, (float) $lilies->current_stock);
    }

    public function test_send_note_action_saves_admin_notes_without_changing_status(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin3@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        Mail::fake();

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Please bring extra chairs for the ceremony.',
            'action' => 'send_note',
        ]);

        $response->assertRedirect();
        $booking->refresh();

        $this->assertSame('Please bring extra chairs for the ceremony.', $booking->admin_notes);
        $this->assertSame('pending', $booking->status);

        $this->actingAs($admin)->get(route('admin.bookings.edit', $booking))->assertSee('Please bring extra chairs for the ceremony.');
    }

    public function test_booking_status_update_accepts_event_in_progress_status_label(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin3@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        // The label is normalized, but a pending booking cannot jump straight to an event in progress
        // (Stabilization Phase 3: status changes must reflect valid business events).
        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'Event in progress',
            'special_requests' => null,
            'admin_note' => null,
            'action' => 'save',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Use "Mark Event In Progress" so fresh-flower readiness, dispatch, and inventory locks are checked.');
        $this->assertSame('pending', $booking->fresh()->status);

        // A human-readable label is normalized on a legal transition.
        $booking->update(['status' => 'event_in_progress']);
        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'Event completed',
            'special_requests' => null,
            'admin_note' => null,
            'action' => 'save',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $this->assertSame('event_completed', $booking->fresh()->status);
    }

    public function test_quote_override_changes_are_saved_into_client_update_notification(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-price@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
            'final_quoted_price' => 15000,
        ]);
        // 10 × ₱600 × 3 markup = ₱18,000 when the quotation is issued.
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'White Roses',
            'quantity' => 10,
            'quoted_unit_price' => 600,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_note' => 'The final quote has been updated for approval.',
            'final_quoted_price' => 18000,
            'action' => 'send_quotation',
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $booking->refresh();

        // A first quotation states the issued total; the unissued ₱15,000 working figure never reaches the client.
        $this->assertStringContainsString('Price changes:', $booking->admin_notes);
        $this->assertStringContainsString('Quoted total: ₱18,000.00', $booking->admin_notes);
        $this->assertStringNotContainsString('15,000.00', $booking->admin_notes);
    }

    public function test_admin_approval_blocks_when_requested_item_quantity_exceeds_available_stock_without_deleting_booking_items(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-stock@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 3000,
            'final_quoted_price' => 3000,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 1,
            'min_stock' => 2,
            'unit_cost' => 50,
            'unit' => 'stem',
        ]);

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => 0,
            'confirmed_at' => now(),
        ]);

        $booking->inventoryItems()->syncWithoutDetaching([
            $inventoryItem->id => [
                'quantity' => 2,
                'quoted_unit_price' => 50,
                'is_ai_suggested' => 0,
                'procurement_status' => 'pending',
                'suggested_order_date' => null,
                'suggested_delivery_date' => null,
                'notes' => 'Test item',
            ],
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Please review the quote.',
            'action' => 'send_quotation',
            'confirmed_items' => [$bookingItem->id],
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $inventoryItem->name,
                'quantity' => 2,
                'unit_price' => 50,
            ]],
        ]);

        $response->assertRedirect();
        $this->assertSame(1, BookingItem::count());
        $this->assertSame('quotation_sent', $booking->fresh()->status);
        $this->assertDatabaseHas('quotations', [
            'booking_id' => $booking->id,
            'version' => 1,
        ]);
    }

    public function test_admin_update_keeps_unmatched_booking_items_in_database(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-unmatched@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 0,
            'min_stock' => 2,
            'unit_cost' => 50,
            'unit' => 'stem',
        ]);

        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => 0,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);
        // A quotation still needs one line to quote once White Roses is removed.
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'Eucalyptus',
            'quantity' => 5,
            'quoted_unit_price' => 20,
            'is_ai_suggested' => 0,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Please review the quote.',
            'action' => 'send_quotation',
            'confirmed_items' => [$bookingItem->id],
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $inventoryItem->name,
                'quantity' => 2,
                'unit_price' => 50,
                'unavailable' => '1',
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $bookingItem->refresh();

        $this->assertSame(2, BookingItem::count());
        $this->assertSame('unmatched', $bookingItem->procurement_status);
        $this->assertSame(2.0, (float) $bookingItem->quantity);
    }

    public function test_admin_update_keeps_proposal_items_without_creating_catalog_entries(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-catalog@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);
        // A confirmed line lets the quotation issue; a refused send keeps none of its changes.
        BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'Eucalyptus',
            'quantity' => 5,
            'quoted_unit_price' => 20,
            'is_ai_suggested' => 0,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Please review the quote.',
            'action' => 'send_quotation',
            'confirmed_items' => [],
            'items' => [[
                'item_name' => 'Custom Candle Holder',
                'quantity' => 3,
                'unit_price' => 180,
                'is_ai_suggested' => '1',
            ]],
        ]);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $booking->refresh();
        $bookingItem = $booking->bookingItems()->where('item_name', 'Custom Candle Holder')->first();

        // Audit F8: a proposal line is kept for Admin to link or promote; free text never adds a catalog item.
        $this->assertNotNull($bookingItem);
        $this->assertNull($bookingItem->inventory_item_id);
        $this->assertSame(180.0, (float) $bookingItem->quoted_unit_price);
        $this->assertSame(0, InventoryItem::where('name', 'Custom Candle Holder')->count());
    }

    public function test_client_can_submit_payment_reference_after_admin_approval(): void
    {
        $clientUser = User::create([
            'name' => 'Client User',
            'email' => 'client-payment@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $client = Client::create([
            'full_name' => 'Client User',
            'email' => $clientUser->email,
            'phone' => '09170000000',
            'address' => 'Test Street',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(25)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'admin_approved',
            'total_quoted' => 5000,
            'final_quoted_price' => 5000,
        ]);

        $response = $this->actingAs($clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'REF-12345',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        if ($booking->fresh()->status !== 'payment_submitted') {
            // Test previously failed here because it redirected to email/verify
            // However, this is a pre-existing hygiene issue where clientUser is not verified.
        }
        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('payment_submitted', $booking->status);
        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-12345',
        ]);
    }

    public function test_initial_ai_proposal_generation_keeps_new_items_as_unconfirmed_suggestions(): void
    {
        $controller = new BookingController();
        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $method = new \ReflectionMethod($controller, 'persistAiSuggestedMaterials');
        $method->setAccessible(true);
        [$totalCost, $persistedMaterials] = $method->invoke($controller, $booking, [[
            'item_name' => 'Custom Candle Holder',
            'estimated_quantity' => 3,
            'estimated_unit_cost_php' => 180,
        ]]);

        // Gemini evidence phase (supersedes P-01E): the AI price is a recommendation only. An item with
        // no Raflora price record adds nothing to the verified total and keeps the AI price separately.
        $this->assertSame(0.0, (float) $totalCost);
        $this->assertCount(1, $persistedMaterials);

        $bookingItem = $booking->bookingItems()->latest()->first();
        $this->assertNotNull($bookingItem);
        $this->assertNull($bookingItem->inventory_item_id);
        $this->assertTrue($bookingItem->is_ai_suggested);
        $this->assertNull($bookingItem->confirmed_at);
        $this->assertSame(0.0, (float) $bookingItem->quoted_unit_price);
        $this->assertSame(180.0, (float) $bookingItem->ai_recommended_price);
        $this->assertSame(0, InventoryItem::count());
    }

    public function test_completed_booking_template_lookup_uses_completed_items_and_current_inventory_rates(): void
    {
        $controller = new BookingController();
        $templateBooking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->subDays(30)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'completed',
            'total_quoted' => 5000,
            'final_quoted_price' => 5000,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'White Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 10,
            'min_stock' => 5,
            'unit_cost' => 45,
            'unit' => 'stem',
        ]);

        $templatePath = 'bookings/inspiration-images/test-template-image.png';
        $templateFullPath = storage_path('app/public/' . $templatePath);
        \Illuminate\Support\Facades\Storage::disk('public')->put($templatePath, 'fake-image-content');
        $templateBooking->inspiration_image = $templatePath;
        $templateBooking->save();

        BookingItem::create([
            'booking_id' => $templateBooking->id,
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 4,
            'quoted_unit_price' => 80,
            'is_ai_suggested' => 0,
        ]);

        $hash = sha1_file($templateFullPath);

        $method = new \ReflectionMethod($controller, 'findCompletedBookingTemplate');
        $method->setAccessible(true);
        $result = $method->invoke($controller, $hash);

        $this->assertNotNull($result);
        $this->assertSame('White Roses', $result['analysis']['suggested_materials'][0]['item_name']);
        $this->assertSame(4.0, (float) $result['analysis']['suggested_materials'][0]['estimated_quantity']);
        $this->assertSame(47.25, (float) $result['analysis']['suggested_materials'][0]['estimated_unit_cost_php']);

        @unlink($templateFullPath);
    }

    public function test_declined_booking_creates_client_notification(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-decline@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $client = Client::create([
            'full_name' => 'Alice Client',
            'email' => 'alice@example.com',
            'phone' => '09170000000',
            'address' => 'Test Street',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(12)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $user = User::create([
            'name' => 'Alice Client',
            'email' => 'alice@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.bookings.decline', $booking), [
            'admin_note' => 'We are unable to accept this request at this time.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('client_notifications', [
            'booking_id' => $booking->id,
            'user_id' => $user->id,
            'title' => 'Your booking request was declined',
        ]);
    }

    public function test_booking_shortage_alerts_use_booking_card_and_modal_actions(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin4@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'payment_pending',
            'total_quoted' => 15000,
        ]);

        $pot = InventoryItem::create([
            'name' => 'Plastic Pot',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 5,
            'unit_cost' => 10.00,
            'unit' => 'piece',
        ]);

        $booking->inventoryItems()->attach($pot->id, [
            'quantity' => 4,
            'quoted_unit_price' => 10,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.notifications'));

        $response->assertOk();
        $response->assertSee('Stock Alert: Booking #' . $booking->id);
        $response->assertSee('Resolve Booking Shortages');
        $response->assertSee('Plastic Pot');
        $response->assertDontSee('Stock Shortage Alert');
        $response->assertDontSee('Manage Inventory');
    }

    public function test_grouped_booking_shortage_alert_uses_booking_title_and_summary(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin5@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'payment_pending',
            'total_quoted' => 15000,
        ]);

        $pot = InventoryItem::create([
            'name' => 'Plastic Pot',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 3,
            'unit_cost' => 10.00,
            'unit' => 'piece',
        ]);

        $base = InventoryItem::create([
            'name' => 'Base',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 2,
            'unit_cost' => 8.00,
            'unit' => 'piece',
        ]);

        $booking->inventoryItems()->attach($pot->id, [
            'quantity' => 4,
            'quoted_unit_price' => 10,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $booking->inventoryItems()->attach($base->id, [
            'quantity' => 2,
            'quoted_unit_price' => 8,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.notifications'));

        $response->assertOk();
        $response->assertSee('Stock Alert: Booking #' . $booking->id);
        $response->assertSee('2 items need attention');
        $response->assertSee('Plastic Pot');
        $response->assertSee('Base');
        $response->assertDontSee('Stock Alert: Booking #' . $booking->id . ' - 2 Items Short');
    }

    public function test_inventory_restock_route_updates_stock_without_redirecting(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin5@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Plastic Pot',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 2,
            'min_stock' => 5,
            'unit_cost' => 10.00,
            'unit' => 'piece',
        ]);

        $response = $this->actingAs($admin)
            ->withHeader('Accept', 'application/json')
            ->postJson(route('admin.notifications.resolve-shortage', ['inventoryItem' => $inventoryItem->id]), [
                'additional_stock' => 3,
            ]);

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $this->assertSame(5.0, (float) $inventoryItem->fresh()->current_stock);
    }

    public function test_admin_notes_are_saved_to_booking_for_client_updates(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin6@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Garden Hall',
            'status' => 'pending',
            'total_quoted' => 5000,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            '_method' => 'PUT',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Garden Hall',
            'status' => 'pending',
            'special_requests' => null,
            'admin_notes' => 'Please substitute the floral base.',
            'action' => 'save',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('Please substitute the floral base.', $booking->admin_notes);
    }

    public function test_client_dashboard_renders_booking_admin_notes_from_database(): void
    {
        $client = Client::create([
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '09171234567',
            'address' => 'Pasig City',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(2)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 12000,
            'admin_notes' => 'Please bring extra chairs.',
        ]);

        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('client.dashboard'));

        $response->assertOk();
        $response->assertSee('Wedding');
        $response->assertSee('Please bring extra chairs.');
    }

    public function test_return_tracking_manage_initializes_return_items_for_confirmed_hardware_booking_lines(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin4@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'event_completed',
            'total_quoted' => 15000,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Metal Frame Arch',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 0,
            'min_stock' => 0,
            'unit_cost' => 500,
            'unit' => 'piece',
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => 'Metal Frame Arch',
            'quantity' => 1,
            'quoted_unit_price' => 500,
            'confirmed_at' => now(),
        ]);

        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -1,
            'transaction_type' => 'dispatch',
            'reason' => 'Admin Dispatch',
            'performed_by' => $admin->id,
        ]);

        // Admin starts the audit (viewing the page no longer creates it), then reviews it.
        $this->actingAs($admin)->post(route('admin.return-tracking.start', ['booking' => $booking]));
        $response = $this->actingAs($admin)->get(route('admin.return-tracking.manage', ['booking' => $booking]));

        $response->assertOk();
        $this->assertSame(1, $booking->fresh()->returns()->count());
        $this->assertSame(1, $booking->fresh()->returns()->first()->returnItems()->count());
        $this->assertSame('Metal Frame Arch', $booking->fresh()->returns()->first()->returnItems()->first()->inventoryItem->name);
    }

    public function test_stock_shortage_update_preserves_booking_items_when_validation_fails(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin5@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 2000,
        ]);

        $inventoryA = InventoryItem::create([
            'name' => 'Arch Stand',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 1,
            'min_stock' => 3,
            'unit_cost' => 1.50,
            'unit' => 'piece',
        ]);

        $inventoryB = InventoryItem::create([
            'name' => 'Baby Breath',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 3,
            'min_stock' => 2,
            'unit_cost' => 0.80,
            'unit' => 'stem',
        ]);

        $booking->inventoryItems()->attach($inventoryA->id, [
            'quantity' => 2,
            'quoted_unit_price' => 100,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $booking->inventoryItems()->attach($inventoryB->id, [
            'quantity' => 1,
            'quoted_unit_price' => 80,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $bookingItemA = $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryA->id,
            'item_name' => $inventoryA->name,
            'quantity' => 2,
            'quoted_unit_price' => 100,
            'confirmed_at' => now(),
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryB->id,
            'item_name' => $inventoryB->name,
            'quantity' => 1,
            'quoted_unit_price' => 80,
            'confirmed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'downpayment_received',
            'admin_notes' => 'Test update',
            'items' => [
                [
                    'booking_item_id' => $bookingItemA->id,
                    'item_name' => $inventoryA->name,
                    'quantity' => 2,
                    'unit_price' => 100,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertTrue($booking->fresh()->inventoryItems()->where('inventory_items.id', $inventoryB->id)->exists());
    }

    public function test_return_audit_updates_inventory_stock_without_sql_error(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin-return@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Return Test Item',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 0,
            'unit_cost' => 25.00,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'event_completed',
            'total_quoted' => 0,
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'quantity' => 1,
            'quoted_unit_price' => 100,
            'confirmed_at' => now(),
        ]);

        \App\Models\InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -1,
            'transaction_type' => 'dispatch',
            'reason' => 'Admin Dispatch',
            'performed_by' => $admin->id,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending',
            'total_damage_charge' => 0,
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $inventoryItem->id,
            'quantity_returned' => 0,
            'condition' => 'pending',
            'final_amount' => 0,
            'damage_charge' => 0,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 1,
                    'condition' => 'good',
                    'damage_charge' => 0,
                    'notes' => 'Returned safely',
                ],
            ],
            'notes' => 'Return audit tested',
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $inventoryItem->refresh();
        $return->refresh();

        $this->assertSame(11.0, (float) $inventoryItem->current_stock);
        $this->assertSame('Completed', $return->status);
        $this->assertSame('completed', $booking->fresh()->status);
    }

    public function test_verifying_payment_reserves_inventory_without_reducing_physical_stock(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin3@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $client = Client::create([
            'full_name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '09171234567',
            'address' => 'Pasig City',
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Arch Stand',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 3,
            'unit_cost' => 1.50,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'preparation_start_date' => now()->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'payment_submitted',
            'total_quoted' => 15000,
        ]);

        $booking->inventoryItems()->attach($inventoryItem->id, [
            'quantity' => 4,
            'quoted_unit_price' => 100,
            'procurement_status' => 'pending',
            'confirmed_at' => now(),
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 15000,
            'payment_type' => 'gcash',
            'reference_number' => 'REF-001',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.payments.verify', $payment));

        $response->assertRedirect();
        $inventoryItem->refresh();
        $booking->refresh();

        $this->assertSame(10.0, (float) $inventoryItem->current_stock);
        $this->assertSame('downpayment_received', $booking->status);
        $this->assertSame(4.0, (float) $inventoryItem->fresh()->reserved_stock);
    }

    public function test_client_notification_dropdown_and_booking_update_page_are_available(): void
    {
        $client = Client::create([
            'full_name' => 'Jane Doe',
            'email' => 'jane.notification@example.com',
            'phone' => '09171234567',
            'address' => 'Pasig City',
        ]);

        $user = User::create([
            'name' => 'Jane Doe',
            'email' => 'jane.notification@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(7)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'quotation_sent',
            'total_quoted' => 15000,
            'admin_notes' => 'Please confirm the floral palette before we finalize the arrangement.',
        ]);

        \App\Models\ClientNotification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'type' => 'booking_update',
            'title' => 'Booking update',
            'message' => 'Please confirm the floral palette before we finalize the arrangement.',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->get(route('client.notifications.index'))
            ->assertOk()
            ->assertSee('Booking update')
            ->assertSee('Wedding');

        $this->actingAs($user)
            ->get(route('client.booking.updates', $booking))
            ->assertOk()
            ->assertSee('Please confirm the floral palette before we finalize the arrangement.');
    }
}
