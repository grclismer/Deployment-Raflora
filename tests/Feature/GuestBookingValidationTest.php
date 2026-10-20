<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBookingValidationTest extends TestCase
{
    use RefreshDatabase;

    private function validPackage(): Package
    {
        return Package::create([
            'title' => 'Standard Floral Package',
            'description' => 'Beautiful flowers for events',
            'price' => 5000.00,
            'included_items' => ['Bridal Bouquet', 'Centerpieces'],
            'is_active' => true,
        ]);
    }

    private function baseGuestData(Package $package, array $overrides = []): array
    {
        return array_merge([
            'guest_name' => 'Maria Santos',
            'guest_email' => 'maria.santos@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '123 Sampaguita St, Quezon City',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'event_time' => '14:00',
            'end_time' => '18:00',
            'venue_city' => 'Quezon City',
            'venue_specific' => 'Grand Manila Ballroom',
            'venue' => 'Grand Manila Ballroom, Quezon City',
            'special_requests' => 'White roses preferred',
        ], $overrides);
    }

    public function test_guest_booking_accepts_valid_11_numeric_digit_mobile_number(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_phone' => '09171234567',
        ]);

        $response = $this->post(route('guest.booking.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
            'guest_phone' => '09171234567',
        ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\TemporaryGuestBookingMail::class, function ($mail) {
            return $mail->hasTo('maria.santos@example.com') && $mail->tempBooking->guest_name === 'Maria Santos';
        });
    }

    public function test_guest_booking_rejects_alphabetic_only_mobile_number(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_phone' => 'ABCDEFGHIJK',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_mixed_letters_and_digits_mobile_number(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_phone' => '0917ABC0001',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_fewer_than_11_digits_mobile_number(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_phone' => '0917123456', // 10 digits
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_more_than_11_digits_mobile_number(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_phone' => '091712345678', // 12 digits
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_symbols_or_spaces_mobile_number(): void
    {
        $package = $this->validPackage();

        // With hyphens
        $responseHyphens = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $this->baseGuestData($package, [
            'guest_phone' => '0917-123-456',
        ]));
        $responseHyphens->assertRedirect(route('guest.booking.create'));
        $responseHyphens->assertSessionHasErrors(['guest_phone']);

        // With spaces
        $responseSpaces = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $this->baseGuestData($package, [
            'guest_phone' => '0917 123 456',
        ]));
        $responseSpaces->assertRedirect(route('guest.booking.create'));
        $responseSpaces->assertSessionHasErrors(['guest_phone']);

        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_empty_mobile_number(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_phone' => '',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_preserves_safe_fields_on_validation_failure(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_name' => 'Safe Guest',
            'guest_email' => 'safeguest@example.com',
            'guest_phone' => 'invalid-letters',
            'guest_address' => '456 Preserved St',
            'venue_specific' => 'Preserved Hall',
            'venue_city' => 'Quezon City',
            'venue' => 'Preserved Hall, Quezon City',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);

        // Safe fields remain populated in old input
        $this->assertEquals('Safe Guest', session('_old_input.guest_name'));
        $this->assertEquals('safeguest@example.com', session('_old_input.guest_email'));
        $this->assertEquals('456 Preserved St', session('_old_input.guest_address'));
        $this->assertEquals('Preserved Hall', session('_old_input.venue_specific'));
        $this->assertEquals('Quezon City', session('_old_input.venue_city'));
        $this->assertEquals('Preserved Hall, Quezon City', session('_old_input.venue'));
    }

    public function test_guest_booking_validation_error_messages_are_clear_and_user_friendly(): void
    {
        $package = $this->validPackage();

        // Invalid regex format
        $responseRegex = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $this->baseGuestData($package, [
            'guest_phone' => 'invalid123',
        ]));
        $responseRegex->assertSessionHasErrors([
            'guest_phone' => 'Mobile number must contain exactly 11 digits.',
        ]);

        // Required
        $responseRequired = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $this->baseGuestData($package, [
            'guest_phone' => '',
        ]));
        $responseRequired->assertSessionHasErrors([
            'guest_phone' => 'Mobile number is required.',
        ]);
    }

    public function test_guest_booking_rejects_guest_count_exceeding_db_max(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'guest_count' => 9123456789, // greater than 2147483647
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_count']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_table_count_exceeding_db_max(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'table_count' => 3000000000, // greater than 2147483647
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['table_count']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_view_does_not_contain_native_alert_calls(): void
    {
        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $content = $response->getContent();
        $this->assertStringNotContainsString('alert(', $content, 'Guest booking view should not contain native browser alert() calls.');
        $this->assertStringNotContainsString('window.alert(', $content, 'Guest booking view should not contain window.alert() calls.');
    }

    public function test_guest_booking_view_contains_accessible_inline_validation_containers(): void
    {
        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $response->assertSee('id="step1-ai-error"', false);
        $response->assertSee('id="step1-preset-error"', false);
        $response->assertSee('id="raflora-validation-modal"', false);
        $response->assertSee('id="validation-modal-list"', false);
        $response->assertSee('id="guest_name_error"', false);
        $response->assertSee('id="guest_email_error"', false);
        $response->assertSee('id="guest_phone_error"', false);
        $response->assertSee('id="guest_address_error"', false);
        $response->assertSee('id="event_type_error"', false);
        $response->assertSee('id="event_date_error"', false);
        $response->assertSee('id="event_time_error"', false);
        $response->assertSee('id="end_time_error"', false);
        $response->assertSee('id="venue_city_error"', false);
        $response->assertSee('id="venue_specific_error"', false);
    }

    public function test_guest_booking_rejects_missing_end_time(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'end_time' => '',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['end_time']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_missing_venue_city(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'venue_city' => '',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['venue_city']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_rejects_missing_specific_venue(): void
    {
        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'venue_specific' => '',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['venue_specific']);
        $this->assertDatabaseMissing('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
        ]);
    }

    public function test_guest_booking_accepts_valid_complete_data_with_end_time_and_specific_venue(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $package = $this->validPackage();
        $payload = $this->baseGuestData($package, [
            'end_time' => '19:30',
            'venue_city' => 'Pasig City',
            'venue_specific' => 'The Glasshouse at Ortigas',
        ]);

        $response = $this->post(route('guest.booking.store'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('temporary_guest_bookings', [
            'guest_email' => 'maria.santos@example.com',
            'venue' => 'The Glasshouse at Ortigas, Pasig City',
        ]);
    }

    public function test_guest_booking_validation_redirect_stays_on_step3_for_contact_errors_and_preserves_old_inputs(): void
    {
        $package = $this->validPackage();
        // Submit with invalid phone (Step 3 field in 4-step wizard)
        $payload = $this->baseGuestData($package, [
            'guest_name' => 'Maria Santos',
            'guest_email' => 'maria.santos@example.com',
            'guest_phone' => '123', // invalid
            'guest_address' => '123 Sampaguita St, Quezon City',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['guest_phone']);

        // Follow redirect to guest.booking.create with session errors
        $viewResponse = $this->get(route('guest.booking.create'));
        $viewResponse->assertStatus(200);
        $viewResponse->assertSee('Maria Santos');
        $viewResponse->assertSee('maria.santos@example.com');
        $viewResponse->assertSee('123 Sampaguita St, Quezon City');
        // Initial guest step script evaluates $hasStep3Errors -> step 3
        $content = $viewResponse->getContent();
        $this->assertStringContainsString('const initialGuestStep = 3;', $content);
    }

    public function test_guest_booking_validation_redirect_stays_on_step1_for_event_errors(): void
    {
        $package = $this->validPackage();
        // Submit with invalid event_date (Step 1 field in 4-step wizard)
        $payload = $this->baseGuestData($package, [
            'event_date' => 'invalid-date',
        ]);

        $response = $this->from(route('guest.booking.create'))->post(route('guest.booking.store'), $payload);

        $response->assertRedirect(route('guest.booking.create'));
        $response->assertSessionHasErrors(['event_date']);

        $viewResponse = $this->get(route('guest.booking.create'));
        $viewResponse->assertStatus(200);
        $content = $viewResponse->getContent();
        $this->assertStringContainsString('const initialGuestStep = 1;', $content);
    }
}
