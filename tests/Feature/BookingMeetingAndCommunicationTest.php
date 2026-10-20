<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * README Client Workflow: "the client and admin can now communicate/negotiate/meeting about their booking".
 */
class BookingMeetingAndCommunicationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $clientUser;
    private Client $client;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'meet-admin@example.com');
        $this->staff = $this->user('staff', 'meet-staff@example.com');
        $this->clientUser = $this->user('client', 'meet-client@example.com');
        $this->client = Client::create(['full_name' => 'Meeting Client', 'email' => $this->clientUser->email, 'phone' => '09170000002']);
        $this->booking = $this->booking('pending');
    }

    // --------------------------------------------------------------- Client requests

    public function test_client_can_request_a_meeting_and_admin_is_alerted(): void
    {
        $preferred = now()->addDays(3)->setTime(10, 0);

        $response = $this->actingAs($this->clientUser)->post(route('bookings.meetings.store', $this->booking), [
            'meeting_type' => 'online',
            'preferred_datetime' => $preferred->format('Y-m-d\TH:i'),
            'agenda' => 'Review the arch design',
        ]);

        $response->assertSessionHas('success', 'Meeting request sent. Raflora will confirm the schedule with you.');
        $meeting = Meeting::firstOrFail();
        $this->assertSame(Meeting::STATUS_REQUESTED, $meeting->status);
        $this->assertSame($this->booking->id, $meeting->booking_id);
        $this->assertSame($this->clientUser->id, $meeting->requested_by);
        $this->assertTrue($meeting->scheduled_datetime->equalTo($preferred));
        $this->assertDatabaseHas('admin_alerts', ['type' => 'meeting_requested', 'booking_id' => $this->booking->id, 'is_read' => false]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'meeting_requested', 'entity_id' => $this->booking->id]);

        // Only one open request at a time.
        $this->actingAs($this->clientUser)->post(route('bookings.meetings.store', $this->booking), [
            'meeting_type' => 'in_person',
            'preferred_datetime' => now()->addDays(4)->format('Y-m-d\TH:i'),
        ])->assertSessionHas('error', 'You already have a meeting request awaiting Raflora confirmation.');
        $this->assertSame(1, Meeting::count());
    }

    public function test_client_meeting_request_is_validated_and_ownership_enforced(): void
    {
        $this->actingAs($this->clientUser)->post(route('bookings.meetings.store', $this->booking), [
            'meeting_type' => 'online',
            'preferred_datetime' => now()->subHour()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('preferred_datetime');

        $this->actingAs($this->clientUser)->post(route('bookings.meetings.store', $this->booking), [
            'meeting_type' => 'phone',
            'preferred_datetime' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('meeting_type');

        $otherUser = $this->user('client', 'meet-other@example.com');
        Client::create(['full_name' => 'Other', 'email' => $otherUser->email, 'phone' => '09170000003']);
        $this->actingAs($otherUser)->post(route('bookings.meetings.store', $this->booking), [
            'meeting_type' => 'online',
            'preferred_datetime' => now()->addDay()->format('Y-m-d\TH:i'),
        ])->assertForbidden();

        foreach (['cancelled', 'declined', 'completed'] as $status) {
            $closed = $this->booking($status);
            $this->actingAs($this->clientUser)->post(route('bookings.meetings.store', $closed), [
                'meeting_type' => 'online',
                'preferred_datetime' => now()->addDay()->format('Y-m-d\TH:i'),
            ])->assertSessionHas('error', 'Meetings can no longer be requested for this booking.');
        }

        $this->assertSame(0, Meeting::count());
    }

    public function test_client_can_cancel_their_open_meeting(): void
    {
        $meeting = $this->meeting(Meeting::STATUS_SCHEDULED, now()->addDays(2));

        $this->actingAs($this->clientUser)
            ->post(route('bookings.meetings.cancel', ['booking' => $this->booking->id, 'meeting' => $meeting->id]))
            ->assertSessionHas('success', 'Meeting cancelled. Raflora has been notified.');

        $this->assertSame(Meeting::STATUS_CANCELLED, $meeting->fresh()->status);
        $this->assertNotNull($meeting->fresh()->cancelled_at);
        $this->assertDatabaseHas('admin_alerts', ['type' => 'meeting_cancelled', 'booking_id' => $this->booking->id]);

        // A meeting from another booking cannot be cancelled through this booking.
        $otherBooking = $this->booking('pending');
        $otherMeeting = $this->meeting(Meeting::STATUS_REQUESTED, now()->addDays(2), $otherBooking);
        $this->actingAs($this->clientUser)
            ->post(route('bookings.meetings.cancel', ['booking' => $this->booking->id, 'meeting' => $otherMeeting->id]))
            ->assertNotFound();
        $this->assertSame(Meeting::STATUS_REQUESTED, $otherMeeting->fresh()->status);
    }

    // --------------------------------------------------------------- Admin scheduling

    public function test_admin_confirms_requested_meeting_and_client_is_notified(): void
    {
        $meeting = $this->meeting(Meeting::STATUS_REQUESTED, now()->addDays(3));
        AdminAlert::create(['type' => 'meeting_requested', 'booking_id' => $this->booking->id, 'title' => 'Meeting Requested', 'message' => 'x', 'is_read' => false]);
        $confirmedAt = now()->addDays(3)->setTime(14, 30);

        // Online meetings need a valid http(s) link.
        $this->actingAs($this->admin)->post(route('admin.bookings.meetings.confirm', ['booking' => $this->booking->id, 'meeting' => $meeting->id]), [
            'meeting_type' => 'online',
            'scheduled_datetime' => $confirmedAt->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('meeting_link');
        $this->actingAs($this->admin)->post(route('admin.bookings.meetings.confirm', ['booking' => $this->booking->id, 'meeting' => $meeting->id]), [
            'meeting_type' => 'online',
            'scheduled_datetime' => $confirmedAt->format('Y-m-d\TH:i'),
            'meeting_link' => 'javascript:alert(1)',
        ])->assertSessionHasErrors('meeting_link');

        $this->actingAs($this->admin)->post(route('admin.bookings.meetings.confirm', ['booking' => $this->booking->id, 'meeting' => $meeting->id]), [
            'meeting_type' => 'online',
            'scheduled_datetime' => $confirmedAt->format('Y-m-d\TH:i'),
            'meeting_link' => 'https://meet.example.com/raflora',
        ])->assertSessionHas('success', 'Meeting confirmed and the client has been notified.');

        $meeting->refresh();
        $this->assertSame(Meeting::STATUS_SCHEDULED, $meeting->status);
        $this->assertSame($this->admin->id, $meeting->scheduled_by);
        $this->assertTrue($meeting->scheduled_datetime->equalTo($confirmedAt));
        $this->assertSame('https://meet.example.com/raflora', $meeting->meeting_link);
        $this->assertDatabaseHas('client_notifications', ['user_id' => $this->clientUser->id, 'booking_id' => $this->booking->id, 'title' => 'Meeting Confirmed']);
        $this->assertSame(0, AdminAlert::where('type', 'meeting_requested')->where('is_read', false)->count());

        $this->actingAs($this->clientUser)->get(route('bookings.show', $this->booking))
            ->assertOk()
            ->assertSee('Meetings with Raflora')
            ->assertSee('https://meet.example.com/raflora')
            ->assertSee('Scheduled');
    }

    public function test_admin_schedules_meeting_with_one_hour_conflict_guard(): void
    {
        $slot = now()->addDays(5)->setTime(9, 0);
        $this->meeting(Meeting::STATUS_SCHEDULED, $slot, $this->booking('confirmed'));

        $this->actingAs($this->admin)->post(route('admin.bookings.meetings.store', $this->booking), [
            'meeting_type' => 'in_person',
            'scheduled_datetime' => $slot->copy()->addMinutes(30)->format('Y-m-d\TH:i'),
            'address' => 'Raflora Studio, Caloocan',
        ])->assertSessionHas('error');
        $this->assertSame(1, Meeting::count());

        $this->actingAs($this->admin)->post(route('admin.bookings.meetings.store', $this->booking), [
            'meeting_type' => 'in_person',
            'scheduled_datetime' => $slot->copy()->addMinutes(60)->format('Y-m-d\TH:i'),
        ])->assertSessionHasErrors('address');

        $this->actingAs($this->admin)->post(route('admin.bookings.meetings.store', $this->booking), [
            'meeting_type' => 'in_person',
            'scheduled_datetime' => $slot->copy()->addMinutes(60)->format('Y-m-d\TH:i'),
            'address' => 'Raflora Studio, Caloocan',
            'agenda' => 'Final venue walkthrough',
        ])->assertSessionHas('success', 'Meeting scheduled and the client has been notified.');

        $created = Meeting::where('booking_id', $this->booking->id)->firstOrFail();
        $this->assertSame(Meeting::STATUS_SCHEDULED, $created->status);
        $this->assertSame('Raflora Studio, Caloocan', $created->address);
        $this->assertNull($created->meeting_link);
        $this->assertDatabaseHas('client_notifications', ['user_id' => $this->clientUser->id, 'title' => 'Meeting Scheduled']);
    }

    public function test_admin_completes_only_past_scheduled_meetings_and_can_cancel(): void
    {
        $future = $this->meeting(Meeting::STATUS_SCHEDULED, now()->addDay());
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.meetings.complete', ['booking' => $this->booking->id, 'meeting' => $future->id]))
            ->assertSessionHas('error', 'A meeting cannot be marked as completed before its scheduled time.');

        $past = $this->meeting(Meeting::STATUS_SCHEDULED, now()->subHours(2));
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.meetings.complete', ['booking' => $this->booking->id, 'meeting' => $past->id]), ['outcome_notes' => 'Agreed on pastel palette.'])
            ->assertSessionHas('success', 'Meeting marked as completed.');
        $this->assertSame(Meeting::STATUS_COMPLETED, $past->fresh()->status);
        $this->assertSame('Agreed on pastel palette.', $past->fresh()->outcome_notes);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.meetings.cancel', ['booking' => $this->booking->id, 'meeting' => $future->id]), ['reason' => 'Florist unavailable'])
            ->assertSessionHas('success', 'Meeting cancelled and the client has been notified.');
        $this->assertSame(Meeting::STATUS_CANCELLED, $future->fresh()->status);
        $this->assertDatabaseHas('client_notifications', ['user_id' => $this->clientUser->id, 'title' => 'Meeting Cancelled']);

        // Completed and cancelled meetings are closed.
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.meetings.cancel', ['booking' => $this->booking->id, 'meeting' => $past->id]))
            ->assertSessionHas('error', 'This meeting can no longer be cancelled.');
    }

    public function test_admin_meeting_routes_are_admin_only(): void
    {
        $meeting = $this->meeting(Meeting::STATUS_REQUESTED, now()->addDays(2));
        $payload = ['meeting_type' => 'in_person', 'scheduled_datetime' => now()->addDays(2)->format('Y-m-d\TH:i'), 'address' => 'Studio'];

        foreach ([$this->staff, $this->clientUser] as $user) {
            $this->actingAs($user)->post(route('admin.bookings.meetings.store', $this->booking), $payload)->assertForbidden();
            $this->actingAs($user)->post(route('admin.bookings.meetings.confirm', ['booking' => $this->booking->id, 'meeting' => $meeting->id]), $payload)->assertForbidden();
            $this->actingAs($user)->post(route('admin.bookings.meetings.cancel', ['booking' => $this->booking->id, 'meeting' => $meeting->id]))->assertForbidden();
        }

        $this->assertSame(Meeting::STATUS_REQUESTED, $meeting->fresh()->status);
        $this->assertSame(1, Meeting::count());
    }

    public function test_admin_booking_page_renders_meeting_panel_with_confirm_form(): void
    {
        $meeting = $this->meeting(Meeting::STATUS_REQUESTED, now()->addDays(2));

        $this->actingAs($this->admin)->get(route('admin.bookings.show', $this->booking))
            ->assertOk()
            ->assertSee('Client Meetings')
            ->assertSee(route('admin.bookings.meetings.confirm', ['booking' => $this->booking->id, 'meeting' => $meeting->id]))
            ->assertSee('Schedule Meeting');
    }

    // --------------------------------------------------------------- Messaging at every stage

    public function test_client_can_start_a_conversation_before_any_message_exists(): void
    {
        $this->actingAs($this->clientUser)->get(route('bookings.show', $this->booking))
            ->assertOk()
            ->assertSee('id="booking-conversation-root"', false)
            ->assertSee('No Messages Yet')
            ->assertSee('id="comm-composer-form"', false)
            ->assertSee(route('bookings.messages.store', $this->booking), false)
            ->assertSee('Request Meeting');
    }

    public function test_admin_message_form_sends_visibility_and_internal_notes_do_not_notify_client(): void
    {
        $html = $this->actingAs($this->admin)->get(route('admin.bookings.show', $this->booking))->assertOk()->getContent();
        $this->assertStringContainsString('name="visibility"', $html);
        $this->assertStringContainsString('<option value="admin_staff">Admin &amp; Staff (Internal)</option>', $html);

        // Conversation component endpoint.
        $this->actingAs($this->admin)->postJson(route('admin.bookings.messages.store', $this->booking), [
            'message' => 'Internal: confirm arch availability.',
            'visibility' => 'admin_staff',
        ])->assertCreated()->assertJsonPath('success', true);
        $this->assertSame(0, ClientNotification::where('booking_id', $this->booking->id)->count());

        $this->actingAs($this->admin)->postJson(route('admin.bookings.messages.store', $this->booking), [
            'message' => 'Hi! We have reviewed your request.',
            'visibility' => 'client_admin',
        ])->assertCreated()->assertJsonPath('success', true);
        $this->assertSame(1, ClientNotification::where('booking_id', $this->booking->id)->where('title', 'New message from Admin')->count());

        // Legacy reply form endpoint honours the same rule.
        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'message' => 'Internal: second check.',
            'visibility' => 'admin_staff',
            'action' => 'reply_only',
        ])->assertSessionHas('success');
        $this->assertSame(1, ClientNotification::where('booking_id', $this->booking->id)->count());
        $this->assertSame(3, BookingMessage::where('booking_id', $this->booking->id)->count());
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Meeting User',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }

    private function booking(string $status): Booking
    {
        return Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Meeting Venue',
            'status' => $status,
        ]);
    }

    private function meeting(string $status, Carbon $at, ?Booking $booking = null): Meeting
    {
        return Meeting::create([
            'booking_id' => ($booking ?? $this->booking)->id,
            'meeting_type' => 'online',
            'scheduled_datetime' => $at,
            'meeting_link' => 'https://meet.example.com/existing',
            'status' => $status,
        ]);
    }
}
