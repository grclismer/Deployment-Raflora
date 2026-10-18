<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Remaining balance: once the event is under way (in progress, completed, or settling extra charges)
 * the client can send a payment reference for the balance from a "Pay Remaining Balance" button.
 * Admin sees it under "Log Final Payment", checks the reference and logs it (or rejects it).
 * A payment logged mid-event keeps the event in progress and never touches stock.
 */
class RemainingBalancePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->clientUser = User::factory()->create(['role' => 'client', 'email' => 'balance@raflora.test', 'email_verified_at' => now()]);
        $this->client = Client::create(['user_id' => $this->clientUser->id, 'full_name' => 'Balance Client', 'email' => $this->clientUser->email, 'phone' => '09171234567', 'address' => 'Caloocan']);
    }

    private function booking(string $status, float $price = 11310, float $paid = 7917): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->client->id, 'event_type' => 'birthday', 'event_date' => now()->toDateString(),
            'venue' => 'Block 8, Caloocan', 'final_quoted_price' => $price, 'total_quoted' => $price, 'status' => $status,
        ]);
        Quotation::create(['booking_id' => $booking->id, 'status' => Quotation::STATUS_ACCEPTED, 'version' => 1, 'final_quoted_price' => $price]);
        if ($paid > 0) {
            Payment::create(['booking_id' => $booking->id, 'amount' => $paid, 'amount_paid' => $paid, 'remaining_balance' => $price - $paid,
                'status' => $paid >= $price ? 'fully_paid' : 'downpayment_received', 'payment_option' => $paid >= $price ? 'full_payment' : 'downpayment',
                'payment_type' => 'gcash', 'reference_number' => 'DOWN-' . uniqid(), 'verified_at' => now()]);
        }

        return $booking->fresh();
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'admin-balance@example.com'], ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin', 'is_bootstrap' => false]);
    }

    private function clientPage(Booking $booking): string
    {
        return $this->actingAs($this->clientUser)->get(route('bookings.show', $booking))->assertOk()->getContent();
    }

    private function submitBalance(Booking $booking, string $reference = 'GCASH-BAL-1')
    {
        return $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => $reference, 'payment_type' => 'gcash', 'payment_option' => 'full_payment',
        ]);
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $end = $start === false ? false : strpos($html, $to, $start);

        return ($start === false || $end === false) ? '' : substr($html, $start, $end - $start);
    }

    // Client: the button shows while a balance remains, in progress and after the event.
    public function test_client_sees_pay_remaining_balance_during_and_after_the_event(): void
    {
        foreach (['event_in_progress', 'event_completed', 'pending_resolution'] as $status) {
            $html = $this->clientPage($this->booking($status));
            $this->assertStringContainsString('id="pay-remaining-balance-btn"', $html, $status);
            $this->assertStringContainsString('id="remainingBalanceModal"', $html, $status);
            $this->assertStringContainsString('₱3,393.00', $this->between($html, 'id="remainingBalanceModal"', '</form>'), $status);
        }
    }

    public function test_button_is_hidden_when_paid_in_full_or_before_the_event(): void
    {
        $this->assertStringNotContainsString('id="pay-remaining-balance-btn"', $this->clientPage($this->booking('event_in_progress', 11310, 11310)));
        $this->assertStringNotContainsString('id="pay-remaining-balance-btn"', $this->clientPage($this->booking('confirmed')));
    }

    public function test_button_is_replaced_by_a_waiting_note_while_a_payment_is_being_checked(): void
    {
        $booking = $this->booking('event_in_progress');
        $this->submitBalance($booking)->assertSessionHas('success');

        $html = $this->clientPage($booking);
        $this->assertStringNotContainsString('id="pay-remaining-balance-btn"', $html);
        $this->assertStringContainsString('Remaining balance payment sent', $html);
    }

    // Client: submitting during the event creates a pending payment for exactly the balance.
    public function test_client_can_submit_the_balance_while_the_event_is_in_progress(): void
    {
        $booking = $this->booking('event_in_progress');

        $this->submitBalance($booking)->assertSessionHas('success');

        $payment = Payment::where('reference_number', 'GCASH-BAL-1')->sole();
        $this->assertSame('pending', $payment->status);
        $this->assertSame('full_payment', $payment->payment_option);
        $this->assertEquals(3393.00, (float) $payment->amount);
        $this->assertSame('event_in_progress', $booking->fresh()->status);

        // A second reference cannot be sent while the first is being checked.
        $this->submitBalance($booking, 'GCASH-BAL-2')->assertSessionHas('error');
        $this->assertSame(0, Payment::where('reference_number', 'GCASH-BAL-2')->count());
    }

    public function test_nothing_to_pay_once_paid_in_full(): void
    {
        $booking = $this->booking('event_in_progress', 11310, 11310);
        $this->submitBalance($booking)->assertSessionHas('error');
        $this->assertSame(0, Payment::where('reference_number', 'GCASH-BAL-1')->count());
    }

    // Admin: logging the client's payment mid-event keeps the event in progress and leaves stock alone.
    public function test_admin_logs_a_mid_event_balance_payment_without_moving_the_booking(): void
    {
        $booking = $this->booking('event_in_progress');
        $this->submitBalance($booking);
        $payment = Payment::where('reference_number', 'GCASH-BAL-1')->sole();
        $ledgerBefore = InventoryTransaction::count();

        $this->actingAs($this->admin())->post(route('admin.payments.verify', $payment))->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('event_in_progress', $booking->status);
        $this->assertSame('fully_paid', $payment->fresh()->status);
        $this->assertEquals(0.0, (float) $booking->remaining_balance);
        $this->assertSame($ledgerBefore, InventoryTransaction::count());
        $this->assertStringNotContainsString('id="pay-remaining-balance-btn"', $this->clientPage($booking));
    }

    public function test_admin_can_reject_a_mid_event_balance_payment_and_the_client_can_resend(): void
    {
        $booking = $this->booking('event_in_progress');
        $this->submitBalance($booking);
        $payment = Payment::where('reference_number', 'GCASH-BAL-1')->sole();

        $this->actingAs($this->admin())->post(route('admin.payments.reject', $payment))->assertSessionHas('success');

        $this->assertSame('event_in_progress', $booking->fresh()->status);
        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertStringContainsString('submit a corrected reference', ClientNotification::where('user_id', $this->clientUser->id)->latest('id')->firstOrFail()->message);
        $this->assertStringContainsString('id="pay-remaining-balance-btn"', $this->clientPage($booking));
    }

    // Admin: the client's payment appears under "Log Final Payment" for checking before it is logged.
    public function test_admin_sees_the_clients_payment_under_log_final_payment(): void
    {
        $booking = $this->booking('event_in_progress');
        $this->submitBalance($booking, 'GCASH-CHECK-777');
        $payment = Payment::where('reference_number', 'GCASH-CHECK-777')->sole();

        $html = $this->actingAs($this->admin())->get(route('admin.bookings.show', $booking))->assertOk()->getContent();
        $actions = $this->between($html, 'id="operational-actions-heading"', 'id="payments-heading"');

        $this->assertStringContainsString('GCASH-CHECK-777', $actions);
        $this->assertStringContainsString('₱3,393.00', $actions);
        $this->assertStringContainsString('action="' . route('admin.payments.verify', $payment) . '"', $actions);
        $this->assertStringContainsString('action="' . route('admin.payments.reject', $payment) . '"', $actions);
        $this->assertStringContainsString('Log Final Payment', $actions);
        // The manual entry form is not offered while the client's payment waits to be checked.
        $this->assertStringNotContainsString('action="' . route('admin.bookings.final_payment', $booking) . '"', $actions);
    }

    public function test_without_a_client_payment_admin_keeps_the_manual_final_payment_form(): void
    {
        $booking = $this->booking('event_in_progress');

        $html = $this->actingAs($this->admin())->get(route('admin.bookings.show', $booking))->assertOk()->getContent();
        $actions = $this->between($html, 'id="operational-actions-heading"', 'id="payments-heading"');

        $this->assertStringContainsString('action="' . route('admin.bookings.final_payment', $booking) . '"', $actions);
        $this->assertStringNotContainsString(route('admin.payments.verify', 1), $actions);
    }

    // Unchanged: after the event, a verified balance payment still follows the post-event rules.
    public function test_after_the_event_a_verified_balance_still_settles_as_before(): void
    {
        $booking = $this->booking('event_completed');
        $this->submitBalance($booking);
        $payment = Payment::where('reference_number', 'GCASH-BAL-1')->sole();

        $this->actingAs($this->admin())->post(route('admin.payments.verify', $payment))->assertSessionHas('success');

        $this->assertSame('event_completed', $booking->fresh()->status);
        $this->assertEquals(0.0, (float) $booking->fresh()->remaining_balance);
    }
}
