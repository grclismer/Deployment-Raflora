<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Setting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class Step14BPriceValidityAndReconfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $clientUser;
    protected Client $clientProfile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_step14b@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'email' => 'staff_step14b@example.com',
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'client_step14b@example.com',
        ]);

        $this->clientProfile = Client::create([
            'user_id' => $this->clientUser->id,
            'full_name' => 'Step 14B Client',
            'email' => $this->clientUser->email,
            'phone' => '09171234567',
            'address' => '123 Floral St, Quezon City',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createBookingWithItem(string $eventDate, array $bookingAttrs = [], float $unitCost = 50.0, int $quantity = 20): Booking
    {
        $booking = Booking::create(array_merge([
            'client_id' => $this->clientProfile->id,
            'status' => 'pending',
            'event_type' => 'Wedding',
            'event_date' => $eventDate,
            'venue' => 'Grand Floral Ballroom',
            'guest_count' => 150,
            'multiplier' => 3.0,
            'labor_method' => 'fixed',
            'labor_rate' => 2000,
            'final_quoted_price' => 5000,
            'total_quoted' => 5000,
        ], $bookingAttrs));

        $item = InventoryItem::create([
            'name' => 'Holland White Hydrangeas ' . uniqid(),
            'unit_cost' => $unitCost,
            'quantity' => 200,
            'category' => 'flowers',
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'quoted_unit_price' => $unitCost,
            'is_ai_suggested' => false,
            'confirmed_at' => Carbon::now(),
        ]);

        return $booking;
    }

    /**
     * Requirement A: Configuration authorization
     */
    public function test_unauthorized_user_cannot_change_business_settings(): void
    {
        $this->actingAs($this->staff)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => 100,
                'confirmed' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($this->clientUser)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => 100,
                'confirmed' => 1,
            ])
            ->assertForbidden();

        // Default remains 90
        $this->assertSame(90, Setting::getLongTermBookingThresholdDays());
    }

    public function test_authorized_admin_can_change_settings_with_explicit_confirmation(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => 120,
                'price_reconfirmation_threshold_days' => 45,
                'change_reason' => 'Quarterly policy review',
                'confirmed' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(120, Setting::getLongTermBookingThresholdDays());
        $this->assertSame(45, Setting::getPriceReconfirmationThresholdDays());

        // Verify audit log created
        $auditLog = AuditLog::where('action', 'setting_updated')->latest()->first();
        $this->assertNotNull($auditLog);
        $this->assertSame($this->admin->id, $auditLog->user_id);
        $this->assertStringContainsString('Quarterly policy review', $auditLog->details['message'] ?? '');
    }

    /**
     * Requirement B: Configuration validation
     */
    public function test_configuration_validation_rejects_invalid_values_and_nonsensical_ranges(): void
    {
        // Negative / zero value rejected
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => -5,
                'confirmed' => 1,
            ])
            ->assertSessionHasErrors(['long_term_booking_threshold_days']);

        // Exceeds max range
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => 9999,
                'confirmed' => 1,
            ])
            ->assertSessionHasErrors(['long_term_booking_threshold_days']);

        // Nonsensical: Price reconfirmation threshold > long term booking threshold
        $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => 60,
                'price_reconfirmation_threshold_days' => 90,
                'confirmed' => 1,
            ])
            ->assertSessionHasErrors(['price_reconfirmation_threshold_days']);
    }

    /**
     * Requirement C: Configuration confirmation behavior
     */
    public function test_setting_is_not_changed_without_explicit_confirmation(): void
    {
        $currLongTerm = Setting::getLongTermBookingThresholdDays(); // 90

        // Submit without 'confirmed' flag
        $response = $this->actingAs($this->admin)
            ->post(route('admin.settings.update'), [
                'long_term_booking_threshold_days' => 100,
                'price_reconfirmation_threshold_days' => 40,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('impact_preview');

        // Setting MUST NOT have changed in DB!
        $this->assertSame($currLongTerm, Setting::getLongTermBookingThresholdDays());
    }

    /**
     * Requirement D: Long-term classification
     */
    public function test_event_outside_threshold_is_classified_as_tentative(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // Event is 120 days away (> 90 days default)
        $booking = $this->createBookingWithItem('2026-10-01');
        $booking->event_date = Carbon::parse('2026-10-01')->addDays(120)->toDateString();
        $booking->save();

        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);

        $this->assertTrue($quotation->is_tentative);
        $this->assertNull($quotation->reconfirmed_at);
        $this->assertTrue($booking->hasTentativePricing());
    }

    public function test_event_inside_threshold_is_not_classified_as_tentative(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // Event is 60 days away (< 90 days default)
        $booking = $this->createBookingWithItem('2026-10-01');
        $booking->event_date = Carbon::parse('2026-10-01')->addDays(60)->toDateString();
        $booking->save();

        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);

        $this->assertFalse($quotation->is_tentative);
        $this->assertNull($quotation->reconfirmed_at);
        $this->assertFalse($booking->hasTentativePricing());
    }

    public function test_exact_boundary_is_not_classified_as_tentative(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // Exactly 90 days away (event_date > 90 qualifies as long-term)
        $booking = $this->createBookingWithItem('2026-10-01');
        $booking->event_date = Carbon::parse('2026-10-01')->addDays(90)->toDateString();
        $booking->save();

        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);

        $this->assertFalse($quotation->is_tentative);
    }

    public function test_long_term_classification_respects_configured_threshold(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // Configure threshold to 60 days
        Setting::setSetting(Setting::KEY_LONG_TERM_THRESHOLD, 60, 'integer');

        // Event is 75 days away (> 60 days)
        $booking = $this->createBookingWithItem('2026-10-01');
        $booking->event_date = Carbon::parse('2026-10-01')->addDays(75)->toDateString();
        $booking->save();

        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);

        $this->assertTrue($quotation->is_tentative);
    }

    /**
     * Requirement E & F: Reconfirmation timing & duplicate alert prevention
     */
    public function test_reconfirmation_timing_and_alert_trigger(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 06:00:00'));

        // Booking with tentative quote, event date in 45 days (outside 30-day threshold)
        $bookingOutside = $this->createBookingWithItem('2026-11-15');
        $qOutside = app(\App\Services\QuotationIssuanceService::class)->issue($bookingOutside, $this->admin->id);
        $qOutside->is_tentative = true;
        $qOutside->save();

        // Booking with tentative quote, event date in 25 days (inside 30-day threshold)
        $bookingInside = $this->createBookingWithItem('2026-10-26');
        $qInside = app(\App\Services\QuotationIssuanceService::class)->issue($bookingInside, $this->admin->id);
        $qInside->is_tentative = true;
        $qInside->save();

        // Booking with NULL event date
        $bookingNullDate = $this->createBookingWithItem('2026-10-26');
        $bookingNullDate->event_date = null;
        $bookingNullDate->save();
        $qNull = app(\App\Services\QuotationIssuanceService::class)->issue($bookingNullDate, $this->admin->id);
        $qNull->is_tentative = true;
        $qNull->save();

        // Run reconfirmation scheduled check
        Artisan::call('alerts:check-price-reconfirmations');

        // Outside threshold -> no alert
        $this->assertFalse(AdminAlert::where('booking_id', $bookingOutside->id)->where('type', 'price_reconfirmation_due')->exists());

        // Inside threshold -> alert created
        $this->assertTrue(AdminAlert::where('booking_id', $bookingInside->id)->where('type', 'price_reconfirmation_due')->exists());

        // NULL event date -> ignored, no alert
        $this->assertFalse(AdminAlert::where('booking_id', $bookingNullDate->id)->where('type', 'price_reconfirmation_due')->exists());

        // Run check again: Duplicate prevention must ensure count remains 1
        Artisan::call('alerts:check-price-reconfirmations');
        $this->assertSame(1, AdminAlert::where('booking_id', $bookingInside->id)->where('type', 'price_reconfirmation_due')->count());
    }

    /**
     * Requirement G: Price unchanged reconfirmation
     */
    public function test_price_unchanged_reconfirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $booking = $this->createBookingWithItem('2026-10-25', ['status' => 'confirmed']);
        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);
        $quotation->status = Quotation::STATUS_ACCEPTED;
        $quotation->is_tentative = true;
        $quotation->save();

        // Existing reconfirmation alert
        AdminAlert::create([
            'type' => 'price_reconfirmation_due',
            'booking_id' => $booking->id,
            'title' => 'Reconfirmation Due',
            'message' => 'Review pricing.',
            'is_read' => false,
        ]);

        // Admin reconfirms unchanged pricing
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-10-25',
            'venue' => 'Grand Floral Ballroom',
            'status' => 'confirmed',
            'action' => 'reconfirm_price_unchanged',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $quotation->refresh();
        $booking->refresh();

        // is_tentative must be false, reconfirmed_at must be populated
        $this->assertFalse($quotation->is_tentative);
        $this->assertNotNull($quotation->reconfirmed_at);

        // Alert dismissed
        $this->assertTrue(AdminAlert::where('booking_id', $booking->id)->where('type', 'price_reconfirmation_due')->first()->is_read);

        // Booking status preserved
        $this->assertSame('confirmed', $booking->status);

        // No new quotation version created
        $this->assertSame(1, Quotation::where('booking_id', $booking->id)->count());

        // Audit log created
        $this->assertTrue(AuditLog::where('action', 'price_reconfirmed_unchanged')->exists());
    }

    /**
     * Requirement H, I, J, K, L: Price changed reconfirmation, versioning, payment integrity, and revised balance
     */
    public function test_price_changed_reconfirmation_creates_new_version_and_preserves_payments(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        // 150 items @ 100.0 wholesale = 15,000 * 2.0 multiplier + 5,000 fixed labor = 35,000.00
        $booking = $this->createBookingWithItem('2026-10-25', [
            'status' => 'downpayment_received',
            'multiplier' => 2.0,
            'labor_rate' => 5000,
        ], 100.0, 150);

        // v1 issued and accepted
        $v1 = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);
        $v1->status = Quotation::STATUS_ACCEPTED;
        $v1->is_tentative = true;
        $v1->save();

        $this->assertEquals(35000.00, (float) $v1->final_quoted_price);

        // Client paid verified downpayment of 10,000
        Payment::create([
            'booking_id' => $booking->id,
            'quotation_id' => $v1->id,
            'amount' => 10000.00,
            'amount_paid' => 10000.00,
            'remaining_balance' => 25000.00,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => 'GCASH-TEST-DP10K',
            'status' => 'downpayment_received',
            'verified_at' => now(),
        ]);

        $this->assertEquals(10000.00, $booking->fresh()->total_paid);
        $this->assertEquals(25000.00, $booking->fresh()->remaining_balance);

        // Existing reconfirmation alert
        AdminAlert::create([
            'type' => 'price_reconfirmation_due',
            'booking_id' => $booking->id,
            'title' => 'Reconfirmation Due',
            'message' => 'Review pricing.',
            'is_read' => false,
        ]);

        // Floral requirements increase from 35,000 to 37,000 (quantity increases to 160: 160 * 100 * 2 + 5000 = 37,000)
        BookingItem::where('booking_id', $booking->id)->update(['quantity' => 160]);

        // Admin issues revised reconfirmation quotation
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-10-25',
            'venue' => 'Grand Floral Ballroom',
            'status' => 'downpayment_received',
            'action' => 'reconfirm_price_revised',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $v1->refresh();

        // Requirement J: Old quotation remains immutable & superseded
        $this->assertSame(Quotation::STATUS_SUPERSEDED, $v1->status);
        $this->assertEquals(35000.00, (float) $v1->final_quoted_price);

        // Requirement I: New version v2 created correctly
        $v2 = Quotation::where('booking_id', $booking->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertSame(Quotation::STATUS_ISSUED, $v2->status);
        $this->assertEquals(37000.00, (float) $v2->final_quoted_price);
        $this->assertFalse($v2->is_tentative);
        $this->assertNotNull($v2->reconfirmed_at);

        // Reconfirmation alert dismissed
        $this->assertTrue(AdminAlert::where('booking_id', $booking->id)->where('type', 'price_reconfirmation_due')->first()->is_read);

        // Booking status set to quotation_sent for client acceptance
        $this->assertSame('quotation_sent', $booking->status);

        // Requirement K: Existing payment remains intact and credited
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count());
        $this->assertEquals(10000.00, $booking->total_paid);

        // Requirement L: Revised remaining balance is 37,000 - 10,000 = 27,000
        $this->assertEquals(27000.00, $booking->remaining_balance);
    }

    /**
     * Requirement M: Client acceptance of revised quotation
     */
    public function test_client_accepts_revised_quotation_and_restores_downpayment_received_status(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $booking = $this->createBookingWithItem('2026-10-25', [
            'status' => 'quotation_sent',
            'final_quoted_price' => 37000.00,
            'total_quoted' => 37000.00,
            'price_valid_until' => Carbon::parse('2026-10-08')->toDateString(),
        ]);

        // Historical v1 superseded
        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_SUPERSEDED,
            'final_quoted_price' => 35000.00,
            'valid_until' => Carbon::parse('2026-10-01')->toDateString(),
            'items_snapshot' => [],
        ]);

        // Active v2 issued
        $v2 = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 2,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 37000.00,
            'valid_until' => Carbon::parse('2026-10-08')->toDateString(),
            'items_snapshot' => [],
        ]);

        // Verified payment exists from earlier
        Payment::create([
            'booking_id' => $booking->id,
            'quotation_id' => $v2->id,
            'amount' => 10000.00,
            'amount_paid' => 10000.00,
            'remaining_balance' => 27000.00,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => 'GCASH-TEST-CLIENTACC',
            'status' => 'downpayment_received',
            'verified_at' => now(),
        ]);

        // Client accepts revised quotation
        $acceptResponse = $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));
        $acceptResponse->assertRedirect();
        $acceptResponse->assertSessionHas('success');

        $booking->refresh();
        $v2->refresh();

        // Quotation v2 accepted
        $this->assertSame(Quotation::STATUS_ACCEPTED, $v2->status);

        // Booking status restored to downpayment_received (because payment is already verified!)
        $this->assertSame('downpayment_received', $booking->status);
        $this->assertEquals(27000.00, $booking->remaining_balance);
    }

    /**
     * Requirement N: Scheduler does not mutate prices, inventory, or bookings
     */
    public function test_scheduler_does_not_mutate_prices_inventory_or_bookings(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 06:00:00'));

        $booking = $this->createBookingWithItem('2026-10-25', [
            'status' => 'confirmed',
        ]);

        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);
        $quotation->status = Quotation::STATUS_ACCEPTED;
        $quotation->is_tentative = true;
        $quotation->save();

        $inventory = InventoryItem::first();
        $initialStock = $inventory->quantity;

        // Run reconfirmation scheduled command
        Artisan::call('alerts:check-price-reconfirmations');

        $booking->refresh();
        $quotation->refresh();
        $inventory->refresh();

        // Prices, inventory stock, and booking status MUST remain unchanged!
        $this->assertEquals(5000.00, (float) $booking->final_quoted_price);
        $this->assertEquals(5000.00, (float) $quotation->final_quoted_price);
        $this->assertSame('confirmed', $booking->status);
        $this->assertSame($initialStock, $inventory->quantity);
    }

    /**
     * Requirement O & P: Existing 7-day quotation validity and acceptance guards remain functional
     */
    public function test_existing_7_day_quotation_validity_remains_functional(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $booking = $this->createBookingWithItem('2026-11-20', [
            'status' => 'quotation_sent',
        ]);
        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);

        $this->assertSame('2026-10-08', $quotation->valid_until->toDateString());
        $this->assertSame('2026-10-08', $booking->price_valid_until->toDateString());

        // Fast forward 10 days (expired)
        Carbon::setTestNow(Carbon::parse('2026-10-11 10:00:00'));

        // Client acceptance rejected due to 7-day expiration
        $response = $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking));
        $response->assertRedirect();
        $response->assertSessionHas('error', 'This quotation has expired due to floral price volatility. Please wait for the admin to re-issue an updated quotation.');
    }

    /**
     * Requirement Q: Inventory reservation is unaffected
     */
    public function test_inventory_reservation_and_stock_remain_unaffected_during_price_reconfirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $booking = $this->createBookingWithItem('2026-10-25', [
            'status' => 'confirmed',
        ]);
        $quotation = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);
        $quotation->status = Quotation::STATUS_ACCEPTED;
        $quotation->is_tentative = true;
        $quotation->save();

        $item = InventoryItem::first();
        $stockBefore = $item->quantity;

        // Admin reconfirms pricing unchanged
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-10-25',
            'venue' => 'Grand Floral Ballroom',
            'status' => 'confirmed',
            'action' => 'reconfirm_price_unchanged',
        ]);

        $item->refresh();

        // Inventory stock MUST NOT change
        $this->assertSame($stockBefore, $item->quantity);

        // No inventory transactions (booking_lock or dispatch) created
        $this->assertSame(0, \App\Models\InventoryTransaction::where('booking_id', $booking->id)->count());
    }

    /**
     * Requirement R: Seasonal substitution behavior remains consistent during reconfirmation
     */
    public function test_seasonal_substitution_behavior_reflects_updated_material_cost_during_reconfirmation(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));

        $booking = $this->createBookingWithItem('2026-10-25', [
            'status' => 'downpayment_received',
            'multiplier' => 2.0,
            'labor_rate' => 1000,
        ], 50.0, 100); // 100 * 50 * 2.0 = 10,000 + 1,000 = 11,000

        $v1 = app(\App\Services\QuotationIssuanceService::class)->issue($booking, $this->admin->id);
        $v1->status = Quotation::STATUS_ACCEPTED;
        $v1->is_tentative = true;
        $v1->save();

        $this->assertEquals(11000.00, (float) $v1->final_quoted_price);

        // Seasonal shortage: Substitute Holland White Hydrangeas with Premium Peonies @ 70.0 unit cost
        $substitute = InventoryItem::create([
            'name' => 'Premium Pink Peonies',
            'unit_cost' => 70.0,
            'quantity' => 150,
            'category' => 'flowers',
        ]);

        $bookingItem = BookingItem::where('booking_id', $booking->id)->first();
        $bookingItem->inventory_item_id = $substitute->id;
        $bookingItem->item_name = $substitute->name;
        $bookingItem->quoted_unit_price = 70.0;
        $bookingItem->save();

        // 100 * 70 * 2.0 = 14,000 + 1,000 labor = 15,000
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'Wedding',
            'event_date' => '2026-10-25',
            'venue' => 'Grand Floral Ballroom',
            'status' => 'downpayment_received',
            'action' => 'reconfirm_price_revised',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $v2 = Quotation::where('booking_id', $booking->id)->where('version', 2)->first();
        $this->assertNotNull($v2);
        $this->assertEquals(15000.00, (float) $v2->final_quoted_price);
        $this->assertSame(Quotation::STATUS_ISSUED, $v2->status);

        // Verify substitute snapshot item name
        $this->assertSame('Premium Pink Peonies', $v2->items_snapshot[0]['item_name']);
    }
}
