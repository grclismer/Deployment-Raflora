<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBookingNumberSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_booking_number_search_works_in_any_form_and_letter_case(): void
    {
        $wanted = $this->booking('Maria Lopez', 'Grand Ballroom');
        $other = $this->booking('Pedro Cruz', 'Garden Hall');

        foreach (["{$wanted->id}", "#{$wanted->id}", "booking {$wanted->id}", "Booking #{$wanted->id}", "BOOKING {$wanted->id}", "bOoKiNg{$wanted->id}", "booking no. {$wanted->id}", "  Booking   #{$wanted->id}  "] as $term) {
            $this->actingAs($this->admin)
                ->get(route('admin.bookings', ['search' => $term]))
                ->assertOk()
                ->assertSee('Maria Lopez')
                ->assertDontSee('Pedro Cruz');
        }

        $this->assertNotSame($wanted->id, $other->id);
    }

    public function test_name_searches_still_work_in_any_letter_case(): void
    {
        $this->booking('Maria Lopez', 'Grand Ballroom');
        $this->booking('Pedro Cruz', 'Garden Hall');

        foreach (['maria', 'MARIA LOPEZ', 'mArIa', 'garden hall'] as $term) {
            $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['search' => $term]))->assertOk();
            str_contains(strtolower($term), 'garden')
                ? $response->assertSee('Pedro Cruz')->assertDontSee('Maria Lopez')
                : $response->assertSee('Maria Lopez')->assertDontSee('Pedro Cruz');
        }
    }

    public function test_guest_requests_can_be_found_by_their_number(): void
    {
        $request = TemporaryGuestBooking::create([
            'status' => 'pending',
            'guest_address' => 'Rosa Street',
            'booking_reference' => 'REF-' . random_int(100000, 999999),
            'guest_name' => 'Rosa Reyes',
            'guest_email' => 'rosa@example.com',
            'guest_phone' => '09170000000',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Rosa Hall',
            'claim_token_hash' => hash('sha256', 'tok-' . uniqid()),
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings', ['stage' => 'request', 'search' => 'Booking #' . $request->id]))
            ->assertOk()
            ->assertSee('Rosa Reyes');
    }

    private function booking(string $clientName, string $venue): Booking
    {
        $client = Client::create(['full_name' => $clientName, 'email' => strtolower(str_replace(' ', '.', $clientName)) . '@example.com', 'phone' => '09170000001']);

        return Booking::create([
            'client_id' => $client->id,
            'status' => 'pending',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => $venue,
        ]);
    }
}
