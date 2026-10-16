<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBookingUpdatesTest extends TestCase
{
    use RefreshDatabase;

    private function createClientUser(string $email = 'client@example.com'): array
    {
        $user = User::create([
            'name' => 'Jane Client',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $client = Client::create([
            'full_name' => 'Jane Client',
            'email' => $email,
            'phone' => '09123456789',
        ]);

        return [$user, $client];
    }

    private function createBookingForClient(Client $client, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'client_id' => $client->id,
            'event_type' => 'Wedding',
            'event_date' => now()->addWeeks(2)->toDateString(),
            'event_time' => '15:00:00',
            'venue' => 'Sunset Pavilion, Manila',
            'guest_count' => 120,
            'status' => 'pending',
            'final_quoted_price' => 75000.00,
        ], $attributes));
    }

    public function test_client_booking_updates_page_renders_for_authorized_owner(): void
    {
        [$user, $client] = $this->createClientUser();
        $booking = $this->createBookingForClient($client);

        $response = $this->actingAs($user)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee('rf-panel', false);
        $response->assertSee('Wedding Updates');
        $response->assertSee('Booking Review');
        $response->assertSee('View Booking');
        $response->assertSee('Back to Bookings');
    }

    public function test_existing_booking_information_is_displayed(): void
    {
        [$user, $client] = $this->createClientUser();
        $booking = $this->createBookingForClient($client, [
            'event_type' => 'Anniversary Gala',
            'venue' => 'Grand Ballroom',
            'final_quoted_price' => 54321.50,
            'status' => 'confirmed',
        ]);
        Quotation::create([
            'booking_id' => $booking->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 54321.50,
        ]);

        $response = $this->actingAs($user)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee('Anniversary Gala Updates');
        $response->assertSee('Grand Ballroom');
        $response->assertSee('₱54,321.50');
        $response->assertSee('Confirmed');
        $response->assertSee('rf-badge--success', false);
    }

    public function test_latest_admin_notes_and_parsed_sections_render_properly(): void
    {
        [$user, $client] = $this->createClientUser();

        $formattedNotes = "We have reviewed your request and made updates.\n\n"
            . "ITEMS:\n- White Roses x 50\n- Fairy Lights x 10\n\n"
            . "REMOVED ITEMS:\n- Plastic Arch x 1\n\n"
            . "PRICE CHANGES:\n- Stage Backdrop: 5000 -> 6500";

        $booking = $this->createBookingForClient($client, [
            'admin_notes' => $formattedNotes,
        ]);

        $response = $this->actingAs($user)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee('Latest Admin Update');
        $response->assertSee('We have reviewed your request and made updates.');
        $response->assertSee('White Roses x 50');
        $response->assertSee('Fairy Lights x 10');
        $response->assertSee('Removed items');
        $response->assertSee('Plastic Arch x 1');
        $response->assertSee('Price changes');
        $response->assertSee('Stage Backdrop: 5000 -&gt; 6500', false);
    }

    public function test_update_timeline_renders_booking_specific_notifications(): void
    {
        [$user, $client] = $this->createClientUser();
        $booking = $this->createBookingForClient($client);

        ClientNotification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'title' => 'Quotation Sent',
            'message' => 'Your custom wedding quotation has been prepared.',
            'is_read' => false,
        ]);

        ClientNotification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'title' => 'Initial Review Completed',
            'message' => 'Staff has reviewed the venue requirements.',
            'is_read' => true,
        ]);

        $response = $this->actingAs($user)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee('Update Timeline');
        $response->assertSee('2 entries');
        $response->assertSee('Quotation Sent');
        $response->assertSee('Your custom wedding quotation has been prepared.');
        $response->assertSee('Initial Review Completed');
        $response->assertSee('Staff has reviewed the venue requirements.');
        $response->assertSee('New'); // Unread notification badge
    }

    public function test_empty_timeline_displays_empty_state(): void
    {
        [$user, $client] = $this->createClientUser();
        $booking = $this->createBookingForClient($client);

        $response = $this->actingAs($user)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee('No updates have been posted for this booking yet.');
    }

    public function test_navigation_action_links_are_correct(): void
    {
        [$user, $client] = $this->createClientUser();
        $booking = $this->createBookingForClient($client);

        $response = $this->actingAs($user)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee(route('bookings.analysis', ['booking' => $booking->id]), false);
        $response->assertSee(route('bookings'), false);
    }

    public function test_unauthenticated_guest_is_redirected_to_login(): void
    {
        [$user, $client] = $this->createClientUser();
        $booking = $this->createBookingForClient($client);

        $response = $this->get(route('client.booking.updates', $booking));

        $response->assertRedirect(route('login'));
    }

    public function test_unauthorized_client_cannot_access_another_clients_booking_updates(): void
    {
        [$ownerUser, $ownerClient] = $this->createClientUser('owner@example.com');
        [$intruderUser, $intruderClient] = $this->createClientUser('intruder@example.com');

        $booking = $this->createBookingForClient($ownerClient);

        $response = $this->actingAs($intruderUser)->get(route('client.booking.updates', $booking));

        $response->assertStatus(403);
    }

    public function test_admin_can_access_client_booking_updates_for_supervision(): void
    {
        [$ownerUser, $ownerClient] = $this->createClientUser();
        $booking = $this->createBookingForClient($ownerClient);

        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin@raflora.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('client.booking.updates', $booking));

        $response->assertOk();
        $response->assertSee('Wedding Updates');
    }
}
