<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\BookingScheduleRequest;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleChangeRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $clientUser;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $this->clientUser->email, 'phone' => '09170000000', 'user_id' => $this->clientUser->id]);
        $this->booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'quotation_sent',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '09:00',
            'end_time' => '12:00',
            'venue' => 'Grand Hall',
        ]);
        Quotation::create(['booking_id' => $this->booking->id, 'version' => 1, 'status' => 'issued', 'final_quoted_price' => 5000, 'valid_until' => now()->addDays(7)]);
    }

    public function test_client_schedule_request_is_saved_for_admin_and_a_new_one_replaces_the_old(): void
    {
        $first = $this->requestSchedule(40);

        $this->assertSame(BookingScheduleRequest::STATUS_PENDING, $first->status);
        $this->assertSame(now()->addDays(40)->toDateString(), $first->requested_date->toDateString());
        $this->assertSame(now()->addDays(30)->toDateString(), $first->previous_date->toDateString());
        $this->assertNotNull($first->booking_message_id);

        $this->booking->refresh()->update(['status' => 'quotation_sent']);
        $second = $this->requestSchedule(45);

        $this->assertSame(BookingScheduleRequest::STATUS_SUPERSEDED, $first->fresh()->status);
        $this->assertSame(BookingScheduleRequest::STATUS_PENDING, $second->status);
    }

    public function test_other_change_types_do_not_create_a_schedule_request(): void
    {
        $this->actingAs($this->clientUser)->post(route('bookings.request-changes', $this->booking), [
            'change_type' => 'other',
            'change_reason' => 'Different ribbon please.',
        ]);

        $this->assertFalse(BookingScheduleRequest::exists());
    }

    public function test_admin_quotation_section_shows_the_request_with_availability(): void
    {
        $vase = InventoryItem::create(['name' => 'Glass Vase', 'category' => 'props', 'is_perishable' => false, 'current_stock' => 10, 'unit_cost' => 50, 'min_stock' => 1, 'unit' => 'piece']);
        $this->booking->bookingItems()->create(['inventory_item_id' => $vase->id, 'item_name' => 'Glass Vase', 'quantity' => 6, 'confirmed_at' => now()]);
        $other = Booking::create(['status' => 'downpayment_received', 'event_type' => 'birthday', 'event_date' => now()->addDays(40)->toDateString(), 'venue' => 'Other Hall']);
        $other->bookingItems()->create(['inventory_item_id' => $vase->id, 'item_name' => 'Glass Vase', 'quantity' => 7, 'confirmed_at' => now()]);

        $request = $this->requestSchedule(40);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $this->booking))
            ->assertOk()
            ->assertSee('id="schedule-change-request"', false)
            ->assertSee($request->requestedLabel())
            ->assertSee('1 other booking on this date')
            ->assertSee('#' . $other->id . ' Birthday')
            ->assertSee('Glass Vase: needs 6, only 3 free that day.')
            ->assertSee(route('admin.bookings.schedule-requests.decide', [$this->booking, $request]), false);
    }

    public function test_accepting_moves_the_event_and_tells_the_client_on_both_ends(): void
    {
        $request = $this->requestSchedule(40, '14:00', '18:00');

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.schedule-requests.decide', [$this->booking, $request]), ['decision' => 'accept', 'decision_note' => 'See you then!'])
            ->assertSessionHas('success');

        $booking = $this->booking->fresh();
        $this->assertSame(now()->addDays(40)->toDateString(), $booking->event_date->toDateString());
        $this->assertStringStartsWith('14:00', (string) $booking->event_time);
        $this->assertStringStartsWith('18:00', (string) $booking->end_time);
        // The quotation is still sent by Admin with "Approve & Send Official Quote".
        $this->assertSame('change_requested', $booking->status);

        $request->refresh();
        $this->assertSame(BookingScheduleRequest::STATUS_ACCEPTED, $request->status);
        $this->assertSame($this->admin->id, (int) $request->decided_by);

        $reply = BookingMessage::where('booking_id', $booking->id)->where('sender_type', 'admin')->latest('id')->first();
        $this->assertSame('client_admin', $reply->visibility);
        $this->assertStringContainsString('Schedule change accepted: your event is now on ' . $request->requestedLabel(), $reply->message);
        $this->assertStringContainsString('Note: See you then!', $reply->message);
        $this->assertTrue(ClientNotification::where('booking_id', $booking->id)->where('title', 'Schedule change accepted')->exists());

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $booking))
            ->assertSee('Schedule change accepted')
            ->assertSee('Your event is now on ' . $request->requestedLabel());

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertDontSee('id="schedule-change-request"', false);
    }

    public function test_declining_keeps_the_date_and_tells_the_client_why(): void
    {
        $request = $this->requestSchedule(40);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.schedule-requests.decide', [$this->booking, $request]), ['decision' => 'decline', 'decision_note' => 'We are fully booked that day.'])
            ->assertSessionHas('success');

        $this->assertSame(now()->addDays(30)->toDateString(), $this->booking->fresh()->event_date->toDateString());
        $this->assertSame(BookingScheduleRequest::STATUS_DECLINED, $request->fresh()->status);
        $this->assertTrue(BookingMessage::where('booking_id', $this->booking->id)->where('sender_type', 'admin')->where('message', 'like', 'Schedule change declined%We are fully booked that day.')->exists());

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $this->booking))
            ->assertSee('Schedule change declined')
            ->assertSee('Reason: We are fully booked that day.');
    }

    public function test_a_request_is_decided_once_by_admin_only_before_the_event_starts(): void
    {
        $request = $this->requestSchedule(40);
        $route = route('admin.bookings.schedule-requests.decide', [$this->booking, $request]);

        $this->actingAs($this->clientUser)->post($route, ['decision' => 'accept'])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'staff']))->post($route, ['decision' => 'accept'])->assertForbidden();

        $this->booking->refresh()->update(['status' => 'event_in_progress']);
        $this->actingAs($this->admin)->post($route, ['decision' => 'accept'])
            ->assertSessionHas('error', 'Schedule requests can only be decided before the event starts.');
        $this->booking->update(['status' => 'change_requested']);

        $this->actingAs($this->admin)->post($route, ['decision' => 'decline']);
        $this->actingAs($this->admin)->post($route, ['decision' => 'accept'])
            ->assertSessionHas('error', 'This schedule request has already been declined.');
        $this->assertSame(now()->addDays(30)->toDateString(), $this->booking->fresh()->event_date->toDateString());

        $otherBooking = Booking::create(['status' => 'change_requested', 'event_type' => 'wedding', 'event_date' => now()->addDays(20)->toDateString(), 'venue' => 'Hall']);
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.schedule-requests.decide', [$otherBooking, $request]), ['decision' => 'accept'])
            ->assertSessionHas('error');
    }

    public function test_a_confirmed_booking_can_still_request_and_accept_a_new_schedule(): void
    {
        $this->booking->update(['status' => 'downpayment_received']);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $this->booking))
            ->assertSee('id="client-request-changes-row"', false);

        $request = $this->requestSchedule(40);

        // A paid booking keeps its status; Admin is alerted instead.
        $this->assertSame('downpayment_received', $this->booking->fresh()->status);
        $this->assertTrue(\App\Models\AdminAlert::where('type', 'change_requested')->where('booking_id', $this->booking->id)->exists());

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $this->booking))
            ->assertSee('id="schedule-change-request"', false)
            ->assertSee('data-next-step-target="schedule-change-request"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.schedule-requests.decide', [$this->booking, $request]), ['decision' => 'accept'])
            ->assertSessionHas('success', 'Schedule change accepted — the event date is updated.');

        $booking = $this->booking->fresh();
        $this->assertSame('downpayment_received', $booking->status);
        $this->assertSame(now()->addDays(40)->toDateString(), $booking->event_date->toDateString());
        $reply = BookingMessage::where('booking_id', $booking->id)->where('sender_type', 'admin')->latest('id')->value('message');
        $this->assertStringNotContainsString('updated quotation', $reply);
    }

    public function test_changes_cannot_be_requested_once_the_event_is_in_progress(): void
    {
        $this->booking->update(['status' => 'event_in_progress']);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $this->booking))
            ->assertDontSee('id="client-request-changes-row"', false);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), ['change_type' => 'other', 'change_reason' => 'Late change'])
            ->assertSessionHas('error', 'This booking cannot be modified at this stage.');

        $this->assertFalse(BookingMessage::where('booking_id', $this->booking->id)->exists());
    }

    private function requestSchedule(int $daysAhead, ?string $start = null, ?string $end = null): BookingScheduleRequest
    {
        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), array_filter([
                'change_type' => 'schedule',
                'requested_date' => now()->addDays($daysAhead)->toDateString(),
                'requested_start_time' => $start,
                'requested_end_time' => $end,
                'change_reason' => 'Venue moved us.',
            ]))
            ->assertSessionHasNoErrors();

        return BookingScheduleRequest::latest('id')->firstOrFail();
    }
}
