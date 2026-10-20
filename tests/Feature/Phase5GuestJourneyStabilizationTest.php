<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stabilization Plan — Phase 5: Guest Booking → Protected Access → Account Conversion.
 */
class Phase5GuestJourneyStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientUser = User::create([
            'name' => 'Guest Turned Client',
            'email' => 'p5-guest@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
    }

    public function test_parallel_claim_of_the_same_request_creates_only_one_booking(): void
    {
        [$temp, $token] = $this->temporaryRequest();

        // Simulate another request completing the claim right after this request loaded the record.
        TemporaryGuestBooking::retrieved(function (TemporaryGuestBooking $model): void {
            DB::table('temporary_guest_bookings')
                ->where('id', $model->id)
                ->whereNull('claimed_at')
                ->update(['claimed_at' => now()]);
        });

        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertRedirect(route('client.dashboard'))
            ->assertSessionHas('error', 'This booking request has already been claimed.');

        $this->assertSame(0, Booking::count(), 'The losing claim must not create a booking.');
    }

    public function test_repeated_claim_submission_creates_exactly_one_booking(): void
    {
        [$temp, $token] = $this->temporaryRequest();

        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertRedirect(route('bookings'));
        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $token]))
            ->assertRedirect(route('client.dashboard'));

        $this->assertSame(1, Booking::count());
        $booking = Booking::firstOrFail();
        $client = Client::where('email', $this->clientUser->email)->firstOrFail();
        $this->assertSame($client->id, $booking->client_id);
        $this->assertSame('pending', $booking->status);
        $this->assertSame($client->id, $temp->fresh()->client_id);
        $this->assertNotNull($temp->fresh()->claimed_at);

        // The converted booking keeps the guest's event details.
        $this->assertSame('Garden Pavilion', $booking->venue);
        $this->assertSame('birthday', $booking->event_type);
    }

    public function test_guest_links_reject_invalid_expired_and_claimed_tokens(): void
    {
        $this->get(route('guest.bookings.show', ['token' => 'not-a-real-token']))->assertNotFound();

        [$expired, $expiredToken] = $this->temporaryRequest(now()->subMinute());
        $this->get(route('guest.bookings.show', ['token' => $expiredToken]))->assertStatus(403);
        $this->actingAs($this->clientUser)
            ->post(route('client.claim-guest-booking.claim', ['token' => $expiredToken]))
            ->assertForbidden();
        $this->assertSame(0, Booking::count());

        [$claimed, $claimedToken] = $this->temporaryRequest();
        $this->actingAs($this->clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $claimedToken]));
        auth()->logout();
        $this->get(route('guest.bookings.show', ['token' => $claimedToken]))->assertForbidden();

        // A converted booking is no longer reachable through guest-only routes.
        $converted = Booking::firstOrFail();
        $this->get(route('guest.booking.analysis', ['token' => $claimedToken]))->assertForbidden();
        $this->post(route('guest.bookings.accept', ['booking' => $converted->id]), ['guest_token' => $claimedToken])->assertForbidden();
    }

    public function test_guest_actions_by_numeric_id_require_the_matching_token(): void
    {
        $token = (string) Str::uuid();
        $booking = Booking::create([
            'guest_name' => 'Numeric Guest',
            'guest_email' => 'numeric-guest@example.com',
            'guest_access_token' => $token,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Numeric Hall',
            'status' => 'quotation_sent',
        ]);

        foreach ([[], ['guest_token' => ''], ['guest_token' => 'guessed-token']] as $payload) {
            $this->post(route('guest.bookings.accept', ['booking' => $booking->id]), $payload)->assertForbidden();
            $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), $payload + [
                'reference_number' => 'GUESS-1', 'payment_type' => 'gcash', 'payment_option' => 'downpayment',
            ])->assertForbidden();
        }

        $this->assertSame('quotation_sent', $booking->fresh()->status);
        $this->assertSame(0, $booking->payments()->count());
    }

    public function test_cleanup_removes_only_expired_unclaimed_requests(): void
    {
        [$expired] = $this->temporaryRequest(now()->subHour());
        [$active] = $this->temporaryRequest(now()->addHours(5));
        [$claimedExpired, $claimedToken] = $this->temporaryRequest(now()->addHours(5));
        $this->actingAs($this->clientUser)->post(route('client.claim-guest-booking.claim', ['token' => $claimedToken]));
        $claimedExpired->forceFill(['expires_at' => now()->subHour()])->save();

        $this->artisan('guest-bookings:cleanup')->assertSuccessful();

        $this->assertNull(TemporaryGuestBooking::find($expired->id));
        $this->assertNotNull(TemporaryGuestBooking::find($active->id));
        $this->assertNotNull(TemporaryGuestBooking::find($claimedExpired->id));
        $this->assertSame(1, Booking::count());
    }

    /**
     * @return array{0: TemporaryGuestBooking, 1: string}
     */
    private function temporaryRequest(?Carbon $expiresAt = null): array
    {
        $temp = new TemporaryGuestBooking([
            'guest_name' => 'Guest Turned Client',
            'guest_email' => 'p5-guest@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Quezon City',
            'booking_type' => 'custom_ai',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(25)->toDateString(),
            'event_time' => '16:00',
            'venue' => 'Garden Pavilion',
            'expires_at' => $expiresAt ?? now()->addHours(20),
        ]);
        $token = $temp->generateToken();
        $temp->save();

        return [$temp, $token];
    }
}
