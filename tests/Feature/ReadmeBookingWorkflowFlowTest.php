<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\StaffChecklistItem;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * End-to-end README booking flow: Guest → Client → Staff, as seen and driven by each role.
 */
class ReadmeBookingWorkflowFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $clientUser;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'flow-admin@example.com');
        $this->staff = $this->user('staff', 'flow-staff@example.com');
        // Extra user so client ids and user ids never coincide by accident.
        $this->user('client', 'flow-other@example.com');
        $this->clientUser = $this->user('client', 'flow-client@example.com');
        $this->client = Client::create([
            'full_name' => 'Flow Client',
            'email' => $this->clientUser->email,
            'phone' => '09170000001',
        ]);
    }

    // ---------------------------------------------------------------------
    // Guest Workflow
    // ---------------------------------------------------------------------

    public function test_guest_tracking_page_shows_awaiting_claim_as_current_stage(): void
    {
        $rawToken = (string) Str::uuid();
        TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Flow Guest',
            'guest_email' => 'flow-guest@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Quezon City',
            'event_type' => 'debut',
            'event_date' => now()->addDays(40)->toDateString(),
            'venue' => 'Garden Venue',
            'expires_at' => now()->addHours(20),
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertOk();
        // Guests see the guest and client stages only; Staff operational stages stay hidden.
        $response->assertSee('Stage 2 of 8 · Awaiting Claim');
        $response->assertSee('data-workflow-stage="request_submitted" data-workflow-state="complete"', false);
        $response->assertSee('data-workflow-stage="awaiting_claim" data-workflow-state="current"', false);
        $response->assertSee('Guest Workflow');
        $response->assertSee('Client Workflow');
        $response->assertDontSee('Staff Workflow');
        $response->assertDontSee('data-workflow-stage="dispatch"', false);
    }

    public function test_permanent_guest_booking_with_expired_quotation_can_still_be_claimed(): void
    {
        $token = (string) Str::uuid();
        Booking::create([
            'guest_name' => 'Legacy Guest',
            'guest_email' => $this->clientUser->email,
            'guest_access_token' => $token,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Legacy Hall',
            'status' => 'quotation_sent',
            'price_valid_until' => now()->subDays(2)->toDateString(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $token]));

        $response->assertOk();
        $response->assertSee('Quotation Expired — Awaiting Update');
        $response->assertSee('CLAIM REQUIRED TO CONTINUE');
        $response->assertSee(route('register', ['guest_token' => $token, 'email' => $this->clientUser->email]));
        $response->assertSee('Create a free account');
        // An expired quotation must not be presented as an expired (unclaimable) guest request.
        $response->assertDontSee('Expired Unclaimed');
        $response->assertDontSee('CLAIM REQUIRED BEFORE EXPIRATION');
    }

    public function test_client_can_claim_permanent_guest_booking_and_continue_in_client_workflow(): void
    {
        $token = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Legacy Guest',
            'guest_email' => strtoupper($this->clientUser->email),
            'guest_phone' => '09179998888',
            'guest_access_token' => $token,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Legacy Hall',
            'status' => 'quotation_sent',
        ]);

        $this->actingAs($this->clientUser)
            ->get(route('client.claim-guest-booking.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('Claim Your Booking Request');

        $response = $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]));

        $response->assertRedirect(route('bookings.show', ['booking' => $booking->id]));
        $booking->refresh();
        $this->assertSame($this->client->id, $booking->client_id);
        $this->assertSame('quotation_sent', $booking->status, 'Claiming must not change the booking status.');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'guest_booking_claimed',
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
            'user_id' => $this->clientUser->id,
        ]);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', ['booking' => $booking->id]))
            ->assertOk()
            ->assertSee('data-workflow-stage="awaiting_claim" data-workflow-state="complete"', false)
            ->assertSee('data-workflow-stage="quotation" data-workflow-state="current"', false);

        // A second claim attempt is rejected without changing ownership.
        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertRedirect(route('client.dashboard'));
    }

    public function test_permanent_guest_booking_claim_requires_matching_verified_email(): void
    {
        $token = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Legacy Guest',
            'guest_email' => 'someone-else@example.com',
            'guest_access_token' => $token,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Legacy Hall',
            'status' => 'pending',
        ]);

        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertForbidden();
        $this->assertNull($booking->fresh()->client_id);

        $unverified = User::create([
            'name' => 'Unverified',
            'email' => 'someone-else@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
        $this->actingAs($unverified)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertRedirect(route('verification.notice'));
        $this->assertNull($booking->fresh()->client_id);

        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => 'not-a-real-token']))
            ->assertNotFound();
    }

    // ---------------------------------------------------------------------
    // Client Workflow: Raflora Review → Material Preparation / Validation → Quotation
    // ---------------------------------------------------------------------

    public function test_admin_completes_raflora_review_and_booking_moves_to_material_validation(): void
    {
        $booking = $this->clientBooking('pending');
        $this->bookingItem($booking, ['is_ai_suggested' => true, 'confirmed_at' => null]);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Complete Raflora Review')
            ->assertSee(route('admin.bookings.complete-review', $booking), false);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.complete-review', $booking));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Raflora Review completed. Material preparation and validation can now proceed.');
        $booking->refresh();
        $this->assertNotNull($booking->reviewed_at);
        $this->assertSame($this->admin->id, $booking->reviewed_by);
        $this->assertSame('pending', $booking->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'raflora_review_completed', 'entity_id' => $booking->id]);
        $this->assertDatabaseHas('client_notifications', [
            'user_id' => $this->clientUser->id,
            'booking_id' => $booking->id,
            'title' => 'Raflora Review Complete',
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertDontSee('Complete Raflora Review')
            ->assertSee('data-workflow-stage="material_validation" data-workflow-state="current"', false);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-workflow-stage="material_validation" data-workflow-state="current"', false);
    }

    public function test_complete_review_is_rejected_outside_pending_unclaimed_or_for_non_admins(): void
    {
        $quoted = $this->clientBooking('quotation_sent');
        $this->actingAs($this->admin)->post(route('admin.bookings.complete-review', $quoted))
            ->assertSessionHas('error', 'Raflora Review can only be completed while the booking is pending review.');
        $this->assertNull($quoted->fresh()->reviewed_at);

        $unclaimed = Booking::create([
            'guest_name' => 'Guest',
            'guest_email' => 'unclaimed@example.com',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Hall',
            'status' => 'pending',
        ]);
        $this->actingAs($this->admin)->post(route('admin.bookings.complete-review', $unclaimed))
            ->assertSessionHas('error', 'This booking must be claimed by a client account before Raflora Review can be completed.');
        $this->assertNull($unclaimed->fresh()->reviewed_at);

        $pending = $this->clientBooking('pending');
        $this->actingAs($this->staff)->post(route('admin.bookings.complete-review', $pending))->assertForbidden();
        $this->actingAs($this->clientUser)->post(route('admin.bookings.complete-review', $pending))->assertForbidden();
        $this->assertNull($pending->fresh()->reviewed_at);
    }

    public function test_confirming_a_material_records_the_raflora_review(): void
    {
        $booking = $this->clientBooking('pending');
        $rose = InventoryItem::create([
            'name' => 'Red Rose',
            'category' => 'flower',
            'is_perishable' => true,
            'current_stock' => 100,
            'unit_cost' => 40,
            'unit' => 'stem',
            'min_stock' => 0,
        ]);
        $item = $this->bookingItem($booking, ['inventory_item_id' => $rose->id, 'item_name' => 'Red Rose', 'is_ai_suggested' => true, 'confirmed_at' => null]);
        $this->bookingItem($booking, ['item_name' => 'Unlinked Foliage', 'is_ai_suggested' => true, 'confirmed_at' => null]);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.items.confirm', ['booking' => $booking->id, 'bookingItem' => $item->id]))
            ->assertSessionHas('success', 'Material requirement confirmed for this booking.');

        $booking->refresh();
        $this->assertNotNull($booking->reviewed_at);
        $this->assertNotNull($item->fresh()->confirmed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'raflora_review_completed', 'entity_id' => $booking->id]);
    }

    public function test_confirm_material_button_is_wired_to_its_own_form(): void
    {
        $booking = $this->clientBooking('pending');
        $rose = InventoryItem::create([
            'name' => 'Blush Rose',
            'category' => 'flower',
            'is_perishable' => true,
            'current_stock' => 100,
            'unit_cost' => 40,
            'unit' => 'stem',
            'min_stock' => 0,
        ]);
        $item = $this->bookingItem($booking, ['inventory_item_id' => $rose->id, 'item_name' => 'Blush Rose', 'is_ai_suggested' => true, 'confirmed_at' => null]);

        $html = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="submit" form="(confirmMaterial_\d+)"[^>]*>Confirm Material<\/button>/', $html);
        preg_match('/<button type="submit" form="(confirmMaterial_\d+)"/', $html, $matches);
        $this->assertStringContainsString(
            '<form id="' . $matches[1] . '" method="POST" action="' . route('admin.bookings.items.confirm', ['booking' => $booking->id, 'bookingItem' => $item->id]) . '"',
            $html
        );
    }

    public function test_sending_the_official_quotation_records_the_review_and_moves_to_quotation(): void
    {
        $booking = $this->clientBooking('pending');
        $this->bookingItem($booking, ['item_name' => 'Roses', 'quoted_unit_price' => 500, 'is_ai_suggested' => false, 'confirmed_at' => now()]);

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->format('Y-m-d'),
            'venue' => 'Flow Venue',
            'status' => 'pending',
            'action' => 'send_quotation',
            'valid_until' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertSessionMissing('error');
        $booking->refresh();
        $this->assertSame('quotation_sent', $booking->status);
        $this->assertNotNull($booking->reviewed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'raflora_review_completed', 'entity_id' => $booking->id]);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $booking))
            ->assertSee('data-workflow-stage="quotation" data-workflow-state="current"', false);
    }

    // ---------------------------------------------------------------------
    // Staff Workflow
    // ---------------------------------------------------------------------

    public function test_staff_event_page_shows_staff_stage_without_client_phase_details(): void
    {
        $booking = $this->clientBooking('confirmed', [
            'staff_id' => $this->staff->id,
            'confirmed_at' => now(),
            'preparation_start_date' => now()->toDateString(),
            'preparation_status' => 'in_preparation',
        ]);

        $response = $this->actingAs($this->staff)->get(route('staff.events.show', $booking));

        $response->assertOk();
        $response->assertSee('Booking Workflow');
        $response->assertSee('data-workflow-stage="preparation_reservation" data-workflow-state="current"', false);
        $response->assertSee('Preparation in progress — no reusable materials require reservation.');
        $response->assertDontSee('Submitted from a registered client account');
    }

    public function test_staff_dashboard_lists_readme_staff_stages_and_current_event_stage(): void
    {
        $this->clientBooking('event_in_progress', ['staff_id' => $this->staff->id, 'confirmed_at' => now()]);

        $response = $this->actingAs($this->staff)->get(route('staff.dashboard'));

        $response->assertOk();
        foreach (['Preparation & Reservation', 'Dispatch', 'Event Execution', 'Material Return', 'Inventory Reconciliation', 'Completion'] as $stage) {
            $response->assertSee($stage);
        }
        $response->assertSee('Workflow stage 11 of 14: Event Execution');
    }

    public function test_admin_dispatch_panel_tracks_reserved_dispatched_and_outstanding_per_item(): void
    {
        $booking = $this->clientBooking('confirmed', ['confirmed_at' => now()]);
        $arch = InventoryItem::create([
            'name' => 'Brass Arch',
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 20,
            'unit_cost' => 100,
            'unit' => 'piece',
            'min_stock' => 0,
        ]);
        $this->bookingItem($booking, ['inventory_item_id' => $arch->id, 'item_name' => 'Brass Arch', 'quantity' => 6, 'confirmed_at' => now()]);
        // Two separate locks for the same item must render as a single dispatch row.
        foreach ([-4, -2] as $change) {
            InventoryTransaction::create([
                'inventory_item_id' => $arch->id,
                'booking_id' => $booking->id,
                'quantity_change' => $change,
                'transaction_type' => 'booking_lock',
                'reason' => 'Reservation Lock',
                'performed_by' => $this->admin->id,
            ]);
        }

        $html = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking))->assertOk()->getContent();

        $formStart = strpos($html, 'action="' . route('admin.bookings.dispatch', $booking) . '"');
        $this->assertNotFalse($formStart, 'Dispatch form must be rendered.');
        $dispatchForm = substr($html, $formStart, strpos($html, '</form>', $formStart) - $formStart);

        $this->assertSame(1, substr_count($dispatchForm, 'name="items[0][inventory_item_id]" value="' . $arch->id . '"'));
        $this->assertStringNotContainsString('name="items[1][inventory_item_id]"', $dispatchForm);
        $this->assertStringContainsString('name="items[0][quantity]" value="6"', $dispatchForm);
        $this->assertStringContainsString('Awaiting Dispatch', $dispatchForm);
        $this->assertStringContainsString('Reserved Materials Ready for Dispatch', $html);
    }

    public function test_completed_return_audit_notifies_the_client_user_account(): void
    {
        $booking = $this->clientBooking('event_completed');
        $frame = InventoryItem::create([
            'name' => 'Rental Frame',
            'category' => 'decor',
            'is_perishable' => false,
            'current_stock' => 0,
            'unit_cost' => 100,
            'unit' => 'piece',
            'min_stock' => 0,
        ]);
        $this->bookingItem($booking, ['inventory_item_id' => $frame->id, 'item_name' => 'Rental Frame', 'quantity' => 2, 'confirmed_at' => now()]);
        InventoryTransaction::create([
            'inventory_item_id' => $frame->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'dispatch',
            'reason' => 'Admin Dispatch',
            'performed_by' => $this->admin->id,
        ]);
        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        $this->assertNotSame($this->client->id, $this->clientUser->id, 'Fixture must keep client and user ids distinct.');

        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => ['quantity_returned' => 2, 'condition' => 'good', 'damage_charge' => 0],
            ],
        ])->assertRedirect();

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertDatabaseHas('client_notifications', [
            'user_id' => $this->clientUser->id,
            'booking_id' => $booking->id,
            'title' => 'Booking completed',
        ]);
        $this->assertSame(1, ClientNotification::where('booking_id', $booking->id)->where('title', 'Booking completed')->count());
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Flow User',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function clientBooking(string $status, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Flow Venue',
            'status' => $status,
        ], $attributes));
    }

    private function bookingItem(Booking $booking, array $attributes = []): BookingItem
    {
        return BookingItem::create(array_merge([
            'booking_id' => $booking->id,
            'item_name' => 'White Rose',
            'quantity' => 10,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
        ], $attributes));
    }
}
