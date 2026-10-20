<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PhaseFivePaymentIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_payment_requires_admin_approval_and_rejects_duplicates(): void
    {
        [$user, $booking] = $this->clientBooking('approved', 546);
        $payload = ['reference_number' => 'CLIENT-REF', 'payment_type' => 'gcash', 'payment_option' => 'downpayment'];

        $this->actingAs($user)->post(route('bookings.payment.reference', $booking), $payload)
            ->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);

        $booking->update(['status' => 'admin_approved']);
        $this->actingAs($user)->post(route('bookings.payment.reference', $booking), $payload)->assertRedirect();
        $this->assertDatabaseCount('payments', 1);
        $payment = Payment::first();
        $this->assertSame(273.0, (float) $payment->amount);
        $this->assertSame(273.0, (float) $payment->remaining_balance);
        $this->assertSame(0.0, (float) $payment->amount_paid);
        $this->assertSame('downpayment', $payment->payment_option);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
        $this->assertNotSame('confirmed', $booking->fresh()->status);
        $this->assertDatabaseCount('inventory_transactions', 0);
        $this->actingAs($user)->post(route('bookings.payment.reference', $booking), $payload)
            ->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_client_full_payment_uses_the_full_quotation_amount(): void
    {
        [$user, $booking] = $this->clientBooking('admin_approved');
        $payload = ['reference_number' => 'FULL-REF', 'payment_type' => 'gcash', 'payment_option' => 'full_payment'];

        $this->actingAs($user)->post(route('bookings.payment.reference', $booking), $payload)->assertRedirect();

        $payment = Payment::first();
        $this->assertSame(1000.0, (float) $payment->amount);
        $this->assertSame(0.0, (float) $payment->remaining_balance);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    public function test_client_payment_rejects_a_zero_quotation_without_creating_payment(): void
    {
        [$user, $booking] = $this->clientBooking('admin_approved');
        foreach ([0, -1] as $invalidQuote) {
            $booking->acceptedQuotation()->update(['final_quoted_price' => $invalidQuote]);
            $booking->update(['final_quoted_price' => $invalidQuote, 'total_quoted' => $invalidQuote]);

            $this->actingAs($user)->post(route('bookings.payment.reference', $booking), [
                'reference_number' => 'INVALID-' . abs($invalidQuote),
                'payment_type' => 'gcash',
                'payment_option' => 'downpayment',
            ])->assertSessionHas('error');
        }

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    public function test_another_client_cannot_submit_payment_for_the_booking(): void
    {
        [, $booking] = $this->clientBooking('admin_approved');
        $otherUser = $this->user('client');

        $this->actingAs($otherUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'UNAUTHORIZED-REF',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
        $this->assertSame('admin_approved', $booking->fresh()->status);
    }

    public function test_guest_payment_uses_payment_submitted_and_requires_token_and_approval(): void
    {
        $booking = $this->guestBooking('approved');
        $payload = [
            'guest_access_token' => $booking->guest_access_token,
            'guest_token' => $booking->guest_access_token,
            'reference_number' => 'GUEST-REF',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ];

        $this->post(route('guest.bookings.payment.reference', $booking), $payload)->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);

        // Phase 2B-6: Unclaimed guest payment submission is blocked even when status is admin_approved
        $booking->update(['status' => 'admin_approved']);
        $this->post(route('guest.bookings.payment.reference', $booking), $payload)
            ->assertRedirect(route('guest.booking.analysis', ['token' => $booking->guest_access_token]))
            ->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');
        $this->assertSame('admin_approved', $booking->fresh()->status);
        $this->assertDatabaseCount('payments', 0);

        $otherBooking = $this->guestBooking('admin_approved');
        $this->post(route('guest.bookings.payment.reference', $otherBooking), array_merge($payload, [
            'guest_access_token' => 'wrong',
            'guest_token' => 'wrong',
            'reference_number' => 'GUEST-WRONG-TOKEN',
        ]))
            ->assertForbidden();
    }

    public function test_admin_verification_requires_submitted_payment_and_rejects_invalid_amounts(): void
    {
        $admin = $this->user('admin');
        $booking = $this->guestBooking('payment_submitted');
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 1000,
            'payment_option' => 'downpayment',
            'amount_paid' => 0,
            'remaining_balance' => 1000,
            'payment_type' => 'gcash',
            'reference_number' => 'VERIFY-REF',
            'status' => 'pending',
        ]);

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), ['amount_received' => 0])
            ->assertSessionHasErrors('amount_received');
        $this->assertNull($payment->fresh()->verified_at);

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), ['amount_received' => 1001])
            ->assertSessionHasErrors('amount_received');
        $this->assertNull($payment->fresh()->verified_at);

        $booking->update(['status' => 'approved']);
        $this->actingAs($admin)->post(route('admin.payments.verify', $payment))
            ->assertSessionHas('error');
        $this->assertNull($payment->fresh()->verified_at);

        $booking->update(['status' => 'payment_submitted']);
        
        // Assert underpayment is blocked
        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), ['amount_received' => 500])
            ->assertSessionHasErrors('amount_received');
        $this->assertNull($payment->fresh()->verified_at);
        
        // Assert exact payment succeeds
        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), ['amount_received' => 1000])
            ->assertRedirect();
        $this->assertNotNull($payment->fresh()->verified_at);
        $this->assertSame('downpayment_received', $booking->fresh()->status);
    }

    public function test_admin_can_reject_submitted_payment_and_client_can_retry(): void
    {
        $admin = $this->user('admin');
        [$user, $booking] = $this->clientBooking('payment_submitted');

        $payment = Payment::create([
            'booking_id' => $booking->id,
            'payment_option' => 'downpayment',
            'payment_type' => 'gcash',
            'reference_number' => 'REF-12345',
            'status' => 'pending',
            'amount' => 500,
            'amount_paid' => 0,
            'remaining_balance' => 1000,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.payments.reject', $payment))
            ->assertRedirect();

        $this->assertSame('rejected', $payment->fresh()->status);
        $this->assertSame('admin_approved', $booking->fresh()->status);

        $this->actingAs($user)
            ->post(route('bookings.payment.reference', $booking), [
                'payment_option' => 'downpayment',
                'payment_type' => 'gcash',
                'reference_number' => 'REF-NEW-123'
            ])
            ->assertRedirect();

        $this->assertSame('payment_submitted', $booking->fresh()->status);
        $newPayment = Payment::where('booking_id', $booking->id)->where('status', 'pending')->latest('id')->first();
        $this->assertNotNull($newPayment);
        $this->assertSame('REF-NEW-123', $newPayment->reference_number);
    }

    private function clientBooking(string $status, float $quoteTotal = 1000): array
    {
        $admin = User::first() ?? $this->user('admin');
        $user = $this->user('client');
        $client = Client::create(['full_name' => 'Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Address']);
        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Venue',
            'status' => $status,
            'final_quoted_price' => $quoteTotal,
            'total_quoted' => $quoteTotal,
        ]);
        \App\Models\Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $admin->id,
            'status' => \App\Models\Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => $quoteTotal,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
        ]);
        return [$user, $booking];
    }

    private function guestBooking(string $status): Booking
    {
        $admin = User::first() ?? $this->user('admin');
        $booking = Booking::create([
            'guest_name' => 'Guest',
            'guest_email' => uniqid('guest-') . '@example.com',
            'guest_phone' => '09170000001',
            'guest_access_token' => (string) Str::uuid(),
            'event_type' => 'birthday',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Venue',
            'status' => $status,
            'final_quoted_price' => 1000,
            'total_quoted' => 1000,
        ]);
        \App\Models\Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $admin->id,
            'status' => \App\Models\Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 1000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
        ]);
        return $booking;
    }

    private function user(string $role): User
    {
        return User::create(['name' => ucfirst($role), 'email' => uniqid($role . '-') . '@example.com', 'password' => bcrypt('password123'), 'role' => $role, 'email_verified_at' => now()]);
    }
}
