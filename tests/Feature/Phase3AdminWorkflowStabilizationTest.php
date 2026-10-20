<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stabilization Plan — Phase 3: Administrator core workflow.
 * Status changes must reflect valid business events; a submitted payment is not a verified payment.
 */
class Phase3AdminWorkflowStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Phase Three Admin',
            'email' => 'p3-admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->client = Client::create(['full_name' => 'Phase Three Client', 'email' => 'p3-client@example.com']);
    }

    public function test_form_status_cannot_skip_guarded_workflow_steps(): void
    {
        $cases = [
            'confirmed' => 'A booking can only be confirmed after a payment has been verified. Verify the client\'s submitted payment instead.',
            'downpayment_received' => 'A booking can only be confirmed after a payment has been verified. Verify the client\'s submitted payment instead.',
            'quotation_sent' => 'Use "Approve & Send Official Quote" so the official quotation is issued and recorded.',
            'approved' =>'This status is set by the client\'s own action and cannot be assigned by Admin.',
            'payment_submitted' => 'This status is set by the client\'s own action and cannot be assigned by Admin.',
            'event_in_progress' => 'Use "Mark Event In Progress" so fresh-flower readiness, dispatch, and inventory locks are checked.',
            'event_completed' => 'The event must be in progress before it can move to post-event settlement.',
        ];

        foreach ($cases as $target => $message) {
            $booking = $this->booking('pending');
            $this->update($booking, ['status' => $target])->assertSessionHas('error', $message);
            $this->assertSame('pending', $booking->fresh()->status, "pending must not jump to {$target}");
        }

        // admin_approved is not an accepted form status at all; only final approval sets it.
        $booking = $this->booking('pending');
        $this->update($booking, ['status' => 'admin_approved'])->assertSessionHasErrors('status');
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_status_governing_actions_ignore_a_forged_status(): void
    {
        // "accept" (final approval) must check the booking's real status, not the submitted one.
        $booking = $this->booking('pending');
        $this->update($booking, ['status' => 'approved', 'action' => 'accept'])
            ->assertSessionHas('error', 'Only an accepted quotation can be final-approved.');
        $this->assertSame('pending', $booking->fresh()->status);

        $this->update($booking, ['status' => 'event_in_progress', 'action' => 'mark_event_in_progress'])
            ->assertSessionHas('error', 'Only a confirmed booking with a verified payment can be marked as in progress.');
        $this->assertSame('pending', $booking->fresh()->status);

        $confirmed = $this->booking('confirmed', ['confirmed_at' => now()]);
        $this->update($confirmed, ['status' => 'event_completed', 'action' => 'mark_event_completed'])
            ->assertSessionHas('error', 'Only an event in progress can be marked as completed.');
        $this->assertSame('confirmed', $confirmed->fresh()->status);

        $this->update($booking, ['action' => 'log_final_payment'])
            ->assertSessionHas('error', 'A final payment can only be logged once the event is in progress or completed.');
        $this->assertSame(0, Payment::where('booking_id', $booking->id)->count());
    }

    public function test_closed_bookings_cannot_be_reopened_from_the_form(): void
    {
        foreach (['cancelled', 'declined', 'completed'] as $closed) {
            $booking = $this->booking($closed);
            $this->update($booking, ['status' => 'pending'])->assertSessionHas('error');
            $this->assertSame($closed, $booking->fresh()->status);
        }
    }

    public function test_legitimate_form_transitions_still_work(): void
    {
        $paid = $this->booking('admin_approved');
        $this->recordVerifiedPayment($paid);
        $this->update($paid, ['status' => 'downpayment_received'])->assertSessionMissing('error');
        $this->assertSame('downpayment_received', $paid->fresh()->status);

        $cancelled = $this->booking('pending');
        $this->update($cancelled, ['status' => 'cancelled'])->assertSessionMissing('error');
        $this->assertSame('cancelled', $cancelled->fresh()->status);

        $revision = $this->booking('change_requested');
        $this->update($revision, ['status' => 'pending'])->assertSessionMissing('error');
        $this->assertSame('pending', $revision->fresh()->status);

        $unchanged = $this->booking('quotation_sent');
        $this->update($unchanged, ['status' => 'quotation_sent', 'special_requests' => 'Updated note'])->assertSessionMissing('error');
        $this->assertSame('Updated note', $unchanged->fresh()->special_requests);
    }

    public function test_rejected_payment_cannot_be_verified_later(): void
    {
        $booking = $this->booking('payment_submitted', ['final_quoted_price' => 2000, 'total_quoted' => 2000]);
        $rejected = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 1000,
            'amount_paid' => 0,
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
            'reference_number' => 'REJECTED-REF',
            'status' => 'rejected',
        ]);

        $this->actingAs($this->admin)->post(route('admin.payments.verify', $rejected))
            ->assertSessionHas('error', 'Only a payment submission awaiting review can be verified.');

        $this->assertSame('rejected', $rejected->fresh()->status);
        $this->assertNull($rejected->fresh()->verified_at);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    public function test_decline_is_limited_to_bookings_that_have_not_started(): void
    {
        foreach (['event_in_progress', 'completed', 'cancelled'] as $status) {
            $booking = $this->booking($status);
            $this->actingAs($this->admin)->post(route('admin.bookings.decline', $booking))->assertSessionHas('error');
            $this->assertSame($status, $booking->fresh()->status);
        }

        $pending = $this->booking('pending');
        $this->actingAs($this->admin)->post(route('admin.bookings.decline', $pending), ['admin_note' => 'Date unavailable'])
            ->assertRedirect(route('admin.bookings'));
        $this->assertSame('declined', $pending->fresh()->status);
    }

    private function booking(string $status, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(25)->toDateString(),
            'venue' => 'Phase Three Venue',
            'status' => $status,
        ], $attributes));
    }

    private function update(Booking $booking, array $payload)
    {
        return $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), array_merge([
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => $booking->status,
            'action' => 'save',
        ], $payload));
    }
}
