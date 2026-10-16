<?php

namespace Tests\Feature;

use App\Mail\TemporaryGuestBookingMail;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestBookingJourneyTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    /**
     * 1. Successful guest submission creates record and redirects to tracking view.
     */
    public function test_01_successful_guest_submission_creates_temporary_booking_and_redirects(): void
    {
        Mail::fake();

        $package = Package::create([
            'title' => 'Rose Romance Package',
            'category' => 'wedding',
            'description' => 'Test preset package',
            'price' => 35000.00,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $response = $this->post(route('guest.booking.store'), [
            'guest_name' => 'Carla Mendoza',
            'guest_email' => 'carla.mendoza@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '45 Orchid Ave, Makati City',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'event_time' => '14:00',
            'end_time' => '18:00',
            'venue_city' => 'Makati City',
            'venue_specific' => 'Makati Shangri-La',
            'venue' => 'Makati Shangri-La, Makati City',
            'guest_count' => 120,
            'table_count' => 12,
        ]);

        $tempBooking = TemporaryGuestBooking::where('guest_email', 'carla.mendoza@example.com')->first();
        $this->assertNotNull($tempBooking);
        $this->assertSame('Carla Mendoza', $tempBooking->guest_name);
        $this->assertSame('wedding', $tempBooking->event_type);

        $response->assertRedirect();
        Mail::assertSent(TemporaryGuestBookingMail::class, function ($mail) use ($tempBooking) {
            return $mail->tempBooking->id === $tempBooking->id;
        });
    }

    /**
     * 2. Confirmation screen displays Booking Request Received, reference code, and no-payment notice.
     */
    public function test_02_confirmation_screen_displays_booking_request_received_and_no_payment_notice(): void
    {
        $rawToken = (string) Str::uuid();
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Elena Santos',
            'guest_email' => 'elena.santos@example.com',
            'guest_phone' => '09181234567',
            'guest_address' => 'BGC Taguig',
            'booking_type' => 'custom',
            'event_type' => 'debut',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Grand Hyatt Manila',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertOk();
        $response->assertSee('Booking Request Received');
        $response->assertSee('REF-' . strtoupper(substr($rawToken, 0, 8)));
        $response->assertSee('NO PAYMENT IS REQUIRED AT THIS STAGE');
        $response->assertSee('Raflora will review your event details');
        $response->assertSee('Track My Booking');
        $response->assertDontSee('Pay Now');
    }

    /**
     * 3. Guest tracking link exists in submission email with reference number and Track My Booking button.
     */
    public function test_03_submission_email_contains_track_my_booking_link_and_reference_code(): void
    {
        $rawToken = (string) Str::uuid();
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Sofia Ramos',
            'guest_email' => 'sofia.ramos@example.com',
            'guest_phone' => '09191234567',
            'guest_address' => 'Ortigas Center, Pasig',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(60)->toDateString(),
            'venue' => 'Edsa Shangri-La',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $mail = new TemporaryGuestBookingMail($temp, $rawToken);
        $rendered = $mail->render();

        $this->assertStringContainsString('Track My Booking', $rendered);
        $this->assertStringContainsString(route('guest.bookings.show', ['token' => $rawToken]), $rendered);
        $this->assertStringContainsString('REF-' . strtoupper(substr($rawToken, 0, 8)), $rendered);
        $this->assertStringContainsString('At this stage, no payment is required.', $rendered);
        $this->assertStringContainsString('Action Required: Claim Your Booking Request', $rendered);
    }

    /**
     * 4. Tracking link reaches correct guest booking analysis page with matching event data.
     */
    public function test_04_tracking_link_reaches_correct_guest_booking(): void
    {
        $rawToken = (string) Str::uuid();
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Beatrice Reyes',
            'guest_email' => 'beatrice@example.com',
            'guest_phone' => '09201234567',
            'guest_address' => 'Alabang Muntinlupa',
            'booking_type' => 'custom',
            'event_type' => 'anniversary',
            'event_date' => Carbon::now()->addDays(15)->toDateString(),
            'venue' => 'Crimson Hotel',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertOk();
        $response->assertSee('Beatrice Reyes');
        $response->assertSee('Anniversary Proposal');
        $response->assertSee('Crimson Hotel');
    }

    /**
     * 5. Invalid token is rejected with 404.
     */
    public function test_05_invalid_token_is_rejected_with_404(): void
    {
        $response = $this->get(route('guest.bookings.show', ['token' => 'non-existent-token-xyz']));
        $response->assertNotFound();
    }

    /**
     * 6. Claimed booking cannot continue as guest.
     */
    public function test_06_claimed_booking_cannot_continue_as_guest(): void
    {
        $rawToken = (string) Str::uuid();
        $clientUser = User::factory()->create(['role' => 'client', 'email' => 'claimed@example.com']);
        $client = Client::create([
            'id' => $clientUser->id,
            'email' => $clientUser->email,
            'full_name' => 'Claimed Client',
            'phone' => '09171234567',
        ]);

        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Claimed Client',
            'guest_email' => 'claimed@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Quezon City',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'venue' => 'Manila Polo Club',
            'expires_at' => Carbon::now()->addHours(24),
            'claimed_at' => Carbon::now(),
            'client_id' => $client->id,
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));
        $response->assertStatus(403);
    }

    /**
     * 7. Unclaimed guest cannot submit payment reference (non-negotiable payment boundary).
     */
    public function test_07_unclaimed_guest_cannot_submit_payment(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Unclaimed Payer',
            'guest_email' => 'unclaimed.pay@example.com',
            'guest_phone' => '09221234567',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Sofitel Philippine Plaza',
            'status' => 'admin_approved',
            'final_quoted_price' => 50000.00,
        ]);

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REF-UNCLAIMED-TEST',
        ]);

        $response->assertRedirect(route('guest.booking.analysis', ['token' => $guestToken]));
        $response->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');
        $this->assertDatabaseMissing('payments', ['reference_number' => 'REF-UNCLAIMED-TEST']);
    }

    /**
     * 8. Claimed Client can reach existing payment workflow after claim.
     */
    public function test_08_claimed_client_can_reach_payment_workflow(): void
    {
        $clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'paying.client@example.com',
            'email_verified_at' => now(),
        ]);
        $client = Client::create([
            'id' => $clientUser->id,
            'email' => $clientUser->email,
            'full_name' => 'Paying Client',
            'phone' => '09171234567',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'Manila Hotel',
            'status' => 'admin_approved',
            'final_quoted_price' => 45000.00,
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'final_quoted_price' => 45000.00,
            'downpayment_percentage' => 50.0,
            'valid_until' => Carbon::now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($clientUser)->get(route('bookings.show', ['booking' => $booking->id]));
        $response->assertOk();
    }

    /**
     * 9. No browser alert calls exist in guest booking views.
     */
    public function test_09_no_browser_alert_calls_exist_in_guest_views(): void
    {
        $createView = file_get_contents(resource_path('views/guest/booking-create.blade.php'));
        $analysisView = file_get_contents(resource_path('views/guest/booking-analysis.blade.php'));

        $this->assertDoesNotMatchRegularExpression('/(^|\W)alert\s*\(/i', $createView);
        $this->assertDoesNotMatchRegularExpression('/(^|\W)window\.alert\s*\(/i', $createView);
        $this->assertDoesNotMatchRegularExpression('/(^|\W)alert\s*\(/i', $analysisView);
        $this->assertDoesNotMatchRegularExpression('/(^|\W)window\.alert\s*\(/i', $analysisView);
    }

    /**
     * 10. Status timeline renders human-readable stages across booking states.
     */
    public function test_10_status_timeline_renders_human_readable_stages(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Timeline Guest',
            'guest_email' => 'timeline@example.com',
            'guest_phone' => '09251234567',
            'guest_access_token' => $guestToken,
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(35)->toDateString(),
            'venue' => 'Pico Sands Hotel',
            'status' => 'quotation_sent',
            'final_quoted_price' => 75000.00,
        ]);

        $response = $this->get(route('guest.booking.analysis', ['token' => $guestToken]));

        $response->assertOk();
        $response->assertSee('Guest Request Journey');
        $response->assertSee('Request Submitted');
        $response->assertSee('Raflora Review');
        $response->assertSee('Awaiting Claim');
        // Unclaimed permanent guest booking: claim is the current stage, the quotation continues after claiming.
        $response->assertSee('data-workflow-stage="awaiting_claim" data-workflow-state="current"', false);
        $response->assertSee('data-workflow-stage="quotation" data-workflow-state="upcoming"', false);
        $response->assertDontSee('Request Expires');
        // One claim panel with its actions, and the status-specific quotation notice.
        $response->assertSee('CLAIM REQUIRED TO CONTINUE');
        $response->assertSee('Create a free account &amp; Claim', false);
        $response->assertSee('Official Quotation Ready');
        $response->assertDontSee('What Raflora Is Doing');
    }
}
