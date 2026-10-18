<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientRequestChangesTypesTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $this->clientUser->email, 'phone' => '09170000000', 'user_id' => $this->clientUser->id]);
        $this->booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'quotation_sent',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Grand Hall',
        ]);
        Quotation::create(['booking_id' => $this->booking->id, 'version' => 1, 'status' => 'issued', 'final_quoted_price' => 5000, 'valid_until' => now()->addDays(7)]);
    }

    public function test_the_popup_has_fields_for_each_change_type(): void
    {
        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $this->booking))
            ->assertOk()
            ->assertSee('data-change-fields="material"', false)
            ->assertSee('data-change-fields="schedule"', false)
            ->assertSee('name="requested_date"', false)
            ->assertSee('Current schedule:');
    }

    public function test_schedule_request_needs_only_a_new_date(): void
    {
        $date = now()->addDays(40);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), [
                'change_type' => 'schedule',
                'requested_date' => $date->toDateString(),
                'requested_start_time' => '14:00',
                'requested_end_time' => '18:00',
                'change_reason' => 'The venue moved us to the afternoon.',
            ])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('change_requested', $this->booking->fresh()->status);
        $this->assertSame(
            "Request Changes:\nType: Schedule\nSchedule: " . $date->format('M j, Y') . ' · 2:00 PM – 6:00 PM (currently ' . now()->addDays(30)->format('M j, Y') . ")\nDetails: The venue moved us to the afternoon.",
            BookingMessage::where('booking_id', $this->booking->id)->value('message')
        );
    }

    public function test_other_request_needs_only_a_description(): void
    {
        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), [
                'change_type' => 'other',
                'change_reason' => 'Please change the ribbon color to champagne gold.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            "Request Changes:\nType: Other\nDetails: Please change the ribbon color to champagne gold.",
            BookingMessage::where('booking_id', $this->booking->id)->value('message')
        );
    }

    public function test_each_type_requires_its_own_fields(): void
    {
        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), ['change_type' => 'material'])
            ->assertSessionHasErrors(['change_item', 'change_quantity']);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), ['change_type' => 'schedule', 'requested_date' => now()->subDay()->toDateString()])
            ->assertSessionHasErrors(['requested_date']);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), ['change_type' => 'schedule', 'requested_date' => now()->addDays(5)->toDateString(), 'requested_start_time' => '15:00', 'requested_end_time' => '09:00'])
            ->assertSessionHasErrors(['requested_end_time']);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), ['change_type' => 'other'])
            ->assertSessionHasErrors(['change_reason']);

        $this->assertSame('quotation_sent', $this->booking->fresh()->status);
        $this->assertFalse(BookingMessage::where('booking_id', $this->booking->id)->exists());
    }

    public function test_the_chat_shows_the_requested_schedule(): void
    {
        $this->actingAs($this->clientUser)->post(route('bookings.request-changes', $this->booking), [
            'change_type' => 'schedule',
            'requested_date' => now()->addDays(40)->toDateString(),
        ]);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $this->booking))
            ->assertSee('Requested schedule:')
            ->assertSee(now()->addDays(40)->format('M j, Y'));
    }
}
