<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3_1b2ClientBookingProgressAndPaymentClarityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = $this->createUser('admin');
    }

    public function test_pending_booking_displays_progress_indicator_and_under_review_status(): void
    {
        [$user, $booking] = $this->createClientBooking('pending');

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        // Client-facing workflow stages; the Staff operational stages appear only once the booking is confirmed
        $response->assertSee('Booking Progress');
        $response->assertSee('Request Submitted');
        $response->assertSee('Awaiting Claim');
        $response->assertSee('Raflora Review');
        $response->assertSee('Material Preparation / Validation');
        $response->assertSee('Quotation');
        $response->assertSee('Approval');
        $response->assertSee('Payment');
        $response->assertSee('Confirmed');
        $response->assertSee('Stage 3 of 8 · Raflora Review');
        $response->assertDontSee('Preparation & Reservation');
        $response->assertDontSee('data-workflow-phase="staff"', false);

        // Current stage: Raflora Review, shown once as the status heading (no repeated status badge)
        $response->assertSee('data-workflow-stage="raflora_review" data-workflow-state="current"', false);
        $response->assertSee('Raflora is reviewing the event details and inspiration before validating materials.');
        $response->assertSee('Your booking request has been received.');

        // Payment form is not rendered
        $response->assertDontSee('Submit Payment Reference');
        $response->assertDontSee('Payment Method');
    }

    public function test_quotation_sent_booking_displays_quotation_ready_and_review_actions(): void
    {
        [$user, $booking] = $this->createClientBooking('quotation_sent');

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        $response->assertSee('Quotation Ready');
        $response->assertSee('data-workflow-stage="quotation" data-workflow-state="current"', false);
        $response->assertSee('Quotation v1 issued — awaiting client review and acceptance');
        $response->assertSee('Accept Quotation');
        $response->assertSee('Request Changes');

        // Payment form must not be visible yet
        $response->assertDontSee('Submit Payment Reference');
    }

    public function test_approved_booking_clearly_communicates_awaiting_admin_final_approval_and_locks_payment(): void
    {
        [$user, $booking] = $this->createClientBooking('approved');

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        // Clear message explaining the waiting boundary
        $response->assertSee('Quotation Accepted — Awaiting Final Admin Approval');
        $response->assertSee('Your quotation has been accepted. Raflora Administration must complete the final booking approval before payment submission becomes available.');

        // Workflow: Quotation is complete and Approval is current; Payment is still upcoming
        $response->assertSee('data-workflow-stage="quotation" data-workflow-state="complete"', false);
        $response->assertSee('data-workflow-stage="approval" data-workflow-state="current"', false);
        $response->assertSee('data-workflow-stage="payment" data-workflow-state="upcoming"', false);
        $response->assertSee('Quotation accepted by the client — awaiting Raflora final approval.');

        // Payment form is strictly hidden for 'approved' status
        $response->assertDontSee('Submit Payment Reference');
        $response->assertDontSee('Payment Method');
    }

    public function test_admin_approved_booking_prompts_payment_and_renders_payment_form(): void
    {
        [$user, $booking] = $this->createClientBooking('admin_approved');

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        $response->assertSee('Payment Required');
        $response->assertSee('Your booking has received Admin final approval. Please submit your payment reference below to secure your booking.');

        // Workflow: Approval complete, Payment in progress
        $response->assertSee('data-workflow-stage="approval" data-workflow-state="complete"', false);
        $response->assertSee('data-workflow-stage="payment" data-workflow-state="current"', false);
        $response->assertSee('Quotation approved by Raflora — downpayment submission is required.');

        // Payment form is visible
        $response->assertSee('Payment Method');
        $response->assertSee('Reference Number');
        $response->assertSee('Submit Payment Reference');
    }

    public function test_payment_submitted_booking_communicates_verification_in_progress(): void
    {
        [$user, $booking] = $this->createClientBooking('payment_submitted');

        Payment::create([
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'reference_number' => 'REF-SUBMITTED-999',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'amount' => 500,
            'status' => 'pending',
        ]);

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        $response->assertSee('Payment Verification in Progress');
        $response->assertSee('data-workflow-stage="payment" data-workflow-state="current"', false);
        $response->assertSee('Payment reference submitted — awaiting Raflora verification.');
        // Must NOT claim booking is fully confirmed yet
        $response->assertDontSee('Booking Confirmed');
    }

    public function test_confirmed_booking_displays_confirmed_status_and_completed_progress(): void
    {
        [$user, $booking] = $this->createClientBooking('confirmed');

        Payment::create([
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'reference_number' => 'REF-VERIFIED-100',
            'payment_type' => 'gcash',
            'payment_option' => 'full',
            'amount' => 1000,
            'status' => 'verified',
        ]);

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        $response->assertSee('Booking Confirmed');
        $response->assertSee('data-workflow-stage="confirmed" data-workflow-state="current"', false);
        $response->assertSee('data-workflow-stage="preparation_reservation" data-workflow-state="upcoming"', false);
        $response->assertDontSee('Event in preparation');
    }

    public function test_confirmed_booking_with_preparation_status_displays_preparation_phase(): void
    {
        [$user, $booking] = $this->createClientBooking('confirmed');
        
        $booking->update([
            'preparation_status' => 'in_preparation',
            'preparation_start_date' => now()->toDateString(),
        ]);

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        // Staff Workflow has started: Confirmed is complete and Preparation & Reservation is current
        $response->assertSee('data-workflow-stage="confirmed" data-workflow-state="complete"', false);
        $response->assertSee('data-workflow-stage="preparation_reservation" data-workflow-state="current"', false);
        $response->assertSee('Preparation & Reservation');

        // Confirmation should now be marked as complete, so we should not see 'Confirmed' as the sublabel of the current step
        // However, the test framework's assertSee is broad. Let's just ensure we get 200 OK and it renders the Preparation phase correctly.
    }

    public function test_rejected_payment_displays_prominent_feedback_above_payment_form(): void
    {
        [$user, $booking] = $this->createClientBooking('admin_approved');

        Payment::create([
            'booking_id' => $booking->id,
            'client_id' => $booking->client_id,
            'reference_number' => 'REF-REJECTED-12345',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'amount' => 500,
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        // Payment rejection callout is visible
        $response->assertSee('Previous Payment Submission Not Verified');
        $response->assertSee('REF-REJECTED-12345');
        $response->assertSee('GCASH');
        $response->assertSee('could not be verified by Raflora Administration.');
        $response->assertSee('Please double-check your payment receipt or transaction slip, verify the reference number, and submit a corrected reference below to secure your booking.');

        // Payment form is still accessible to submit corrected details
        $response->assertSee('Submit Payment Reference');
    }

    public function test_cancelled_booking_displays_terminated_banner_without_misleading_active_steps(): void
    {
        [$user, $booking] = $this->createClientBooking('cancelled');

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        $response->assertSee('Booking Cancelled');
        $response->assertSee('This booking process has been stopped and is no longer active.');
        // Should not have active in-progress dot
        $response->assertDontSee('Current:');
    }

    public function test_declined_booking_displays_terminated_banner(): void
    {
        [$user, $booking] = $this->createClientBooking('declined');

        $response = $this->actingAs($user)->get(route('bookings.analysis', $booking));

        $response->assertOk();
        $response->assertSee('Booking Declined');
        $response->assertSee('This booking process has been stopped and is no longer active.');
        $response->assertDontSee('Current:');
    }

    public function test_unauthorized_client_cannot_view_another_clients_booking_analysis(): void
    {
        [$owner, $booking] = $this->createClientBooking('admin_approved');
        $otherUser = $this->createUser('client');

        Client::create([
            'full_name' => 'Other Client',
            'email' => $otherUser->email,
            'phone' => '09170000009',
            'address' => 'Other address',
        ]);

        $response = $this->actingAs($otherUser)->get(route('bookings.analysis', $booking));

        $response->assertForbidden();
    }

    private function createClientBooking(string $status = 'quotation_sent'): array
    {
        $user = $this->createUser('client');
        $client = Client::create([
            'full_name' => 'Test Client',
            'email' => $user->email,
            'phone' => '09170000000',
            'address' => 'Test Address',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => $status,
            'final_quoted_price' => 1000,
            'total_quoted' => 1000,
            'price_valid_until' => now()->addDays(3)->toDateString(),
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 1000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [
                [
                    'item_id' => 1,
                    'name' => 'Test Floral Centerpiece',
                    'quantity' => 10,
                    'unit_price' => 100,
                    'total_price' => 1000,
                ],
            ],
            'valid_until' => now()->addDays(3)->toDateString(),
        ]);

        return [$user, $booking];
    }

    private function createUser(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => uniqid($role . '-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
