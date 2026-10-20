<?php

namespace Tests\Feature;

use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClientClaimBookingVisualTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedClient(string $email = 'client@example.com'): User
    {
        return User::create([
            'name' => 'Jane Client',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }

    private function createTemporaryGuestBooking(string $email = 'client@example.com', array $attributes = []): array
    {
        $token = Str::random(40);
        $temp = TemporaryGuestBooking::create(array_merge([
            'claim_token_hash' => hash('sha256', $token),
            'guest_name' => 'Jane Client',
            'guest_email' => $email,
            'guest_phone' => '09123456789',
            'guest_address' => '123 Flora St, Manila',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(20)->toDateString(),
            'event_time' => '16:00:00',
            'venue' => 'Sky Garden Grand Venue',
            'guest_count' => 150,
            'table_count' => 15,
            'special_requests' => 'Warm white lighting with blush roses.',
            'expires_at' => Carbon::now()->addHours(24),
        ], $attributes));

        return [$temp, $token];
    }

    public function test_claim_booking_page_renders_with_light_client_ui_elements(): void
    {
        $user = $this->createVerifiedClient('jane@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('jane@example.com');

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertOk();
        $response->assertSee('rf-panel', false);
        $response->assertSee('Guest Booking Claim');
        $response->assertSee('Claim Your Booking Request');
        $response->assertSee('Verified Account Match');
        $response->assertSee('jane@example.com');
    }

    public function test_claim_booking_displays_event_details_and_request_overview(): void
    {
        $user = $this->createVerifiedClient('sarah@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('sarah@example.com', [
            'event_type' => 'Golden Anniversary',
            'venue' => 'Heritage Ballroom',
            'guest_count' => 200,
            'table_count' => 20,
            'special_requests' => 'All white roses and gold centerpieces.',
        ]);

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertOk();
        $response->assertSee('Golden Anniversary');
        $response->assertSee('Heritage Ballroom');
        $response->assertSee('200 guests');
        $response->assertSee('20 tables');
        $response->assertSee('All white roses and gold centerpieces.');
    }

    public function test_claim_booking_displays_next_steps_guidance(): void
    {
        $user = $this->createVerifiedClient('nextsteps@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('nextsteps@example.com');

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertOk();
        $response->assertSee('What Happens Next');
        $response->assertSee('Account Linked');
        $response->assertSee('Admin Quotation');
        $response->assertSee('Review & Payment', false);
    }

    public function test_claim_booking_form_contains_csrf_and_correct_action_and_buttons(): void
    {
        $user = $this->createVerifiedClient('form@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('form@example.com');

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertOk();
        $response->assertSee(route('client.claim-guest-booking.claim', ['token' => $token]), false);
        $response->assertSee('Yes, Claim &amp; Submit Request', false);
        $response->assertSee(route('client.dashboard'), false);
    }

    public function test_unauthenticated_user_cannot_access_claim_page(): void
    {
        [$temp, $token] = $this->createTemporaryGuestBooking('guest@example.com');

        $response = $this->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertRedirect('/login');
    }

    public function test_unverified_client_cannot_access_claim_page(): void
    {
        $user = User::create([
            'name' => 'Unverified Client',
            'email' => 'unverified@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => null,
        ]);

        [$temp, $token] = $this->createTemporaryGuestBooking('unverified@example.com');

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertRedirect(route('verification.notice'));
    }

    public function test_client_with_mismatched_email_receives_403(): void
    {
        $user = $this->createVerifiedClient('legitimate@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('different@example.com');

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertStatus(403);
    }

    public function test_invalid_token_returns_404(): void
    {
        $user = $this->createVerifiedClient('valid@example.com');

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => 'invalid-token-12345']));

        $response->assertStatus(404);
    }

    public function test_expired_token_returns_403(): void
    {
        $user = $this->createVerifiedClient('expired@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('expired@example.com', [
            'expires_at' => Carbon::now()->subMinute(),
        ]);

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertStatus(403);
    }

    public function test_already_claimed_booking_redirects_with_error(): void
    {
        $user = $this->createVerifiedClient('claimed@example.com');
        [$temp, $token] = $this->createTemporaryGuestBooking('claimed@example.com', [
            'claimed_at' => Carbon::now()->subHour(),
        ]);

        $response = $this->actingAs($user)->get(route('client.claim-guest-booking.show', ['token' => $token]));

        $response->assertRedirect(route('client.dashboard'));
        $response->assertSessionHas('error');
    }
}
