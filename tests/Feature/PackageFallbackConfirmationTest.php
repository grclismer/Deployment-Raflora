<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\Booking;
use App\Models\BookingItem;

class PackageFallbackConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_package_fallback_items_are_automatically_confirmed()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create a package with textual included_items but no physical inventory linked
        $package = Package::create([
            'title' => 'Test Fallback Package',
            'description' => 'Test Description',
            'price' => 5000,
            'is_active' => true,
            'image_path' => 'test.jpg',
            'included_items' => ['10 x Red Roses', '1 x Vase']
        ]);

        $clientProfile = \App\Models\Client::create(['full_name' => 'Test Client', 'email' => 'john@example.com', 'phone' => '09170000000', 'address' => 'Address', 'user_id' => $admin->id]);

        $payload = [
            'client_id' => $clientProfile->id,
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'client_phone' => '1234567890',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Grand Hall',
            'delivery_address' => '123 Test St',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'status' => 'pending',
            'suggested_procurement_date' => now()->addDays(5)->toDateString()
        ];

        // Client bookings are created by client accounts (the client portal is client-only).
        $clientUser = User::factory()->create(['role' => 'client', 'email' => 'john@example.com']);
        $response = $this->actingAs($clientUser)->post('/client/bookings/create', $payload);
        
        file_put_contents(base_path('scratch/test_response.html'), $response->content());
        $response->assertSessionHasNoErrors();

        $booking = Booking::latest()->first();
        $this->assertNotNull($booking);

        $items = BookingItem::where('booking_id', $booking->id)->get();
        $this->assertCount(2, $items);

        foreach ($items as $item) {
            $this->assertNotNull($item->confirmed_at, "Package fallback item {$item->item_name} must be confirmed immediately");
        }

        // Test Quotation Issuance
        $quotationService = app(\App\Services\QuotationIssuanceService::class);
        $booking->status = 'change_requested';
        $booking->save();
        $quotation = $quotationService->issue($booking, $admin->id);
        
        $this->assertNotNull($quotation, 'Quotation should be successfully issued');
    }

    public function test_custom_and_ai_materials_remain_unconfirmed()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $booking = Booking::create([
            'client_name' => 'John Doe',
            'client_email' => 'john@example.com',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'booking_type' => 'custom',
            'status' => 'pending'
        ]);

        // Custom material added manually
        $customItem = BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'Custom Ribbon',
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => false,
            'procurement_status' => 'pending',
            // Omit confirmed_at deliberately to mimic standard custom addition behavior
        ]);
        
        $this->assertNull($customItem->confirmed_at);

        // AI suggested material
        $aiItem = BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'AI Extra Roses',
            'quantity' => 5,
            'quoted_unit_price' => 100,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending'
        ]);

        $this->assertNull($aiItem->confirmed_at);
    }

    public function test_guest_package_claim_items_are_confirmed()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create a package
        $package = Package::create([
            'title' => 'Guest Package',
            'price' => 5000,
            'is_active' => true,
            'image_path' => 'guest.jpg',
            'included_items' => ['10 x Guest Roses']
        ]);

        $tempBooking = new \App\Models\TemporaryGuestBooking([
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Guest Name',
            'guest_phone' => '09171234567',
            'guest_address' => 'Guest Address',
            'venue' => 'Guest Venue',
            'expires_at' => now()->addHours(24),
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'booking_type' => 'preset',
            'package_id' => $package->id,
        ]);
        $rawToken = $tempBooking->generateToken();
        $tempBooking->save();

        $client = User::factory()->create(['role' => 'client', 'email' => 'guest@example.com']);
        $clientProfile = \App\Models\Client::create(['full_name' => 'Guest Name', 'email' => 'guest@example.com', 'user_id' => $client->id]);

        $payload = [
            'client_name' => 'Guest Name',
            'client_phone' => '1234567890',
            'event_time' => '10:00',
            'delivery_address' => '123 Guest St',
            'venue' => 'Guest Hall'
        ];

        $response = $this->actingAs($client)->post("/client/claim-booking/{$rawToken}", $payload);
        $response->assertSessionHasNoErrors();

        $booking = Booking::where('client_id', $clientProfile->id)->latest()->first();
        $this->assertNotNull($booking);

        $items = BookingItem::where('booking_id', $booking->id)->get();
        $this->assertCount(1, $items);
        $this->assertNotNull($items->first()->confirmed_at);
    }
}
