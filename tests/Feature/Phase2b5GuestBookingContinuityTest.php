<?php

namespace Tests\Feature;

use App\Mail\GuestBookingNotificationMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2b5GuestBookingContinuityTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_notification_email_links_to_permanent_guest_analysis_route(): void
    {
        $guestBooking = Booking::create([
            'guest_name' => 'Test Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Guest St',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'venue' => 'Grand Pavilion',
            'status' => 'quotation_sent',
            'client_id' => null,
            'guest_access_token' => (string) Str::uuid(),
        ]);

        $mail = new GuestBookingNotificationMail($guestBooking, 'status_updated');
        $html = $mail->render();

        $expectedUrl = route('guest.booking.analysis', ['token' => $guestBooking->guest_access_token]);
        $this->assertStringContainsString($expectedUrl, $html);

        $response = $this->get($expectedUrl);
        $response->assertStatus(200);
        $response->assertSee('Wedding Proposal');
        $response->assertSee('Pricing Summary');
    }

    public function test_permanent_guest_booking_status_polling_returns_authoritative_status_and_timestamp(): void
    {
        $guestBooking = Booking::create([
            'guest_name' => 'Polling Guest',
            'guest_email' => 'polling@example.com',
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Garden Hall',
            'status' => 'admin_approved',
            'client_id' => null,
            'guest_access_token' => (string) Str::uuid(),
        ]);

        $response = $this->get(route('guest.bookings.status', ['token' => $guestBooking->guest_access_token]));

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'admin_approved',
            'client_status_label' => $guestBooking->status_display_label,
        ]);
        $this->assertNotEmpty($response->json('updated_at'));
    }

    public function test_temporary_guest_booking_status_polling_returns_pending_and_handles_claimed_state(): void
    {
        $tempBooking = new TemporaryGuestBooking();
        $tempBooking->fill([
            'guest_name' => 'Temp Guest',
            'guest_email' => 'temp@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Temp Address',
            'event_type' => 'corporate',
            'event_date' => Carbon::now()->addDays(10)->toDateString(),
            'venue' => 'Boardroom',
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        $rawToken = $tempBooking->generateToken();
        $tempBooking->save();

        // Active temporary booking returns pending
        $response = $this->get(route('guest.bookings.status', ['token' => $rawToken]));
        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'pending',
        ]);

        // When claimed, returns claimed status with login redirect
        $tempBooking->claimed_at = Carbon::now();
        $tempBooking->save();

        $claimedResponse = $this->get(route('guest.bookings.status', ['token' => $rawToken]));
        $claimedResponse->assertStatus(200);
        $claimedResponse->assertJson([
            'status' => 'claimed',
            'claimed' => true,
            'redirect_url' => route('login'),
        ]);
    }

    public function test_guest_analysis_page_renders_payment_form_when_admin_approved(): void
    {
        $guestBooking = Booking::create([
            'guest_name' => 'Admin Approved Guest',
            'guest_email' => 'approved@example.com',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Manila Hotel',
            'status' => 'admin_approved',
            'client_id' => null,
            'guest_access_token' => (string) Str::uuid(),
            'final_quoted_price' => 50000.00,
        ]);

        $quotation = Quotation::create([
            'booking_id' => $guestBooking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 50000.00,
            'downpayment_percentage' => 50.0,
            'valid_until' => Carbon::now()->addDays(7),
            'issued_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestBooking->guest_access_token]));

        $response->assertStatus(200);
        $response->assertSee('Quotation Approved by Admin');
        $response->assertSee('Log In to Submit Downpayment');
        $response->assertDontSee('Submit Payment Reference');
        $response->assertDontSee(route('guest.bookings.payment.reference', ['booking' => $guestBooking->id]));
    }

    public function test_unclaimed_guest_payment_reference_submission_is_blocked(): void
    {
        $guestBooking = Booking::create([
            'guest_name' => 'Paying Guest',
            'guest_email' => 'paying@example.com',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Manila Hotel',
            'status' => 'admin_approved',
            'client_id' => null,
            'guest_access_token' => (string) Str::uuid(),
            'final_quoted_price' => 60000.00,
        ]);

        $quotation = Quotation::create([
            'booking_id' => $guestBooking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 60000.00,
            'downpayment_percentage' => 50.0,
            'valid_until' => Carbon::now()->addDays(7),
            'issued_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
        ]);

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $guestBooking->id]), [
            'guest_token' => $guestBooking->guest_access_token,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'GCASH-REF-998877',
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $guestBooking->guest_access_token]));
        $response->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');

        $guestBooking->refresh();
        $this->assertEquals('admin_approved', $guestBooking->status);

        $payment = Payment::where('booking_id', $guestBooking->id)->first();
        $this->assertNull($payment);
    }

    public function test_guest_analysis_page_displays_payment_submitted_status_without_stale_quotation_ready_prompt(): void
    {
        $guestBooking = Booking::create([
            'guest_name' => 'Submitted Guest',
            'guest_email' => 'submitted@example.com',
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Sunset Terrace',
            'status' => 'payment_submitted',
            'client_id' => null,
            'guest_access_token' => (string) Str::uuid(),
            'final_quoted_price' => 25000.00,
        ]);

        Quotation::create([
            'booking_id' => $guestBooking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 25000.00,
            'downpayment_percentage' => 50.0,
            'valid_until' => Carbon::now()->addDays(7),
            'issued_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestBooking->guest_access_token]));

        $response->assertStatus(200);
        $response->assertSee('Payment Verification in Progress');
        $response->assertSee('Payment Submitted');
        $response->assertDontSee('Quotation Ready');
        $response->assertDontSee('accept below to proceed');
    }

    public function test_guest_analysis_page_displays_downpayment_received_and_confirmed_states(): void
    {
        $guestBooking = Booking::create([
            'guest_name' => 'Confirmed Guest',
            'guest_email' => 'confirmed@example.com',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(40)->toDateString(),
            'venue' => 'Royal Ballroom',
            'status' => 'confirmed',
            'client_id' => null,
            'guest_access_token' => (string) Str::uuid(),
            'final_quoted_price' => 100000.00,
        ]);

        Quotation::create([
            'booking_id' => $guestBooking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 100000.00,
            'downpayment_percentage' => 50.0,
            'valid_until' => Carbon::now()->addDays(7),
            'issued_at' => Carbon::now(),
            'accepted_at' => Carbon::now(),
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestBooking->guest_access_token]));

        $response->assertStatus(200);
        $response->assertSee('Booking Confirmed');
        $response->assertSee('Your payment has been verified');
    }

    public function test_custom_event_type_up_to_255_chars_persists_in_bookings_table(): void
    {
        $longEventType = '50th Golden Wedding Anniversary and Family Thanksgiving Reunion Gala Celebration';
        $this->assertGreaterThan(50, strlen($longEventType));
        $this->assertLessThanOrEqual(255, strlen($longEventType));

        $clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create([
            'id' => $clientUser->id,
            'email' => $clientUser->email,
            'full_name' => $clientUser->name,
            'phone' => '09171234567',
            'address' => 'Client Address',
        ]);

        $package = \App\Models\Package::create([
            'title' => 'Long Event Test Package',
            'category' => 'wedding',
            'description' => 'Test package for custom event type',
            'price' => 15000.00,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'other',
            'other_event_type' => $longEventType,
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'venue' => 'Grand Ballroom',
            'guest_count' => 100,
        ]);

        $booking = Booking::where('client_id', $client->id)->first();
        $this->assertNotNull($booking);
        $this->assertEquals($longEventType, $booking->event_type);
    }

    public function test_claimed_guest_booking_preserves_custom_event_type_up_to_255_chars_during_conversion(): void
    {
        $longEventType = 'Annual Corporate Strategic Planning and Team Excellence Recognition Summit 2026';
        $this->assertGreaterThan(50, strlen($longEventType));

        $tempBooking = new TemporaryGuestBooking();
        $tempBooking->fill([
            'guest_name' => 'Corporate Coordinator',
            'guest_email' => 'coordinator@corporate.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Corporate Tower',
            'event_type' => $longEventType,
            'event_date' => Carbon::now()->addDays(60)->toDateString(),
            'venue' => 'Convention Center',
            'expires_at' => Carbon::now()->addHours(24),
        ]);
        $rawToken = $tempBooking->generateToken();
        $tempBooking->save();

        $clientUser = User::factory()->create([
            'email' => 'coordinator@corporate.com',
            'email_verified_at' => now(),
            'role' => 'client',
        ]);

        $response = $this->actingAs($clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));

        $response->assertRedirect(route('bookings'));

        $booking = Booking::where('guest_email', 'coordinator@corporate.com')->first();
        $this->assertNotNull($booking);
        $this->assertEquals($longEventType, $booking->event_type);
        $this->assertEquals($clientUser->email, $booking->client->email);
    }

    public function test_claimed_booking_rejects_guest_payment_and_preserves_client_authorization(): void
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create([
            'id' => $clientUser->id,
            'email' => $clientUser->email,
            'full_name' => 'Owned Client',
            'phone' => '09171234567',
            'address' => 'Client St',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'guest_name' => 'Claimed Guest',
            'guest_email' => $clientUser->email,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Manila Hotel',
            'status' => 'admin_approved',
            'guest_access_token' => (string) Str::uuid(),
            'final_quoted_price' => 50000.00,
        ]);

        // Guest payment endpoint must reject claimed booking
        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $booking->guest_access_token,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'UNAUTH-REF',
        ]);

        $response->assertStatus(403);

        // Guest analysis view must reject claimed booking
        $viewResponse = $this->get(route('guest.booking.analysis', ['token' => $booking->guest_access_token]));
        $viewResponse->assertStatus(403);
    }
}
