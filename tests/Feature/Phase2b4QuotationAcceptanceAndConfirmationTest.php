<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2b4QuotationAcceptanceAndConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Client $clientProfile;
    protected Booking $booking;
    protected Quotation $activeQuotation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin_phase2b4@example.com',
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'client_phase2b4@example.com',
        ]);

        $this->clientProfile = Client::create([
            'user_id' => $this->clientUser->id,
            'full_name' => 'Phase 2B4 Client',
            'email' => $this->clientUser->email,
            'phone' => '09170000099',
            'address' => '123 Client Lane',
        ]);

        $this->booking = Booking::create([
            'client_id' => $this->clientProfile->id,
            'status' => 'quotation_sent',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Manila Grand Hotel',
            'guest_count' => 150,
            'final_quoted_price' => 25000,
            'total_quoted' => 25000,
            'price_valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $this->activeQuotation = Quotation::create([
            'booking_id' => $this->booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 25000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [
                ['item_name' => 'White Roses', 'quantity' => 50, 'quoted_unit_price' => 500],
            ],
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);
    }

    /**
     * Q-01: Client acceptance displays accurate feedback and advances to approved.
     */
    public function test_q01_client_acceptance_displays_accurate_feedback_and_status(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('bookings.accept', $this->booking));

        $response->assertRedirect(route('bookings.analysis', ['booking' => $this->booking->id]));
        $response->assertSessionHas('success', 'Quotation accepted. Administrative final review is pending. Payment options will become available once approved.');

        $this->booking->refresh();
        $this->activeQuotation->refresh();

        $this->assertSame('approved', $this->booking->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $this->activeQuotation->status);
    }

    /**
     * Q-02: Client cannot accept when no active quotation exists.
     */
    public function test_q02_client_cannot_accept_when_no_active_quotation_exists(): void
    {
        // Delete all quotation records for this booking
        Quotation::where('booking_id', $this->booking->id)->delete();

        $response = $this->actingAs($this->clientUser)
            ->post(route('bookings.accept', $this->booking));

        $response->assertSessionHas('error', 'No active quotation found. Please wait for the admin to issue a quotation.');

        $this->booking->refresh();
        $this->assertSame('quotation_sent', $this->booking->status);
    }

    /**
     * Q-02: Expired active quotation cannot be accepted.
     */
    public function test_q02_expired_active_quotation_cannot_be_accepted(): void
    {
        // Active quotation has expired
        $this->activeQuotation->update(['valid_until' => now()->subDay()->toDateString()]);

        $response = $this->actingAs($this->clientUser)
            ->post(route('bookings.accept', $this->booking));

        $response->assertSessionHas('error', 'This quotation has expired due to floral price volatility. Please wait for the admin to re-issue an updated quotation.');

        $this->booking->refresh();
        $this->assertSame('quotation_sent', $this->booking->status);
        $this->assertSame(Quotation::STATUS_ISSUED, $this->activeQuotation->fresh()->status);
    }

    /**
     * Q-02: Unauthorized client cannot accept booking.
     */
    public function test_q02_unauthorized_client_cannot_accept_another_clients_quotation(): void
    {
        $otherUser = User::factory()->create(['role' => 'client']);

        $response = $this->actingAs($otherUser)
            ->post(route('bookings.accept', $this->booking));

        $response->assertForbidden();
        $this->assertSame('quotation_sent', $this->booking->fresh()->status);
    }

    /**
     * Q-02: Incompatible booking statuses cannot be accepted.
     */
    public function test_q02_incompatible_booking_statuses_cannot_be_accepted(): void
    {
        $invalidStatuses = ['pending', 'change_requested', 'cancellation_requested', 'admin_approved', 'confirmed', 'declined', 'cancelled'];

        foreach ($invalidStatuses as $status) {
            $this->booking->update(['status' => $status]);

            $response = $this->actingAs($this->clientUser)
                ->post(route('bookings.accept', $this->booking));

            $response->assertSessionHas('error');
            $this->assertSame($status, $this->booking->fresh()->status);
        }
    }

    /**
     * Q-03: Administrative final approval creates client notification with payment instructions.
     */
    public function test_q03_admin_final_approval_creates_client_notification(): void
    {
        $this->booking->update(['status' => 'approved']);

        $response = $this->actingAs($this->admin)
            ->post(route('admin.bookings.final-approve', $this->booking));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quotation final-approved. Payment submission is now available.');

        $this->booking->refresh();
        $this->assertSame('admin_approved', $this->booking->status);

        $this->assertDatabaseHas('client_notifications', [
            'user_id' => $this->clientUser->id,
            'booking_id' => $this->booking->id,
            'type' => 'booking_update',
            'title' => 'Quotation Approved — Payment Unlocked',
            'is_read' => false,
        ]);
    }

    /**
     * Q-03: Repeated administrative final approval does not duplicate notifications.
     */
    public function test_q03_repeated_approval_does_not_create_duplicate_notifications(): void
    {
        $this->booking->update(['status' => 'approved']);

        // First approval
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.final-approve', $this->booking));

        $this->assertSame(1, ClientNotification::where('booking_id', $this->booking->id)->count());

        // Repeated request on already approved booking is rejected
        $response = $this->actingAs($this->admin)
            ->post(route('admin.bookings.final-approve', $this->booking));

        $response->assertSessionHas('error', 'This booking is not awaiting Admin final approval.');
        $this->assertSame(1, ClientNotification::where('booking_id', $this->booking->id)->count());
    }

    /**
     * Q-04: Guest analysis view renders acceptance form when active quotation is issued.
     */
    public function test_q04_guest_analysis_renders_acceptance_form_for_eligible_guest(): void
    {
        $guestToken = (string) Str::uuid();
        $guestBooking = Booking::create([
            'guest_name' => 'Maria Guest',
            'guest_email' => 'maria.guest@example.com',
            'guest_phone' => '09180000001',
            'guest_access_token' => $guestToken,
            'status' => 'quotation_sent',
            'event_type' => 'Debut',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Sky Garden Tagaytay',
            'guest_count' => 100,
            'final_quoted_price' => 18000,
            'total_quoted' => 18000,
            'price_valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $quotation = Quotation::create([
            'booking_id' => $guestBooking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 18000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestToken]));

        $response->assertOk();
        $response->assertSee('Official Quotation Ready');
        $response->assertSee('Log In / Register to Review &amp; Accept Quotation', false);
        $response->assertDontSee(route('guest.bookings.accept', ['booking' => $guestBooking->id]));
    }

    /**
     * Phase 2B-6: Unclaimed guest quotation acceptance is blocked; directs to login/claim.
     */
    public function test_q04_unclaimed_guest_acceptance_is_blocked(): void
    {
        $guestToken = (string) Str::uuid();
        $guestBooking = Booking::create([
            'guest_name' => 'Juan Guest',
            'guest_email' => 'juan.guest@example.com',
            'guest_phone' => '09180000002',
            'guest_access_token' => $guestToken,
            'status' => 'quotation_sent',
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Manila Lounge',
            'final_quoted_price' => 12000,
            'price_valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $quotation = Quotation::create([
            'booking_id' => $guestBooking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 12000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->post(route('guest.bookings.accept', ['booking' => $guestBooking->id]), [
            'guest_token' => $guestToken,
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertSessionHas('error', 'Official quotations cannot be accepted by an unclaimed guest. Please log in or create an account to claim your booking first.');

        $guestBooking->refresh();
        $quotation->refresh();

        $this->assertSame('quotation_sent', $guestBooking->status);
        $this->assertSame(Quotation::STATUS_ISSUED, $quotation->status);
    }

    /**
     * Q-04: Guest analysis disables acceptance when quotation is expired.
     */
    public function test_q04_guest_analysis_disables_acceptance_when_quotation_is_expired(): void
    {
        $guestToken = (string) Str::uuid();
        $guestBooking = Booking::create([
            'guest_name' => 'Expired Guest',
            'guest_email' => 'expired.guest@example.com',
            'guest_phone' => '09180000003',
            'guest_access_token' => $guestToken,
            'status' => 'quotation_sent',
            'event_type' => 'Anniversary',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Manila Lounge',
            'final_quoted_price' => 12000,
            'price_valid_until' => now()->subDay()->toDateString(),
        ]);

        Quotation::create([
            'booking_id' => $guestBooking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 12000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => now()->subDay()->toDateString(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestToken]));

        $response->assertOk();
        $response->assertSee('Quotation Expired — Awaiting Update');
        $response->assertDontSee('Accept Quotation</button>', false);
    }

    /**
     * Q-04: Registered client booking does not expose guest acceptance endpoint.
     */
    public function test_q04_registered_client_booking_rejects_guest_acceptance(): void
    {
        // $this->booking has a client_id
        $response = $this->post(route('guest.bookings.accept', ['booking' => $this->booking->id]), [
            'guest_token' => 'any-token',
        ]);

        $response->assertForbidden();
    }
}
