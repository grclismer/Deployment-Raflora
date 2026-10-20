<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use App\Models\TemporaryGuestBooking;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestBookingManualAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private function createAdminPackage(array $attributes = []): Package
    {
        return Package::create(array_merge([
            'title' => 'Deluxe Garden Wedding Package',
            'category' => 'wedding',
            'description' => 'Comprehensive floral arrangements for garden weddings.',
            'price' => 45000.00,
            'included_items' => [
                '1x Cascading Bridal Bouquet',
                '5x Bridesmaid Posies',
                '10x Table Floral Centerpieces',
                '2x Entrance Floral Arches',
            ],
            'is_active' => true,
            'is_archived' => false,
        ], $attributes));
    }

    private function validGuestPayload(Package $package, array $overrides = []): array
    {
        return array_merge([
            'guest_name' => 'Samantha Cruz',
            'guest_email' => 'samantha.cruz@example.com',
            'guest_phone' => '09179876543',
            'guest_address' => '78 Magnolia Lane, Quezon City',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'Tagaytay City',
            'venue_specific' => 'The Glasshouse Tagaytay',
            'venue' => 'The Glasshouse Tagaytay, Tagaytay City',
            'special_requests' => 'Soft pastel palette with eucalyptus leaves.',
        ], $overrides);
    }

    public function test_01_guest_booking_with_valid_data_succeeds_and_creates_request(): void
    {
        Mail::fake();
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package);

        $response = $this->post(route('guest.booking.store'), $payload);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $tempBooking = TemporaryGuestBooking::where('guest_email', 'samantha.cruz@example.com')->first();
        $this->assertNotNull($tempBooking);
        $this->assertEquals('Samantha Cruz', $tempBooking->guest_name);
        $this->assertEquals('The Glasshouse Tagaytay, Tagaytay City', $tempBooking->venue);
        $this->assertEquals('preset', $tempBooking->booking_type);
        $this->assertEquals($package->id, $tempBooking->package_id);

        // Prove it is a request, not a confirmed booking
        $this->assertDatabaseMissing('bookings', [
            'guest_email' => 'samantha.cruz@example.com',
            'status' => 'confirmed',
        ]);
    }

    public function test_02_missing_full_name_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['guest_name' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_name']);
    }

    public function test_03_missing_email_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['guest_email' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_email']);
    }

    public function test_04_missing_mobile_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['guest_phone' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);
    }

    public function test_05_missing_physical_address_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['guest_address' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_address']);
    }

    public function test_06_missing_event_type_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['event_type' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['event_type']);
    }

    public function test_07_missing_event_date_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['event_date' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['event_date']);
    }

    public function test_08_missing_start_time_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['event_time' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['event_time']);
    }

    public function test_09_missing_end_time_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, ['end_time' => '']);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['end_time']);
    }

    public function test_10_missing_venue_city_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, [
            'venue_city' => '',
            'venue' => '',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['venue_city']);
    }

    public function test_11_missing_specific_venue_fails(): void
    {
        $package = $this->createAdminPackage();
        $payload = $this->validGuestPayload($package, [
            'venue_specific' => '',
            'venue' => '',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['venue_specific']);
    }

    public function test_12_step1_displays_authoritative_admin_package_data(): void
    {
        $package = $this->createAdminPackage([
            'title' => 'Signature Orchid Grand Package',
            'price' => 78000.00,
            'description' => 'Exquisite orchid styling for high-profile gatherings.',
            'included_items' => ['Orchid Archway', 'Crystal Candelabras'],
            'is_active' => true,
        ]);

        $response = $this->get(route('guest.booking.create'));

        $response->assertOk();
        $response->assertSee('Signature Orchid Grand Package');
        $response->assertSee('₱78,000');
        $response->assertSee('Exquisite orchid styling');
        $response->assertSee('Orchid Archway');
        $response->assertSee('Crystal Candelabras');
    }

    public function test_13_inactive_or_archived_admin_packages_are_not_shown(): void
    {
        $activePackage = $this->createAdminPackage(['title' => 'Visible Active Package', 'is_active' => true]);
        $inactivePackage = Package::create([
            'title' => 'Hidden Inactive Package',
            'category' => 'wedding',
            'price' => 20000,
            'is_active' => false,
            'is_archived' => false,
        ]);
        $archivedPackage = Package::create([
            'title' => 'Hidden Archived Package',
            'category' => 'wedding',
            'price' => 25000,
            'is_active' => true,
            'is_archived' => true,
        ]);

        $response = $this->get(route('guest.booking.create'));

        $response->assertOk();
        $response->assertSee('Visible Active Package');
        $response->assertDontSee('Hidden Inactive Package');
        $response->assertDontSee('Hidden Archived Package');
    }

    public function test_14_validation_modal_and_error_containers_exist_without_browser_alert(): void
    {
        $response = $this->get(route('guest.booking.create'));

        $response->assertOk();
        $content = $response->getContent();

        // Raflora validation modal elements
        $response->assertSee('id="raflora-validation-modal"', false);
        $response->assertSee('id="validation-modal-title"', false);
        $response->assertSee('id="validation-modal-list"', false);
        $response->assertSee('id="validation-modal-close-btn"', false);

        // Required field error elements
        $response->assertSee('id="end_time_error"', false);
        $response->assertSee('id="venue_city_error"', false);
        $response->assertSee('id="venue_specific_error"', false);

        // Review & Submit container elements
        $response->assertSee('id="review-package-inclusions"', false);
        $response->assertSee('id="review-venue-city"', false);
        $response->assertSee('id="review-venue-specific"', false);
        $response->assertSee('id="review-contact-name"', false);
        $response->assertSee('id="review-contact-email"', false);
        $response->assertSee('id="review-contact-phone"', false);
        $response->assertSee('id="review-contact-address"', false);

        // Zero browser native alert/confirm
        $this->assertStringNotContainsString('alert(', $content);
        $this->assertStringNotContainsString('window.alert(', $content);
        $this->assertStringNotContainsString('confirm(', $content);
        $this->assertStringNotContainsString('window.confirm(', $content);
    }

    public function test_15_tracking_view_preserves_7_stages_and_distinguishes_catalog_price_from_quote(): void
    {
        $rawToken = (string) Str::uuid();
        $package = $this->createAdminPackage();

        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Beatriz Alonzo',
            'guest_email' => 'beatriz@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Makati City',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Makati Shangri-La, Makati City',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        $response = $this->get(route('guest.bookings.show', ['token' => $rawToken]));

        $response->assertOk();
        $response->assertSee('Booking Request Received');
        $response->assertSee('NO PAYMENT IS REQUIRED AT THIS STAGE');
        $response->assertSee('Guest Request Journey');

        // README Guest Workflow stages (Request Submitted → Awaiting Claim) followed by the Client Workflow
        $response->assertSee('Request Submitted');
        $response->assertSee('Awaiting Claim');
        $response->assertSee('Raflora Review');
        $response->assertDontSee('Request Expires');
        $response->assertSee('CLAIM REQUIRED BEFORE EXPIRATION');
        $response->assertSee('Create Account &amp; Claim', false);

        // Catalog price vs official quote notice
        $response->assertSee('Catalog Package Price');
        $response->assertSee('Official quotation prepared after Raflora review');

        // Package inclusions rendered from admin
        $response->assertSee('1x Cascading Bridal Bouquet');
        $response->assertSee('5x Bridesmaid Posies');
    }

    public function test_16_unclaimed_guest_cannot_make_payment(): void
    {
        $rawToken = (string) Str::uuid();
        $temp = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Unclaimed Guest',
            'guest_email' => 'unclaimed@example.com',
            'guest_phone' => '09171112233',
            'guest_address' => 'Manila',
            'booking_type' => 'preset',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Manila Hotel',
            'expires_at' => Carbon::now()->addHours(24),
        ]);

        // Attempt to submit payment reference directly
        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $temp->id]), [
            'payment_reference' => 'REF-99887766',
        ]);

        // Route blocks or 404/403/redirects because guest booking is not client-authenticated
        $this->assertTrue($response->status() === 403 || $response->status() === 404 || $response->isRedirect());
    }
}
