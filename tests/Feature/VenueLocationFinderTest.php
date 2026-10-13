<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class VenueLocationFinderTest extends TestCase
{
    use RefreshDatabase;

    private function nominatimPlace(array $overrides = []): array
    {
        return array_merge([
            'lat' => '14.5536',
            'lon' => '121.0498',
            'name' => 'Grand Hyatt Manila',
            'display_name' => 'Grand Hyatt Manila, 8th Avenue, Bonifacio Global City, Taguig, Metro Manila, 1634, Philippines',
            'address' => [
                'tourism' => 'Grand Hyatt Manila',
                'road' => '8th Avenue',
                'suburb' => 'Bonifacio Global City',
                'city' => 'Taguig',
                'region' => 'Metro Manila',
                'postcode' => '1634',
                'country' => 'Philippines',
            ],
        ], $overrides);
    }

    public function test_search_returns_normalized_places_limited_to_the_philippines(): void
    {
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([$this->nominatimPlace()])]);

        $response = $this->getJson(route('location.search', ['q' => 'Grand Hyatt Manila']));

        $response->assertOk()->assertExactJson(['results' => [[
            'label' => 'Grand Hyatt Manila, 8th Avenue, Bonifacio Global City, Taguig, Metro Manila, 1634, Philippines',
            'name' => 'Grand Hyatt Manila',
            'specific' => 'Grand Hyatt Manila, 8th Avenue, Bonifacio Global City',
            'city' => 'Taguig',
            'latitude' => 14.5536,
            'longitude' => 121.0498,
        ]]]);

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request->url(), 'countrycodes=ph')
                && str_contains($request->url(), 'q=Grand%20Hyatt%20Manila')
                && $request->hasHeader('User-Agent')
                && !str_contains($request->header('User-Agent')[0], 'GuzzleHttp');
        });
    }

    public function test_search_drops_duplicate_entries_for_the_same_venue(): void
    {
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([
            $this->nominatimPlace(),
            $this->nominatimPlace(['lat' => '14.5531', 'lon' => '121.0493']),
        ])]);

        $this->getJson(route('location.search', ['q' => 'Grand Hyatt Manila']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.latitude', 14.5536);
    }

    public function test_repeated_searches_are_served_from_cache(): void
    {
        Http::fake(['nominatim.openstreetmap.org/search*' => Http::response([$this->nominatimPlace()])]);

        $this->getJson(route('location.search', ['q' => 'Grand Hyatt Manila']))->assertOk();
        $this->getJson(route('location.search', ['q' => '  grand hyatt   manila ']))->assertOk();

        Http::assertSentCount(1);
    }

    public function test_search_reports_unavailable_service_without_breaking_the_form(): void
    {
        Http::fake(['nominatim.openstreetmap.org/*' => Http::response('Bad gateway', 502)]);

        $this->getJson(route('location.search', ['q' => 'Manila Hotel']))
            ->assertStatus(503)
            ->assertJsonPath('results', [])
            ->assertJsonPath('message', 'Location search is unavailable right now. You can still type the venue address manually.');
    }

    public function test_search_requires_at_least_three_characters(): void
    {
        Http::fake();

        $this->getJson(route('location.search', ['q' => 'ab']))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        Http::assertNothingSent();
    }

    public function test_reverse_lookup_keeps_the_exact_pin_and_handles_points_without_an_address(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/reverse?lat=14.55*' => Http::response($this->nominatimPlace()),
            'nominatim.openstreetmap.org/reverse?lat=13*' => Http::response(['error' => 'Unable to geocode']),
        ]);

        $this->getJson(route('location.reverse', ['lat' => 14.5537123, 'lng' => 121.0499456]))
            ->assertOk()
            ->assertJsonPath('result.city', 'Taguig')
            ->assertJsonPath('result.latitude', 14.5537123)
            ->assertJsonPath('result.longitude', 121.0499456);

        $this->getJson(route('location.reverse', ['lat' => 13.0, 'lng' => 120.0]))
            ->assertOk()
            ->assertJsonPath('result', null);

        $this->getJson(route('location.reverse', ['lat' => 120, 'lng' => 121]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lat']);
    }

    public function test_client_booking_saves_the_pinned_venue_coordinates(): void
    {
        $user = User::factory()->create();
        $client = Client::where('email', $user->email)->first() ?? Client::create([
            'id' => $user->id,
            'email' => $user->email,
            'full_name' => $user->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);
        $package = Package::create(['title' => 'Pinned Venue Package', 'price' => 5000, 'is_active' => true]);

        $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'venue' => 'Grand Hyatt Manila, 8th Avenue, Taguig',
            'venue_latitude' => '14.5536000',
            'venue_longitude' => '121.0498000',
        ])->assertSessionHasNoErrors();

        $booking = Booking::where('client_id', $client->id)->firstOrFail();
        $this->assertSame(14.5536, $booking->venue_latitude);
        $this->assertSame(121.0498, $booking->venue_longitude);
        $this->assertTrue($booking->hasVenueCoordinates());
    }

    public function test_client_booking_rejects_an_invalid_or_half_set_pin(): void
    {
        $user = User::factory()->create();
        $package = Package::create(['title' => 'Pinned Venue Package', 'price' => 5000, 'is_active' => true]);
        $payload = [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel, Manila',
        ];

        $this->actingAs($user)->post(route('bookings.store'), $payload + ['venue_latitude' => 200, 'venue_longitude' => 121])
            ->assertSessionHasErrors(['venue_latitude']);

        $this->actingAs($user)->post(route('bookings.store'), $payload + ['venue_latitude' => 14.5])
            ->assertSessionHasErrors(['venue_longitude']);

        $this->assertSame(0, Booking::count());
    }

    public function test_guest_pin_is_kept_on_the_request_and_copied_when_the_booking_is_claimed(): void
    {
        Mail::fake();
        $package = Package::create(['title' => 'Guest Pinned Package', 'price' => 45000, 'is_active' => true, 'is_archived' => false]);

        $this->post(route('guest.booking.store'), [
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
            'venue_latitude' => '14.1153000',
            'venue_longitude' => '120.9621000',
        ])->assertSessionHasNoErrors();

        $temp = TemporaryGuestBooking::where('guest_email', 'samantha.cruz@example.com')->firstOrFail();
        $this->assertSame(14.1153, $temp->venue_latitude);
        $this->assertSame(120.9621, $temp->venue_longitude);

        // Claim it the way the emailed link does.
        $token = Str::random(40);
        $temp->update(['claim_token_hash' => hash('sha256', $token)]);
        $user = User::factory()->create(['email' => 'samantha.cruz@example.com', 'email_verified_at' => now()]);

        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertRedirect(route('bookings'));

        $booking = Booking::where('guest_email', 'samantha.cruz@example.com')->firstOrFail();
        $this->assertSame(14.1153, $booking->venue_latitude);
        $this->assertSame(120.9621, $booking->venue_longitude);
    }

    public function test_booking_forms_include_the_location_finder(): void
    {
        $this->get(route('guest.booking.create'))
            ->assertOk()
            ->assertSee('data-location-finder', false)
            ->assertSee('name="venue_latitude"', false)
            ->assertSee(route('location.search'), false);

        $user = User::factory()->create();
        $this->actingAs($user)->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('data-location-finder', false)
            ->assertSee('name="venue_longitude"', false);
    }

    public function test_admin_and_staff_see_the_venue_map_with_directions_to_the_pin(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Glasshouse Tagaytay, Tagaytay City',
            'venue_latitude' => 14.1153,
            'venue_longitude' => 120.9621,
            'staff_id' => $staff->id,
            'status' => 'approved',
            'total_quoted' => 15000,
        ]);
        $directions = 'https://www.google.com/maps/dir/?api=1&amp;destination=14.1153%2C120.9621';

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-venue-map', false)
            ->assertSee($directions, false)
            ->assertSee('Exact location pinned by the client.');

        $this->actingAs($staff)->get(route('staff.events.show', $booking))
            ->assertOk()
            ->assertSee('Venue location')
            ->assertSee($directions, false);
    }

    public function test_venue_map_falls_back_to_the_address_text_for_unpinned_bookings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = Booking::create([
            'event_type' => 'birthday',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Manila Hotel, Manila',
            'status' => 'pending',
            'total_quoted' => 0,
        ]);

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('https://www.google.com/maps/dir/?api=1&amp;destination=Manila+Hotel%2C+Manila', false)
            ->assertSee('Locating the venue from its address');
    }
}
