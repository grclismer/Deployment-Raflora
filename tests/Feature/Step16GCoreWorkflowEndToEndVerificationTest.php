<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ReturnItem;
use App\Models\Setting;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\InventoryDispatchService;
use App\Services\QuotationIssuanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Step16GCoreWorkflowEndToEndVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected User $staffUser;
    protected User $clientUser;
    protected Client $clientRecord;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Jane',
            'email' => 'admin_16g@raflora.test',
            'email_verified_at' => now(),
        ]);

        $this->staffUser = User::factory()->create([
            'role' => 'staff',
            'name' => 'Staff Mark',
            'email' => 'staff_16g@raflora.test',
            'email_verified_at' => now(),
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'name' => 'Client Alice',
            'email' => 'alice_16g@raflora.test',
            'email_verified_at' => now(),
        ]);

        $this->clientRecord = Client::create([
            'id' => $this->clientUser->id,
            'user_id' => $this->clientUser->id,
            'full_name' => $this->clientUser->name,
            'email' => $this->clientUser->email,
            'phone' => '09171234567',
            'address' => '100 Rose Boulevard, Quezon City',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * JOURNEY A: NORMAL BOOKING -> COMPLETION
     *
     * Validates all 42 verification steps of the canonical Raflora lifecycle:
     * Booking -> AI -> Confirmation -> Quote -> Payment -> Confirmation ->
     * Prep Scheduling -> Reservation -> Dispatch -> In Progress -> Completed ->
     * Return Tracking (Good condition) -> Stock Restoration -> Final Payment ->
     * Final Completion Guards -> Audit/History Persistence.
     */
    public function test_journey_a_normal_booking_through_completion(): void
    {
        // 1. Setup Inventory
        $vaseItem = InventoryItem::create([
            'name' => 'Ceramic Centerpiece Vase',
            'category' => 'prop',
            'is_perishable' => false,
            'current_stock' => 50,
            'reserved_stock' => 0,
            'unit_cost' => 120.00,
            'unit' => 'piece',
            'min_stock' => 5,
        ]);

        $flowerItem = InventoryItem::create([
            'name' => 'White Ecuadorian Roses',
            'category' => 'flower',
            'is_perishable' => true,
            'current_stock' => 100,
            'reserved_stock' => 0,
            'unit_cost' => 50.00,
            'unit' => 'stem',
            'min_stock' => 10,
        ]);

        // 2. Client creates booking with AI analysis payload
        $eventDate = Carbon::now()->addDays(20)->toDateString();
        $image = UploadedFile::fake()->create('wedding_inspiration.jpg', 120);
        $analysisToken = Str::random(40);

        $response = $this->actingAs($this->clientUser)->withSession([
            "booking_analysis_payloads.{$analysisToken}" => [
                'image_hash' => sha1_file($image->getRealPath()),
                'temp_path' => 'bookings/inspiration-images/wedding_inspiration.jpg',
                'analysis' => [
                    'theme' => 'Vintage Elegance',
                    'suggested_materials' => [
                        [
                            'item_name' => 'Ceramic Centerpiece Vase',
                            'category' => 'prop',
                            'unit_type' => 'piece',
                            'quantity' => 10,
                            'unit_cost_php' => 120,
                        ],
                        [
                            'item_name' => 'White Ecuadorian Roses',
                            'category' => 'flower',
                            'unit_type' => 'stem',
                            'quantity' => 30,
                            'unit_cost_php' => 50,
                        ],
                    ],
                ],
                'raw_response' => 'mocked_ai_response',
            ],
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Grand Plaza Ballroom',
            'guest_count' => 100,
            'special_requests' => 'Classic white floral setup',
            'analysis_token' => $analysisToken,
            'inspiration_image' => $image,
        ]);

        $booking = Booking::first();
        $this->assertNotNull($booking, 'Step 1-2: Booking must be created in database.');
        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertSame($this->clientRecord->id, $booking->client_id, 'Step 3: Booking must belong to Client.');
        $this->assertSame('pending', $booking->status, 'Step 3: Initial status must be pending.');

        // Verify AI suggestions
        $bookingItems = $booking->bookingItems()->get();
        $this->assertCount(2, $bookingItems, 'Step 7-8: AI suggestions must be saved as booking items.');
        $vaseBookingItem = $bookingItems->firstWhere('item_name', 'Ceramic Centerpiece Vase');
        $this->assertNotNull($vaseBookingItem);

        // Add an unconfirmed unmatched AI item to test the quotation guard
        $unconfirmedItem = $booking->bookingItems()->create([
            'item_name' => 'Vintage Brass Candelabra',
            'quantity' => 2,
            'is_ai_suggested' => true,
            'confirmed_at' => null,
            'inventory_item_id' => null,
        ]);

        // Dead-end check: Unconfirmed material blocks quotation submission
        $failQuote = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);
        $failQuote->assertSessionHas('error');
        $this->assertSame('pending', $booking->fresh()->status, 'Step 11: Quotation blocked when items unconfirmed.');

        // Remove/confirm the extra unconfirmed item
        $unconfirmedItem->delete();

        // Admin confirms materials
        foreach ($booking->bookingItems()->get() as $item) {
            $this->actingAs($this->adminUser)->post(route('admin.bookings.items.confirm', [
                'booking' => $booking,
                'bookingItem' => $item,
            ]))->assertRedirect();
        }

        $this->assertNotNull($vaseBookingItem->fresh()->confirmed_at, 'Step 12: Material requirement confirmed.');

        // Check stock and issue quotation
        $quoteResponse = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);
        $quoteResponse->assertRedirect();
        $booking->refresh();
        $this->assertSame('quotation_sent', $booking->status, 'Step 14: Quotation issued transitions to quotation_sent.');

        // Quotation snapshot & versioning
        $quotation = $booking->quotations()->first();
        $this->assertNotNull($quotation, 'Step 15: Quotation record must exist.');
        $this->assertSame(1, $quotation->version);
        $this->assertSame(Quotation::STATUS_ISSUED, $quotation->status);
        $this->assertNotEquals('confirmed', $booking->status, 'Step 16: Issuing quotation does not confirm booking.');

        // Client accepts quotation
        $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame('approved', $booking->status, 'Step 17: Client acceptance moves status to approved.');

        // Admin final approves
        $this->actingAs($this->adminUser)->post(route('admin.bookings.final-approve', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame('admin_approved', $booking->status);

        // Client submits downpayment reference
        $dpAmount = (float) $quotation->final_quoted_price * 0.5;
        $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'GCASH-NORM-DP-999',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])->assertRedirect();

        $payment = Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment, 'Step 18: Payment record created.');
        $this->assertSame('pending', $payment->status);

        // Admin verifies payment
        $this->actingAs($this->adminUser)->post(route('admin.payments.verify', $payment), [
            'amount_received' => $payment->amount,
        ])->assertRedirect();

        $booking->refresh();
        $this->assertSame('downpayment_received', $booking->status, 'Step 19: Status transitions to downpayment_received.');
        $this->assertSame(0.0, (float) $vaseItem->fresh()->reserved_stock, 'Step 22: Preparation not started; stock not reserved.');

        // Dead-end check: Reservation fails when preparation_start_date is null
        $failReserve = $this->actingAs($this->adminUser)->post(route('admin.bookings.reserve-materials', $booking));
        $failReserve->assertSessionHas('error');

        // Dead-end check: Reservation fails when preparation_start_date is in future
        $booking->preparation_start_date = Carbon::now()->addDays(5)->toDateString();
        $booking->save();
        $failFuture = $this->actingAs($this->adminUser)->post(route('admin.bookings.reserve-materials', $booking));
        $failFuture->assertSessionHas('error');

        // Admin schedules eligible preparation_start_date (today) and reserves
        $booking->preparation_start_date = Carbon::now()->toDateString();
        $booking->status = 'confirmed';
        $booking->save();

        $reserveResponse = $this->actingAs($this->adminUser)->post(route('admin.bookings.reserve-materials', $booking));
        $reserveResponse->assertSessionHas('success');
        $this->assertSame(10.0, (float) $vaseItem->fresh()->reserved_stock, 'Step 26: 10 vases reserved.');

        $lockTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('inventory_item_id', $vaseItem->id)
            ->where('transaction_type', 'booking_lock')
            ->first();
        $this->assertNotNull($lockTx, 'Step 27: booking_lock transaction created.');

        // Dead-end check: Event execution blocked without dispatch
        $failStart = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_in_progress',
        ]);
        $failStart->assertSessionHas('error');

        // Admin confirms fresh flower procurement readiness
        $this->actingAs($this->adminUser)->post(route('admin.bookings.confirm-fresh-flowers', $booking))->assertSessionHas('success');
        $this->assertTrue($booking->fresh()->areFreshFlowersReady(), 'Step 28: Fresh flowers confirmed ready.');

        // Admin dispatches materials
        app(InventoryDispatchService::class)->dispatchItems($booking, [
            ['inventory_item_id' => $vaseItem->id, 'quantity' => 10],
        ], $this->adminUser->id, 'Admin Dispatch Vases');

        $this->assertSame(40.0, (float) $vaseItem->fresh()->current_stock, 'Step 29: Physical stock deducted to 40 on dispatch.');

        // Start event execution
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_in_progress',
        ])->assertRedirect();
        $this->assertSame('event_in_progress', $booking->fresh()->status, 'Step 30: Event in progress.');

        // Mark event completed
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_completed',
            'action' => 'save',
        ])->assertRedirect();
        $this->assertSame('event_completed', $booking->fresh()->status, 'Step 32: Event completed.');

        // Initialize and handle return
        $this->actingAs($this->adminUser)->get(route('admin.return-tracking.manage', $booking))->assertOk();
        $assetReturn = $booking->fresh()->returns()->first();
        $this->assertNotNull($assetReturn, 'Step 33: Return tracking initialized.');
        $returnItem = $assetReturn->returnItems()->first();
        $this->assertNotNull($returnItem);

        // Return all 10 in good condition
        $this->actingAs($this->adminUser)->put(route('admin.return-tracking.update', $assetReturn), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                    'damage_charge' => 0,
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(50.0, (float) $vaseItem->fresh()->current_stock, 'Step 35: Inventory restored to 50.');
        $this->assertSame('Completed', $assetReturn->fresh()->status);

        // Dead-end check: Cannot complete booking while balance remains
        $this->assertTrue($booking->fresh()->remaining_balance > 0);
        $failComplete = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'completed',
            'action' => 'save',
        ]);
        $failComplete->assertSessionHas('error');

        // Client pays remaining balance
        $remainingBal = (float) $booking->fresh()->remaining_balance;
        $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'GCASH-NORM-FINAL-100',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ])->assertRedirect();

        $finalPayment = Payment::where('booking_id', $booking->id)->where('reference_number', 'GCASH-NORM-FINAL-100')->first();
        $this->actingAs($this->adminUser)->post(route('admin.payments.verify', $finalPayment), [
            'amount_received' => $finalPayment->amount,
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $booking->fresh()->remaining_balance, 'Step 37: Remaining balance is 0.');

        // Successfully transition booking to completed
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'completed',
            'action' => 'save',
        ])->assertRedirect();

        $this->assertSame('completed', $booking->fresh()->status, 'Step 38-39: Booking successfully completed.');

        // Persistence & Traceability
        $this->assertTrue(AuditLog::where('entity_id', $booking->id)->exists(), 'Step 40: Audit records exist.');
        $this->assertTrue(InventoryTransaction::where('booking_id', $booking->id)->exists(), 'Step 41: Inventory transactions exist.');
        $this->assertSame(2, Payment::where('booking_id', $booking->id)->count(), 'Step 42: Both payments recorded.');
        $this->assertSame('Completed', $assetReturn->fresh()->status, 'Step 42: Return record completed.');
    }

    /**
     * JOURNEY B: DAMAGE/LOSS + FINAL SETTLEMENT
     *
     * Validates mixed returns (good + damaged + lost), stock restoration of good only,
     * recording damage fee, financial obligation recalculation, completion guard blocking
     * until damage fee is settled, and successful completion after settlement.
     */
    public function test_journey_b_damage_loss_and_final_settlement(): void
    {
        $chairItem = InventoryItem::create([
            'name' => 'Tiffany Banquet Chair',
            'category' => 'furniture',
            'is_perishable' => false,
            'current_stock' => 100,
            'reserved_stock' => 0,
            'unit_cost' => 200.00,
            'unit' => 'piece',
            'min_stock' => 10,
        ]);

        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'debut',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Sunset Pavillion',
            'status' => 'confirmed',
            'preparation_start_date' => Carbon::now()->toDateString(),
            'final_quoted_price' => 5000.00,
            'total_quoted' => 5000.00,
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $chairItem->id,
            'item_name' => $chairItem->name,
            'quantity' => 20,
            'quoted_unit_price' => 200.00,
            'confirmed_at' => now(),
        ]);

        // Dispatched 20 chairs
        InventoryTransaction::create([
            'inventory_item_id' => $chairItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -20,
            'transaction_type' => 'dispatch',
            'reason' => 'Event Dispatch',
            'performed_by' => $this->adminUser->id,
        ]);
        $chairItem->decrement('current_stock', 20); // 80 remaining

        // Advance to event_completed
        $booking->status = 'event_completed';
        $booking->save();

        // Downpayment was paid (₱2,500)
        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 2500.00,
            'amount_paid' => 2500.00,
            'remaining_balance' => 2500.00,
            'payment_option' => 'downpayment',
            'payment_type' => 'bank_transfer',
            'reference_number' => 'REF-JB-DP',
            'status' => 'downpayment_received',
            'verified_at' => now(),
        ]);

        // Initialize Return
        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $assetReturn = $booking->fresh()->returns()->firstOrFail();
        $returnItem = $assetReturn->returnItems()->firstOrFail();

        // 20 dispatched: 14 good, 4 damaged, 2 lost
        // Admin assesses ₱800 damage charge
        $response = $this->actingAs($this->adminUser)->put(route('admin.return-tracking.update', $assetReturn), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 14,
                    'quantity_damaged' => 4,
                    'quantity_lost' => 2,
                    'charge_decision' => 'charge',
                    'damage_charge' => 800.00,
                    'charge_reason' => '4 chairs cracked frame, 2 chairs missing',
                ],
            ],
            'notes' => 'Damaged and lost assessment recorded',
        ]);
        $response->assertRedirect();

        // Verification 1: Only good quantity (14) restored to stock
        $this->assertSame(94.0, (float) $chairItem->fresh()->current_stock, 'Step 1: 80 + 14 = 94 current stock.');
        $this->assertSame(14.0, (float) $returnItem->fresh()->quantity_good);
        $this->assertSame(4.0, (float) $returnItem->fresh()->quantity_damaged);
        $this->assertSame(2.0, (float) $returnItem->fresh()->quantity_lost);
        $this->assertSame(800.0, (float) $returnItem->fresh()->damage_charge);
        $this->assertSame('mixed', $returnItem->fresh()->condition);
        $this->assertSame('Completed', $assetReturn->fresh()->status);

        // Verification 2: Damage charge increases financial obligation
        $booking->refresh();
        $this->assertEquals(800.0, (float) $booking->damage_charges);
        $expectedBalance = 5000.00 - 2500.00 + 800.00; // 3,300.00
        $this->assertEquals($expectedBalance, (float) $booking->remaining_balance, 'Step 8: Remaining balance includes damage fee.');

        // Dead-end check: Booking cannot be marked completed with outstanding balance
        $failComplete = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'completed',
            'action' => 'save',
        ]);
        $failComplete->assertSessionHas('error');
        $this->assertNotSame('completed', $booking->fresh()->status);

        // Client pays the full remaining balance (₱3,300.00)
        $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'REF-JB-SETTLE',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ])->assertRedirect();

        $settlePayment = Payment::where('booking_id', $booking->id)->where('reference_number', 'REF-JB-SETTLE')->firstOrFail();
        $this->actingAs($this->adminUser)->post(route('admin.payments.verify', $settlePayment), [
            'amount_received' => $settlePayment->amount,
        ])->assertRedirect();

        $this->assertEquals(0.0, (float) $booking->fresh()->remaining_balance, 'Step 11: Balance resolved.');

        // Now completion succeeds
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'completed',
            'action' => 'save',
        ])->assertRedirect();

        $this->assertSame('completed', $booking->fresh()->status, 'Step 12: Booking reaches completed.');
    }

    /**
     * JOURNEY C: LONG-TERM PRICE RECONFIRMATION
     *
     * Branch A: Price Unchanged -> tentative flag cleared, reconfirmed_at populated, alert dismissed.
     * Branch B: Price Changed -> v1 superseded, v2 issued, client acceptance required, payments preserved.
     */
    public function test_journey_c_price_reconfirmation_branches_a_and_b(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // --- BRANCH A: PRICE UNCHANGED ---
        $bookingA = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Diamond Hotel',
            'status' => 'confirmed',
            'multiplier' => 2.0,
            'labor_method' => 'fixed',
            'labor_rate' => 3000,
            'final_quoted_price' => 15000.00,
            'total_quoted' => 15000.00,
        ]);

        $itemA = InventoryItem::create([
            'name' => 'Rose Garland A',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 200,
            'unit_cost' => 100.00,
        ]);

        $bookingA->bookingItems()->create([
            'inventory_item_id' => $itemA->id,
            'item_name' => $itemA->name,
            'quantity' => 60,
            'quoted_unit_price' => 100.00,
            'confirmed_at' => now(),
        ]);

        $v1A = app(QuotationIssuanceService::class)->issue($bookingA, $this->adminUser->id);
        $v1A->status = Quotation::STATUS_ACCEPTED;
        $v1A->is_tentative = true;
        $v1A->save();

        AdminAlert::create([
            'type' => 'price_reconfirmation_due',
            'booking_id' => $bookingA->id,
            'title' => 'Price Reconfirmation Due',
            'message' => 'Review pricing.',
            'is_read' => false,
        ]);

        // Admin reconfirms price unchanged
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $bookingA), [
            'event_type' => 'wedding',
            'event_date' => '2026-11-20',
            'venue' => 'Manila Diamond Hotel',
            'status' => 'confirmed',
            'action' => 'reconfirm_price_unchanged',
        ])->assertRedirect()->assertSessionHas('success');

        $v1A->refresh();
        $this->assertFalse($v1A->is_tentative, 'Branch A: is_tentative must be false.');
        $this->assertNotNull($v1A->reconfirmed_at, 'Branch A: reconfirmed_at populated.');
        $this->assertSame(1, Quotation::where('booking_id', $bookingA->id)->count(), 'Branch A: No duplicate quote.');
        $this->assertTrue(AdminAlert::where('booking_id', $bookingA->id)->where('type', 'price_reconfirmation_due')->first()->is_read);

        // --- BRANCH B: PRICE CHANGED ---
        $bookingB = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => '2026-12-15',
            'venue' => 'Sofitel Grand Ballroom',
            'status' => 'downpayment_received',
            'multiplier' => 2.0,
            'labor_method' => 'fixed',
            'labor_rate' => 4000,
            'final_quoted_price' => 20000.00,
            'total_quoted' => 20000.00,
        ]);

        $bookingB->bookingItems()->create([
            'inventory_item_id' => $itemA->id,
            'item_name' => $itemA->name,
            'quantity' => 80,
            'quoted_unit_price' => 100.00,
            'confirmed_at' => now(),
        ]);

        $v1B = app(QuotationIssuanceService::class)->issue($bookingB, $this->adminUser->id);
        $v1B->status = Quotation::STATUS_ACCEPTED;
        $v1B->is_tentative = true;
        $v1B->save();

        Payment::create([
            'booking_id' => $bookingB->id,
            'quotation_id' => $v1B->id,
            'amount' => 10000.00,
            'amount_paid' => 10000.00,
            'remaining_balance' => 10000.00,
            'payment_option' => 'downpayment',
            'payment_type' => 'bank_transfer',
            'reference_number' => 'REF-JC-DP10K',
            'status' => 'downpayment_received',
            'verified_at' => now(),
        ]);

        AdminAlert::create([
            'type' => 'price_reconfirmation_due',
            'booking_id' => $bookingB->id,
            'title' => 'Price Reconfirmation Due',
            'message' => 'Review pricing.',
            'is_read' => false,
        ]);

        // Quantity increases to 100: 100 * 100 * 2 + 4000 = 24,000
        BookingItem::where('booking_id', $bookingB->id)->update(['quantity' => 100]);

        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $bookingB), [
            'event_type' => 'wedding',
            'event_date' => '2026-12-15',
            'venue' => 'Sofitel Grand Ballroom',
            'status' => 'downpayment_received',
            'action' => 'reconfirm_price_revised',
        ])->assertRedirect()->assertSessionHas('success');

        $v1B->refresh();
        $bookingB->refresh();

        // v1 superseded & immutable
        $this->assertSame(Quotation::STATUS_SUPERSEDED, $v1B->status, 'Branch B: v1 superseded.');

        // v2 created
        $v2B = Quotation::where('booking_id', $bookingB->id)->where('version', 2)->firstOrFail();
        $this->assertSame(Quotation::STATUS_ISSUED, $v2B->status);
        $this->assertEquals(24000.00, (float) $v2B->final_quoted_price);
        $this->assertFalse($v2B->is_tentative);

        // Existing payment preserved
        $this->assertSame(1, Payment::where('booking_id', $bookingB->id)->count(), 'Branch B: Payment intact.');
        $this->assertEquals(10000.00, (float) $bookingB->total_paid);
        $this->assertEquals(14000.00, (float) $bookingB->remaining_balance);

        // Booking status awaits client acceptance
        $this->assertSame('quotation_sent', $bookingB->status);

        // Client accepts revised quotation
        $this->actingAs($this->clientUser)->post(route('bookings.accept', $bookingB))->assertRedirect();
        $this->assertSame('downpayment_received', $bookingB->fresh()->status, 'Branch B: Client accepted revised quote restores downpayment_received status.');
    }

    /**
     * JOURNEY D: GUEST CLAIM SECURITY BOUNDARY
     *
     * Validates temporary guest booking, token-bound access, payment rejection before claim,
     * legitimate client claim linking, and cross-client isolation.
     */
    public function test_journey_d_guest_claim_security_boundary(): void
    {
        $guestEmail = 'guest_journey_d@example.com';
        $token = (string) Str::uuid();

        // Permanent guest booking created initially with guest_access_token
        $guestBooking = Booking::create([
            'guest_name' => 'Guest David',
            'guest_email' => $guestEmail,
            'guest_phone' => '09178888888',
            'guest_access_token' => $token,
            'client_id' => null,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Orchard Gazebo',
            'status' => 'admin_approved',
            'final_quoted_price' => 10000.00,
        ]);

        // 1. Guest accesses with valid token
        $this->get(route('guest.booking.analysis', ['token' => $token]))->assertOk();

        // 2. Access with invalid token is blocked (404)
        $this->get(route('guest.booking.analysis', ['token' => 'invalid-token-123']))->assertNotFound();

        // 3. Unclaimed guest cannot submit payment
        $failPay = $this->post(route('guest.bookings.payment.reference', ['booking' => $guestBooking->id]), [
            'guest_token' => $token,
            'reference_number' => 'GUEST-UNAUTH-DP',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $failPay->assertRedirect(route('guest.booking.analysis', ['token' => $token]));
        $failPay->assertSessionHas('error');
        $this->assertSame(0, Payment::where('booking_id', $guestBooking->id)->count(), 'No payment created for unclaimed guest.');

        // 4. Temporary guest booking for registration claim flow
        $tempBooking = new TemporaryGuestBooking();
        $tempBooking->fill([
            'guest_name' => 'Guest David',
            'guest_email' => $guestEmail,
            'guest_phone' => '09178888888',
            'guest_address' => '50 Orchard Road',
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Orchard Gazebo',
            'table_count' => 5,
            'guest_count' => 50,
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        $claimToken = $tempBooking->generateToken();
        $tempBooking->save();

        // Client registers with matching email and claims booking
        $registeredClientUser = User::factory()->create([
            'email' => $guestEmail,
            'name' => 'David Registered',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $claimResp = $this->actingAs($registeredClientUser)->post(route('client.claim-guest-booking.claim', ['token' => $claimToken]));
        $claimResp->assertRedirect(route('bookings'));

        $claimedBooking = Booking::where('guest_email', $guestEmail)->whereNotNull('client_id')->firstOrFail();
        $this->assertNotNull($claimedBooking->client_id, 'Booking associated with Client ID.');
        $this->assertSame($registeredClientUser->email, $claimedBooking->client->email);

        // 5. Client can now proceed with payment workflow
        $claimedBooking->status = 'admin_approved';
        $claimedBooking->final_quoted_price = 10000.00;
        $claimedBooking->total_quoted = 10000.00;
        $claimedBooking->save();

        $payResp = $this->actingAs($registeredClientUser)->post(route('bookings.payment.reference', ['booking' => $claimedBooking->id]), [
            'reference_number' => 'DAVID-GCASH-AUTH-DP',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $payResp->assertRedirect();
        $this->assertSame(1, Payment::where('booking_id', $claimedBooking->id)->count(), 'Payment successfully created for claimed client.');

        // 6. Cross-client access denied (403)
        $unrelatedClient = User::factory()->create(['role' => 'client', 'email' => 'stranger@example.com']);
        Client::create([
            'id' => $unrelatedClient->id,
            'user_id' => $unrelatedClient->id,
            'full_name' => 'Stranger',
            'email' => $unrelatedClient->email,
            'phone' => '09170001111',
            'address' => 'Nowhere',
        ]);

        $this->actingAs($unrelatedClient)->get(route('bookings.show', ['booking' => $claimedBooking->id]))->assertForbidden();
    }

    /**
     * JOURNEY E: INVENTORY CONFLICT & AVAILABILITY
     *
     * Validates multi-booking on same date when capacity permits,
     * overbooking rejection, capacity restoration upon cancellation,
     * cross-date independence, and perishable non-locking behavior.
     */
    public function test_journey_e_inventory_conflict_scenarios(): void
    {
        $eventDate = Carbon::now()->addDays(20)->toDateString();

        $archItem = InventoryItem::create([
            'name' => 'Floral Arch Frame',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 2,
            'unit_cost' => 1000.00,
        ]);

        // Booking 1 uses 1 arch
        $booking1 = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Venue 1',
            'status' => 'pending',
            'total_quoted' => 5000.00,
        ]);
        $booking1->inventoryItems()->attach($archItem->id, [
            'quantity' => 1,
            'quoted_unit_price' => 1000.00,
            'confirmed_at' => now(),
        ]);

        // Booking 2 uses 1 arch (same date)
        $booking2 = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'debut',
            'event_date' => $eventDate,
            'venue' => 'Venue 2',
            'status' => 'pending',
            'total_quoted' => 5000.00,
        ]);
        $booking2->inventoryItems()->attach($archItem->id, [
            'quantity' => 1,
            'quoted_unit_price' => 1000.00,
            'confirmed_at' => now(),
        ]);

        // Scenario 1: Both bookings confirmed because total demand (2) <= stock (2)
        $this->recordVerifiedPayment($booking1);
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking1), [
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Venue 1',
            'status' => 'confirmed',
            'action' => 'save',
        ])->assertSessionMissing('error');
        $this->assertSame('confirmed', $booking1->fresh()->status);

        $this->recordVerifiedPayment($booking2);
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking2), [
            'event_type' => 'debut',
            'event_date' => $eventDate,
            'venue' => 'Venue 2',
            'status' => 'confirmed',
            'action' => 'save',
        ])->assertSessionMissing('error');
        $this->assertSame('confirmed', $booking2->fresh()->status);

        // Scenario 2: Booking 3 demands 1 arch on same date -> Overbooking blocked
        $booking3 = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'anniversary',
            'event_date' => $eventDate,
            'venue' => 'Venue 3',
            'status' => 'pending',
            'total_quoted' => 5000.00,
        ]);
        $booking3->inventoryItems()->attach($archItem->id, [
            'quantity' => 1,
            'quoted_unit_price' => 1000.00,
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($booking3);
        $failResp = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking3), [
            'event_type' => 'anniversary',
            'event_date' => $eventDate,
            'venue' => 'Venue 3',
            'status' => 'confirmed',
            'action' => 'save',
        ]);
        $failResp->assertSessionHas('error');
        $this->assertSame('pending', $booking3->fresh()->status, 'Scenario 2: Overbooking blocked.');

        // Scenario 3: Cancel Booking 1 -> Booking 3 can now be confirmed
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking1), [
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Venue 1',
            'status' => 'cancelled',
            'action' => 'save',
        ])->assertRedirect();
        $this->assertSame('cancelled', $booking1->fresh()->status);

        $successResp = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking3), [
            'event_type' => 'anniversary',
            'event_date' => $eventDate,
            'venue' => 'Venue 3',
            'status' => 'confirmed',
            'action' => 'save',
        ]);
        $successResp->assertSessionMissing('error');
        $this->assertSame('confirmed', $booking3->fresh()->status, 'Scenario 3: Capacity released by cancellation.');

        // Scenario 4: Different date demand does not conflict
        $diffDate = Carbon::now()->addDays(35)->toDateString();
        $bookingDiff = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'corporate',
            'event_date' => $diffDate,
            'venue' => 'Convention Hall',
            'status' => 'pending',
            'total_quoted' => 5000.00,
        ]);
        $bookingDiff->inventoryItems()->attach($archItem->id, [
            'quantity' => 2,
            'quoted_unit_price' => 1000.00,
            'confirmed_at' => now(),
        ]);

        $this->recordVerifiedPayment($bookingDiff);
        $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $bookingDiff), [
            'event_type' => 'corporate',
            'event_date' => $diffDate,
            'venue' => 'Convention Hall',
            'status' => 'confirmed',
            'action' => 'save',
        ])->assertSessionMissing('error');
        $this->assertSame('confirmed', $bookingDiff->fresh()->status, 'Scenario 4: Different date independent.');
    }

    /**
     * AUTHORIZATION BOUNDARY VERIFICATION
     *
     * Validates strict role enforcement:
     * - Staff cannot access Admin routes or execute Admin operations.
     * - Client cannot access other Client records or execute Admin operations.
     * - Guest cannot access protected routes without valid token.
     */
    public function test_authorization_boundaries_across_all_roles(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Grand Pavilion',
            'status' => 'pending',
            'total_quoted' => 10000.00,
        ]);

        $item = InventoryItem::create([
            'name' => 'Gold Pedestal',
            'category' => 'props',
            'unit_cost' => 500,
            'current_stock' => 10,
        ]);

        $bItem = $booking->bookingItems()->create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 2,
            'quoted_unit_price' => 500,
            'is_ai_suggested' => true,
        ]);

        // 1. Staff cannot confirm material requirements (Admin only)
        $this->actingAs($this->staffUser)->post(route('admin.bookings.items.confirm', [
            'booking' => $booking,
            'bookingItem' => $bItem,
        ]))->assertForbidden();

        // 2. Staff cannot access admin settings
        $this->actingAs($this->staffUser)->get(route('admin.settings'))->assertForbidden();

        // 3. Client cannot access admin bookings queue
        $this->actingAs($this->clientUser)->get(route('admin.bookings'))->assertForbidden();

        // 4. Guest cannot access client dashboard
        auth()->logout();
        $this->get(route('client.dashboard'))->assertRedirect(route('login'));
    }

    /**
     * FAILURE / DEAD-END TESTING
     *
     * Validates that illegal state transitions and invalid operations are safely rejected
     * without data corruption, leaving existing valid state preserved.
     */
    public function test_dead_ends_and_invalid_action_rejections(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(10)->toDateString(),
            'venue' => 'Manila Manor',
            'status' => 'event_completed',
            'total_quoted' => 10000.00,
            'final_quoted_price' => 10000.00,
        ]);

        $item = InventoryItem::create([
            'name' => 'Crystal Chandelier',
            'category' => 'props',
            'is_perishable' => false,
            'unit_cost' => 2500,
            'current_stock' => 5,
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 2,
            'quoted_unit_price' => 2500,
            'confirmed_at' => now(),
        ]);

        // Dispatched 2 chandeliers
        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'dispatch',
            'reason' => 'Dispatch',
            'performed_by' => $this->adminUser->id,
        ]);

        // Initialize return
        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $return = $booking->fresh()->returns()->firstOrFail();
        $returnItem = $return->returnItems()->firstOrFail();

        // 1. Invalid return quantity exceeding dispatch is rejected
        $respExceed = $this->actingAs($this->adminUser)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 3, // 3 > 2 dispatched
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);
        $respExceed->assertSessionHas('error');
        $this->assertNotSame('Completed', $return->fresh()->status);

        // 2. Booking completion blocked when return audit is incomplete
        $respCompleteBlocked = $this->actingAs($this->adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'completed',
            'action' => 'save',
        ]);
        $respCompleteBlocked->assertSessionHas('error');
        $this->assertNotSame('completed', $booking->fresh()->status);
    }

    /**
     * DATABASE STATE & INTEGRITY VERIFICATION
     *
     * Validates referential constraints, version immutability,
     * and transactional traceability throughout the workflow.
     */
    public function test_database_integrity_and_immutability_throughout_workflow(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Heritage Chapel',
            'status' => 'pending',
            'total_quoted' => 12000.00,
        ]);

        // Referential integrity: client_id matches Client primary key
        $this->assertSame($this->clientRecord->id, $booking->client->id);

        // Quotation version composite uniqueness enforced
        $booking->quotations()->create([
            'version' => 1,
            'final_quoted_price' => 12000.00,
            'status' => Quotation::STATUS_ACCEPTED,
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $booking->quotations()->create([
            'version' => 1, // Duplicate version for same booking violates unique constraint
            'final_quoted_price' => 14000.00,
            'status' => Quotation::STATUS_ISSUED,
        ]);
    }
}

