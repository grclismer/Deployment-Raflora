<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientBookingChatPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientUser = User::factory()->create(['role' => 'client']);
        $this->client = Client::create(['full_name' => 'Test Client', 'email' => $this->clientUser->email, 'phone' => '09170000000', 'user_id' => $this->clientUser->id]);
    }

    public function test_messages_and_meetings_live_in_the_floating_panel_not_the_page(): void
    {
        $html = $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $this->booking('quotation_sent')))
            ->assertOk()
            ->assertSee('id="client-chat-launcher"', false)
            ->assertSee('data-chat-view="meetings" aria-pressed', false)
            ->getContent();

        $panel = strpos($html, 'id="client-chat-panel"');
        $statusCardEnd = strpos($html, '</section>', strpos($html, 'aria-labelledby="status-action-heading"'));
        $this->assertNotFalse($panel);
        $this->assertGreaterThan($statusCardEnd, $panel, 'The panel sits outside the status card.');
        $this->assertGreaterThan($panel, strpos($html, 'id="booking-conversation-root"'), 'The conversation is inside the panel.');
        $this->assertGreaterThan($panel, strpos($html, 'id="client-meetings"'), 'Meetings are inside the panel.');
    }

    public function test_launcher_shows_the_unread_count_and_meetings_hide_when_not_allowed(): void
    {
        $booking = $this->booking('completed');
        BookingMessage::create(['booking_id' => $booking->id, 'sender_type' => 'admin', 'sender_id' => 1, 'message' => 'Thanks!', 'visibility' => 'client_admin']);

        $html = $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('id="client-chat-panel"', false)
            ->assertDontSee('data-chat-view="meetings" aria-pressed', false)
            ->getContent();

        $this->assertMatchesRegularExpression('/<span data-chat-unread-badge class="(?![^"]*\bhidden\b)[^"]*">1<\/span>/', $html);
    }

    private function booking(string $status): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'status' => $status,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Grand Hall',
        ]);
    }
}
