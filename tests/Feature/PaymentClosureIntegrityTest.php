<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin payment defects F4, F5 and F16: a booking is never closed while a client payment is
 * still waiting to be checked, rejecting a payment never reopens or rewrites a booking that was
 * not waiting for it, the client is told about a rejection, and a final payment is only logged
 * for a started event, for a real amount within the balance.
 */
class PaymentClosureIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // F4 — Declining waits until the submitted payment is verified or rejected.
    public function test_decline_is_refused_while_a_payment_waits_and_allowed_after_rejection(): void
    {
        [$user, $booking] = $this->clientBooking('payment_submitted');
        $payment = $this->pendingPayment($booking);

        $this->actingAs($this->admin())->post(route('admin.bookings.decline', $booking), ['admin_note' => 'Fully booked'])
            ->assertSessionHas('error');
        $this->assertSame('payment_submitted', $booking->fresh()->status);
        $this->assertSame('pending', $payment->fresh()->status);

        $this->actingAs($this->admin())->post(route('admin.payments.reject', $payment))->assertSessionHas('success');
        $this->assertSame('admin_approved', $booking->fresh()->status);

        $this->actingAs($this->admin())->post(route('admin.bookings.decline', $booking), ['admin_note' => 'Fully booked'])
            ->assertSessionHas('success');
        $this->assertSame('declined', $booking->fresh()->status);
        $this->assertSame('rejected', $payment->fresh()->status);
    }

    // F4 — A payment left waiting on an already closed booking (older data) can be rejected without reopening it.
    public function test_rejecting_a_leftover_payment_keeps_a_closed_booking_closed(): void
    {
        [$user, $booking] = $this->clientBooking('payment_submitted');
        $payment = $this->pendingPayment($booking);
        $booking->forceFill(['status' => 'declined'])->save();

        $this->actingAs($this->admin())->post(route('admin.payments.verify', $payment), ['amount_received' => 9000])
            ->assertSessionHas('error');
        $this->actingAs($this->admin())->post(route('admin.payments.reject', $payment))->assertSessionHas('success');

        $this->assertSame('declined', $booking->fresh()->status);
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertStringContainsString('please contact Raflora', $this->latestNotification($user)->message);
    }

    // F4 — Approving a cancellation waits for the payment; rejecting it keeps the request open.
    public function test_cancellation_approval_waits_for_the_payment_and_rejection_keeps_the_request(): void
    {
        [$user, $booking] = $this->clientBooking('payment_submitted');
        $payment = $this->pendingPayment($booking);
        $booking->forceFill(['status' => 'cancellation_requested', 'pre_cancellation_status' => 'payment_submitted'])->save();

        $this->actingAs($this->admin())->post(route('admin.bookings.handle-cancellation', $booking), ['action' => 'approve'])
            ->assertSessionHas('error');
        $this->assertSame('cancellation_requested', $booking->fresh()->status);

        $this->actingAs($this->admin())->post(route('admin.payments.reject', $payment))->assertSessionHas('success');
        $booking->refresh();
        $this->assertSame('cancellation_requested', $booking->status);
        $this->assertSame('admin_approved', $booking->pre_cancellation_status);

        $this->actingAs($this->admin())->post(route('admin.bookings.handle-cancellation', $booking), ['action' => 'approve'])
            ->assertSessionHas('success');
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    // F4 — Denying the cancellation after the rejection returns the client to the payment step.
    public function test_denied_cancellation_after_rejection_lets_the_client_pay_again(): void
    {
        [$user, $booking] = $this->clientBooking('payment_submitted');
        $payment = $this->pendingPayment($booking);
        $booking->forceFill(['status' => 'cancellation_requested', 'pre_cancellation_status' => 'payment_submitted'])->save();

        $this->actingAs($this->admin())->post(route('admin.payments.reject', $payment))->assertSessionHas('success');
        $this->actingAs($this->admin())->post(route('admin.bookings.handle-cancellation', $booking), ['action' => 'deny'])
            ->assertSessionHas('success');

        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    // F4 — Closing through the booking form's status is held to the same rule.
    public function test_closing_status_through_the_booking_form_waits_for_the_payment(): void
    {
        [$user, $booking] = $this->clientBooking('payment_submitted');
        $this->pendingPayment($booking);

        $this->actingAs($this->admin())->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'cancelled',
        ])->assertSessionHas('error');

        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    // F16 — The client is told when a payment could not be verified.
    public function test_rejected_payment_notifies_the_client_with_next_step(): void
    {
        [$user, $booking] = $this->clientBooking('payment_submitted');
        $payment = $this->pendingPayment($booking, 'GCASH-555');

        $this->actingAs($this->admin())->post(route('admin.payments.reject', $payment))
            ->assertSessionHas('success', 'Payment has been rejected and the client has been notified.');

        $notification = $this->latestNotification($user);
        $this->assertSame('Payment Not Verified', $notification->title);
        $this->assertSame('payment_update', $notification->type);
        $this->assertSame($booking->id, $notification->booking_id);
        $this->assertStringContainsString('GCASH-555', $notification->message);
        $this->assertStringContainsString('submit a corrected reference', $notification->message);
    }

    // F5 — No final payment before the event starts, for ₱0, above the balance, or beside a waiting client payment.
    public function test_final_payment_requires_started_event_real_amount_within_balance_and_no_waiting_payment(): void
    {
        [$user, $pending] = $this->clientBooking('pending');
        $this->finalPayment($pending, 5000)->assertSessionHas('error');
        $this->assertSame(0, $pending->payments()->count());

        [$user, $booking] = $this->clientBooking('event_completed');
        $this->verifiedPayment($booking, 9000); // ₱9,000 of ₱18,000 paid

        $this->finalPayment($booking, 0)->assertSessionHasErrors('amount_received');
        $this->finalPayment($booking, 9000.01)->assertSessionHas('error', 'The amount is more than the remaining balance of ₱9,000.00.');
        $this->assertSame(1, $booking->payments()->count());

        $waiting = $this->pendingPayment($booking, 'GCASH-FINAL');
        $this->finalPayment($booking, 9000)->assertSessionHas('error');
        $this->assertSame(2, $booking->payments()->count());
        $this->actingAs($this->admin())->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_completed',
            'action' => 'log_final_payment',
        ])->assertSessionHas('error');
        $this->assertSame(2, $booking->payments()->count());

        $waiting->update(['status' => 'rejected']);
        $this->finalPayment($booking, 4000)->assertSessionHas('success');
        $booking->refresh();
        $this->assertEquals(13000.0, $booking->total_paid);
        $this->assertEquals(5000.0, $booking->remaining_balance);
    }

    private function finalPayment(Booking $booking, float $amount)
    {
        return $this->actingAs($this->admin())->post(route('admin.bookings.final_payment', $booking), [
            'amount_received' => $amount,
            'payment_type' => 'cash',
        ]);
    }

    private function clientBooking(string $status): array
    {
        $user = User::factory()->create(['role' => 'client', 'email' => 'pay-' . uniqid() . '@example.com']);
        $client = Client::create(['full_name' => 'Payment Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Manila']);
        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => $status,
            'final_quoted_price' => 18000,
            'total_quoted' => 18000,
        ]);

        return [$user, $booking];
    }

    private function pendingPayment(Booking $booking, string $reference = 'GCASH-1001'): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => 9000,
            'amount_paid' => 0,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => $reference,
            'status' => 'pending',
        ]);
    }

    private function verifiedPayment(Booking $booking, float $amount): Payment
    {
        return Payment::create([
            'booking_id' => $booking->id,
            'amount' => $amount,
            'amount_paid' => $amount,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => 'VERIFIED-' . uniqid(),
            'status' => 'downpayment_received',
            'verified_at' => now(),
        ]);
    }

    private function latestNotification(User $user): ClientNotification
    {
        return ClientNotification::where('user_id', $user->id)->latest('id')->firstOrFail();
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin-payments@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin']
        );
    }
}
