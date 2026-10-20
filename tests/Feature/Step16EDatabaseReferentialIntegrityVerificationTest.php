<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ReturnItem;
use App\Models\ReturnItemEvidence;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Step16EDatabaseReferentialIntegrityVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TEST 1: USER -> CLIENT -> BOOKING OWNERSHIP & FOREIGN KEY INTEGRITY
     *
     * Verifies that bookings.client_id references clients.id (not users.id),
     * and that assigning a nonexistent client_id triggers a database constraint error.
     */
    public function test_booking_references_valid_client_and_rejects_nonexistent_client(): void
    {
        $user = User::factory()->create([
            'email' => 'client.test@example.com',
            'role' => 'client',
        ]);

        $client = Client::create([
            'full_name' => 'Test Client',
            'email' => $user->email,
            'phone' => '09123456789',
        ]);

        // Valid creation referencing clients.id
        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'venue' => 'Manila Cathedral',
            'status' => 'pending',
            'total_quoted' => 10000.00,
        ]);

        $this->assertNotNull($booking->id);
        $this->assertSame($client->id, $booking->client->id);
        $this->assertSame($client->email, $booking->client->email);
        $this->assertTrue($client->bookings->contains($booking));

        // Attempting to insert a booking with a nonexistent client_id triggers DB foreign key violation
        $this->expectException(QueryException::class);
        Booking::create([
            'client_id' => 999999, // Nonexistent client ID
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Unknown Venue',
            'status' => 'pending',
            'total_quoted' => 5000.00,
        ]);
    }

    /**
     * TEST 2: AUTHENTICATED CLIENT BOOKING CREATION BINDS TO CLIENT.ID (NOT USER.ID)
     */
    public function test_authenticated_booking_creation_binds_to_client_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'mobile_number' => '09171234567',
            'address' => 'Makati City',
            'role' => 'client',
        ]);

        $package = \App\Models\Package::create([
            'title' => 'Intimate Wedding Floral Package',
            'description' => 'Beautiful standard wedding floral set',
            'price' => 25000.00,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'BGC Taguig',
        ]);

        $response->assertRedirect();

        $booking = Booking::latest()->first();
        $this->assertNotNull($booking);

        // Client record should exist with user's email
        $client = Client::where('email', $user->email)->first();
        $this->assertNotNull($client);
        $this->assertSame($client->id, $booking->client_id);
        $this->assertSame('Maria Santos', $booking->client->full_name);
    }

    /**
     * TEST 3: GUEST BOOKING CLAIM BINDS BOOKING TO CLIENT.ID
     */
    public function test_guest_booking_claim_binds_to_client_id(): void
    {
        $user = User::factory()->create([
            'name' => 'Guest User',
            'email' => 'guest.claim@example.com',
            'role' => 'client',
        ]);

        $rawToken = 'test_token_123456789012345678901234';
        $tokenHash = hash('sha256', $rawToken);

        $tempBooking = TemporaryGuestBooking::create([
            'claim_token_hash' => $tokenHash,
            'guest_name' => 'Guest User',
            'guest_email' => $user->email,
            'guest_phone' => '09181234567',
            'guest_address' => 'Quezon City',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Fernwood Gardens',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));
        $response->assertRedirect(route('bookings'));

        $booking = Booking::where('venue', 'Fernwood Gardens')->first();
        $this->assertNotNull($booking);

        $client = Client::where('email', $user->email)->first();
        $this->assertNotNull($client);
        $this->assertSame($client->id, $booking->client_id);

        $tempBooking->refresh();
        $this->assertNotNull($tempBooking->claimed_at);
        $this->assertSame($client->id, $tempBooking->client_id);
    }

    /**
     * TEST 4: BOOKING CHILD INTEGRITY - BOOKING ITEMS, QUOTATIONS, AND PAYMENTS
     */
    public function test_booking_child_relationships_and_foreign_keys(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Grand Plaza Ballroom',
            'status' => 'pending',
            'total_quoted' => 25000.00,
        ]);

        $item = InventoryItem::create([
            'name' => 'Silver Candelabra',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 10,
            'unit_cost' => 300.00,
            'unit' => 'piece',
        ]);

        // 1. BookingItem
        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'quantity' => 4,
            'quoted_unit_price' => 300.00,
            'is_ai_suggested' => false,
        ]);
        $this->assertSame($booking->id, $bookingItem->booking->id);
        $this->assertSame($item->id, $bookingItem->inventoryItem->id);

        // 2. Quotation
        $quotation = Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'raw_materials_sum' => 1200.00,
            'multiplier' => 1.5,
            'labor_amount' => 500.00,
            'final_quoted_price' => 2300.00,
            'status' => Quotation::STATUS_ISSUED,
        ]);
        $this->assertSame($booking->id, $quotation->booking->id);

        // 3. Payment referencing booking and quotation
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'quotation_id' => $quotation->id,
            'amount' => 1150.00,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-PAY-CHILD-01',
            'status' => 'pending',
        ]);
        $this->assertSame($booking->id, $payment->booking->id);
        $this->assertSame($quotation->id, $payment->quotation->id);

        // 4. AssetReturn
        $assetReturn = AssetReturn::create([
            'booking_id' => $booking->id,
            'return_date' => Carbon::now()->addDays(16)->toDateString(),
            'status' => 'Pending',
        ]);
        $this->assertSame($booking->id, $assetReturn->booking->id);

        // 5. ReturnItem
        $returnItem = ReturnItem::create([
            'return_id' => $assetReturn->id,
            'inventory_item_id' => $item->id,
            'quantity_returned' => 4,
            'quantity_good' => 4,
            'condition' => 'good',
        ]);
        $this->assertSame($assetReturn->id, $returnItem->assetReturn->id);
        $this->assertSame($item->id, $returnItem->inventoryItem->id);

        // 6. AiAnalysisResult
        $aiAnalysis = AiAnalysisResult::create([
            'booking_id' => $booking->id,
            'raw_gemini_response' => '{"theme":"Rustic"}',
            'suggested_materials' => json_encode([['name' => 'Silver Candelabra', 'quantity' => 4]]),
            'analyzed_at' => now(),
        ]);
        $this->assertSame($booking->id, $aiAnalysis->booking->id);
    }

    /**
     * TEST 5: QUOTATION VERSION UNIQUE CONSTRAINT
     *
     * Ensures that (booking_id, version) is unique: duplicate version for the same booking
     * is rejected by the database.
     */
    public function test_quotation_version_unique_constraint_enforced(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(10)->toDateString(),
            'venue' => 'Manila Diamond Hotel',
            'status' => 'pending',
            'total_quoted' => 15000.00,
        ]);

        // First version 1
        Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'final_quoted_price' => 15000.00,
            'status' => Quotation::STATUS_SUPERSEDED,
        ]);

        // Separate booking can also have version 1 without collision
        $otherBooking = Booking::create([
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(12)->toDateString(),
            'venue' => 'Greenbelt Lounge',
            'status' => 'pending',
            'total_quoted' => 8000.00,
        ]);
        $otherQuotation = Quotation::create([
            'booking_id' => $otherBooking->id,
            'version' => 1,
            'final_quoted_price' => 8000.00,
            'status' => Quotation::STATUS_ISSUED,
        ]);
        $this->assertNotNull($otherQuotation->id);

        // Duplicate version 1 on the SAME booking MUST fail with QueryException (unique constraint violation)
        $this->expectException(QueryException::class);
        Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1, // Duplicate version for $booking->id!
            'final_quoted_price' => 16000.00,
            'status' => Quotation::STATUS_ISSUED,
        ]);
    }

    /**
     * TEST 6: PAYMENT UNIQUE REFERENCE CONSTRAINT PER BOOKING
     *
     * Ensures that (booking_id, reference_number) is unique.
     */
    public function test_payment_unique_reference_constraint_enforced(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'San Agustin Church',
            'status' => 'payment_submitted',
            'total_quoted' => 30000.00,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 15000.00,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'GCASH-REF-UNIQUE-999',
            'status' => 'pending',
        ]);

        // Duplicate payment reference for the SAME booking MUST fail
        $this->expectException(QueryException::class);
        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 15000.00,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'GCASH-REF-UNIQUE-999',
            'status' => 'pending',
        ]);
    }

    /**
     * TEST 7: INVENTORY TRANSACTION TRACEABILITY AND RESTRICTED AUDIT TRAIL
     */
    public function test_inventory_transaction_traceability_and_restrict_constraint(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'name' => 'Wrought Iron Arch',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 5,
            'unit_cost' => 1200.00,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(10)->toDateString(),
            'venue' => 'The Bellevue Hotel',
            'status' => 'downpayment_received',
            'total_quoted' => 20000.00,
        ]);

        // Initial transaction (e.g. reservation / dispatch)
        $tx1 = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -1.0,
            'transaction_type' => 'dispatch',
            'reason' => 'Dispatched for event setup',
            'performed_by' => $admin->id,
        ]);

        // Secondary correction transaction referencing the initial transaction
        $tx2 = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'reference_transaction_id' => $tx1->id,
            'quantity_change' => 1.0,
            'transaction_type' => 'dispatch_correction',
            'reason' => 'Dispatched item returned unused',
            'performed_by' => $admin->id,
        ]);

        $this->assertSame($tx1->id, $tx2->reference_transaction_id);
        $this->assertSame($item->id, $tx1->inventoryItem->id);
        $this->assertSame($booking->id, $tx1->booking->id);

        // Attempting to delete $tx1 when $tx2 references it must fail due to onDelete('restrict')
        $this->expectException(QueryException::class);
        $tx1->delete();
    }

    /**
     * TEST 8: USER DELETION PRESERVES FINANCIAL AND AUDIT RECORDS VIA NULL_ON_DELETE
     */
    public function test_user_deletion_sets_null_on_financial_and_audit_records(): void
    {
        $staff = User::factory()->create([
            'name' => 'Staff Member',
            'role' => 'staff',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(10)->toDateString(),
            'venue' => 'Sofitel Manila',
            'status' => 'downpayment_received',
            'staff_id' => $staff->id,
            'total_quoted' => 15000.00,
        ]);

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 7500.00,
            'payment_type' => 'cash',
            'payment_option' => 'downpayment',
            'reference_number' => 'CASH-STAFF-001',
            'verified_by' => $staff->id,
            'status' => 'verified',
        ]);

        $this->assertSame($staff->id, $booking->staff_id);
        $this->assertSame($staff->id, $payment->verified_by);

        // Delete staff user
        $staff->delete();

        // Foreign keys must be set to null, NOT delete the booking or payment records
        $booking->refresh();
        $payment->refresh();

        $this->assertNull($booking->staff_id);
        $this->assertNull($payment->verified_by);
        $this->assertSame('verified', $payment->status);
    }

    /**
     * TEST 9: CLIENT DELETION SETS BOOKINGS.CLIENT_ID TO NULL (DOES NOT DESTROY BOOKING)
     */
    public function test_client_deletion_sets_null_on_bookings(): void
    {
        $client = Client::create([
            'full_name' => 'Ephemeral Client',
            'email' => 'ephemeral@example.com',
            'phone' => '09991234567',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'corporate',
            'event_date' => Carbon::now()->addDays(18)->toDateString(),
            'venue' => 'SMX Convention Center',
            'status' => 'pending',
            'total_quoted' => 50000.00,
        ]);

        $this->assertSame($client->id, $booking->client_id);

        // Delete client
        $client->delete();

        $booking->refresh();
        $this->assertNull($booking->client_id);
        $this->assertSame('SMX Convention Center', $booking->venue);
    }

    /**
     * TEST 10: REVISED QUOTATIONS PRESERVE IMMUTABLE HISTORICAL QUOTATIONS
     */
    public function test_revised_quotations_preserve_immutable_historical_quotation_records(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Pico Sands Hotel',
            'status' => 'pending',
            'total_quoted' => 20000.00,
        ]);

        // Historical quotation version 1
        $q1 = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $admin->id,
            'version' => 1,
            'raw_materials_sum' => 10000.00,
            'multiplier' => 1.5,
            'final_quoted_price' => 20000.00,
            'status' => Quotation::STATUS_ISSUED,
            'items_snapshot' => [
                ['name' => 'White Roses', 'quantity' => 50, 'unit_price' => 50.00],
            ],
        ]);

        // Revised quotation version 2 (e.g. client requested additions)
        $q1->update(['status' => Quotation::STATUS_SUPERSEDED]);

        $q2 = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $admin->id,
            'version' => 2,
            'raw_materials_sum' => 15000.00,
            'multiplier' => 1.5,
            'final_quoted_price' => 30000.00,
            'status' => Quotation::STATUS_ISSUED,
            'items_snapshot' => [
                ['name' => 'White Roses', 'quantity' => 50, 'unit_price' => 50.00],
                ['name' => 'Gold Arch', 'quantity' => 1, 'unit_price' => 2500.00],
            ],
        ]);

        $booking->refresh();

        // Historical record Q1 must remain intact and immutable
        $this->assertSame(2, $booking->quotations()->count());
        $q1Fresh = $q1->fresh();
        $this->assertSame(Quotation::STATUS_SUPERSEDED, $q1Fresh->status);
        $this->assertSame(1, $q1Fresh->version);
        $this->assertSame(20000.0, (float) $q1Fresh->final_quoted_price);
        $this->assertCount(1, $q1Fresh->items_snapshot);

        // Active quotation is Q2
        $activeQuote = $booking->activeQuotation;
        $this->assertNotNull($activeQuote);
        $this->assertSame(2, $activeQuote->version);
        $this->assertSame(30000.0, (float) $activeQuote->final_quoted_price);
        $this->assertCount(2, $activeQuote->items_snapshot);
    }
}

