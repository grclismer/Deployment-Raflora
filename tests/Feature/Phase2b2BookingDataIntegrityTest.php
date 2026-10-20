<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2b2BookingDataIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function createClientUser(string $email = 'client@example.com'): array
    {
        $user = User::factory()->create([
            'email' => $email,
            'name' => 'Test Client',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $client = Client::create([
            'id' => $user->id,
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '09171234567',
            'address' => '123 Bonifacio St, Taguig',
        ]);

        return [$user, $client];
    }

    private function createPresetPackage(): Package
    {
        return Package::create([
            'title' => 'Classic Elegance Package',
            'category' => 'wedding',
            'description' => 'A wonderful preset package for testing.',
            'price' => 25000.00,
            'is_active' => true,
            'is_archived' => false,
        ]);
    }

    /**
     * Requirement A & B: Table count omitted where allowed (nullable).
     */
    public function test_client_booking_with_table_count_omitted_is_nullable(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'Grand Ballroom, Taguig',
            'guest_count' => 120,
            // table_count is omitted / null
        ]);

        $response->assertSessionHasNoErrors();
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertNull($booking->table_count);
        $this->assertEquals('wedding', $booking->event_type);
        $response->assertRedirect(route('bookings.show', $booking));
    }

    /**
     * Requirement A & B: Table count supplied is persisted as integer.
     */
    public function test_client_booking_with_table_count_supplied_persists_integer(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'Grand Ballroom, Taguig',
            'guest_count' => 120,
            'table_count' => 15,
        ]);

        $response->assertSessionHasNoErrors();
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertSame(15, $booking->table_count);
        $this->assertEquals(120, $booking->event_size);
    }

    /**
     * Requirement A & B: Invalid table count is rejected.
     */
    public function test_client_booking_with_invalid_table_count_is_rejected(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Grand Ballroom, Taguig',
            'guest_count' => 120,
            'table_count' => -5,
        ]);

        $response->assertSessionHasErrors(['table_count']);
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Requirement C: Predefined event types continue working as expected.
     */
    public function test_client_booking_with_predefined_event_type_persists_correctly(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Celebration Hall',
            'guest_count' => 80,
        ]);

        $response->assertSessionHasNoErrors();
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertEquals('birthday', $booking->event_type);
    }

    /**
     * Requirement C: Custom event type preserved when "other" is selected.
     */
    public function test_client_booking_with_other_event_type_preserves_custom_value(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'other',
            'other_event_type' => 'Golden Wedding Anniversary',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Private Garden Estate',
            'guest_count' => 100,
            'table_count' => 10,
        ]);

        $response->assertSessionHasNoErrors();
        $booking = Booking::first();
        $this->assertNotNull($booking);
        $this->assertEquals('Golden Wedding Anniversary', $booking->event_type);
        $this->assertSame(10, $booking->table_count);
    }

    /**
     * Requirement C: Selecting "other" without specifying other_event_type fails validation.
     */
    public function test_client_booking_requires_other_event_type_when_other_is_selected(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'other',
            'other_event_type' => '',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'venue' => 'Private Estate',
            'guest_count' => 50,
        ]);

        $response->assertSessionHasErrors(['other_event_type']);
        $this->assertDatabaseCount('bookings', 0);
    }

    /**
     * Requirement B & C: Guest booking preserves custom event type and table count.
     */
    public function test_guest_booking_preserves_custom_event_type_and_table_count(): void
    {
        $package = $this->createPresetPackage();

        $response = $this->post(route('guest.booking.store'), [
            'guest_name' => 'Maria Clara',
            'guest_email' => 'maria@example.com',
            'guest_phone' => '09191234567',
            'guest_address' => '789 Rizal Ave, Makati',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'other',
            'other_event_type' => 'Debutante Gala',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '18:00',
            'end_time' => '22:00',
            'venue' => 'The Glasshouse Makati',
            'guest_count' => 150,
            'table_count' => 15,
        ]);

        $response->assertSessionHasNoErrors();
        $tempBooking = TemporaryGuestBooking::first();
        $this->assertNotNull($tempBooking);
        $this->assertEquals('Debutante Gala', $tempBooking->event_type);
        $this->assertSame(15, $tempBooking->table_count);
        $this->assertEquals(150, $tempBooking->guest_count);
    }

    /**
     * Requirement B & C: Guest-to-permanent conversion preserves table_count and custom event_type.
     */
    public function test_guest_to_permanent_conversion_preserves_table_count_and_custom_event_type(): void
    {
        $email = 'conversion.client@example.com';
        [$user, $client] = $this->createClientUser($email);

        $rawToken = (string) Str::uuid();
        $tempBooking = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => $user->name,
            'guest_email' => $user->email,
            'guest_phone' => '09171234567',
            'guest_address' => '123 Bonifacio St, Taguig',
            'booking_type' => 'custom',
            'event_type' => 'Alumni Grand Reunion',
            'event_date' => Carbon::now()->addDays(45)->toDateString(),
            'event_time' => '17:00:00',
            'venue' => 'University Alumni Center',
            'guest_count' => 200,
            'table_count' => 20,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));

        $response->assertRedirect(route('bookings'));
        $response->assertSessionHas('success');

        $booking = Booking::where('client_id', $client->id)->first();
        $this->assertNotNull($booking);
        $this->assertEquals('Alumni Grand Reunion', $booking->event_type);
        $this->assertSame(20, $booking->table_count);
        $this->assertEquals(200, $booking->event_size);
    }

    /**
     * Requirement D: Validation failures preserve old user inputs.
     */
    public function test_booking_validation_preserves_old_input_on_failure(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $response = $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'other',
            'other_event_type' => 'Charity Fundraiser',
            'event_date' => Carbon::yesterday()->toDateString(), // Invalid date in past
            'venue' => 'Manila Diamond Hotel',
            'guest_count' => 85,
            'table_count' => 9,
        ]);

        $response->assertSessionHasErrors(['event_date']);
        $this->assertEquals('Charity Fundraiser', session('_old_input.other_event_type'));
        $this->assertEquals('Manila Diamond Hotel', session('_old_input.venue'));
        $this->assertEquals('9', session('_old_input.table_count'));
        $this->assertEquals('85', session('_old_input.guest_count'));
    }

    /**
     * Requirement E: Duplicate submission prevention rejects rapid repeated submits.
     */
    public function test_client_duplicate_submission_within_window_is_rejected(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $payload = [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'corporate',
            'event_date' => Carbon::now()->addDays(25)->toDateString(),
            'venue' => 'SMX Convention Center',
            'guest_count' => 300,
            'table_count' => 30,
        ];

        // First submission succeeds
        $firstResponse = $this->actingAs($user)->post(route('bookings.store'), $payload);
        $firstResponse->assertSessionHasNoErrors();
        $this->assertDatabaseCount('bookings', 1);

        $firstBooking = Booking::first();

        // Immediate duplicate submission within 10-second window redirects with notice
        $secondResponse = $this->actingAs($user)->post(route('bookings.store'), $payload);
        $secondResponse->assertRedirect(route('bookings.show', ['booking' => $firstBooking->id]));
        $secondResponse->assertSessionHas('info', 'Your booking request has already been submitted.');
        $this->assertDatabaseCount('bookings', 1);
    }

    /**
     * Requirement E: Guest duplicate submission redirects to existing request without duplicating records.
     */
    public function test_guest_duplicate_submission_within_window_redirects_to_existing_token(): void
    {
        $package = $this->createPresetPackage();

        $payload = [
            'guest_name' => 'Juan Dela Cruz',
            'guest_email' => 'juan@example.com',
            'guest_phone' => '09181234567',
            'guest_address' => '456 Taft Ave, Manila',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'birthday',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'event_time' => '14:00',
            'end_time' => '18:00',
            'venue' => 'Manila Pavilion',
            'guest_count' => 60,
            'table_count' => 6,
        ];

        // First submission succeeds
        $firstResponse = $this->post(route('guest.booking.store'), $payload);
        $firstResponse->assertSessionHasNoErrors();
        $this->assertDatabaseCount('temporary_guest_bookings', 1);

        $tempBooking = TemporaryGuestBooking::first();
        $this->assertNotNull($tempBooking);

        // Immediate duplicate submission redirects to existing guest view
        $secondResponse = $this->post(route('guest.booking.store'), $payload);
        $secondResponse->assertSessionHas('info', 'Your booking request has already been submitted.');
        $this->assertDatabaseCount('temporary_guest_bookings', 1);
    }

    /**
     * Requirement F: Persisted booking analysis view displays table count and has no artificial setTimeout.
     */
    public function test_persisted_booking_view_renders_table_count_and_has_no_artificial_delay(): void
    {
        [$user, $client] = $this->createClientUser();
        $package = $this->createPresetPackage();

        $booking = Booking::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'event_time' => '14:00:00',
            'venue' => 'The Manila Hotel',
            'event_size' => 150,
            'table_count' => 15,
            'status' => 'pending',
            'total_quoted' => 25000,
            'final_quoted_price' => 25000,
        ]);

        $response = $this->actingAs($user)->get(route('bookings.show', $booking));
        $response->assertStatus(200);

        // Verify table count is displayed in rendered HTML
        $response->assertSee('15 tables');

        // Verify artificial delay code is not present in the blade views
        $clientViewContent = file_get_contents(resource_path('views/client/booking-analysis.blade.php'));
        $this->assertStringNotContainsString("setTimeout(function () {\n            if (loadingState)", $clientViewContent);

        $guestViewContent = file_get_contents(resource_path('views/guest/booking-analysis.blade.php'));
        $this->assertStringNotContainsString("setTimeout(function () {\n            if (loadingState)", $guestViewContent);
    }
}
