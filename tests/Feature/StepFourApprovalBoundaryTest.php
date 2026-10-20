<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StepFourApprovalBoundaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_acceptance_waits_for_admin_approval_before_payment(): void
    {
        [$user, $booking] = $this->clientBooking();

        $this->actingAs($user)->post(route('bookings.accept', $booking))->assertRedirect();
        $this->assertSame('approved', $booking->fresh()->status);

        $this->actingAs($user)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-REF',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])->assertSessionHas('error', 'Payment reference cannot be submitted at this time.');
        $this->assertDatabaseMissing('payments', ['reference_number' => 'CLIENT-REF']);
    }

    public function test_admin_can_final_approve_accepted_client_booking_without_verifying_payment(): void
    {
        [$user, $booking] = $this->clientBooking('approved');
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.bookings.final-approve', $booking))->assertRedirect();
        $booking->refresh();
        $this->assertSame('admin_approved', $booking->status);
    $this->assertSame(0, $booking->payments()->count());

        $this->actingAs($user)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-APPROVED',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])->assertRedirect();
        $this->assertDatabaseHas('payments', ['reference_number' => 'CLIENT-APPROVED', 'status' => 'pending']);
    }

    public function test_guest_acceptance_requires_token_and_expiry_and_waits_for_admin(): void
    {
        $booking = $this->guestBooking();
        $bad = $this->post(route('guest.bookings.accept', $booking), ['guest_token' => 'wrong-token']);
        $bad->assertForbidden();
        $this->assertSame('quotation_sent', $booking->fresh()->status);

        // Phase 2B-6: Unclaimed guest quotation acceptance is blocked
        $this->post(route('guest.bookings.accept', $booking), ['guest_token' => $booking->guest_access_token])
            ->assertRedirect(route('guest.booking.analysis', ['token' => $booking->guest_access_token]))
            ->assertSessionHas('error', 'Official quotations cannot be accepted by an unclaimed guest. Please log in or create an account to claim your booking first.');
        $this->assertSame('quotation_sent', $booking->fresh()->status);

        // Phase 2B-6: Unclaimed guest payment submission is blocked
        $this->post(route('guest.bookings.payment.reference', $booking), [
            'guest_access_token' => $booking->guest_access_token,
            'guest_token' => $booking->guest_access_token,
            'reference_number' => 'GUEST-BEFORE-APPROVAL',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])
            ->assertRedirect(route('guest.booking.analysis', ['token' => $booking->guest_access_token]))
            ->assertSessionHas('error', 'Payment cannot be submitted for an unclaimed guest booking. Please log in to your registered client account to proceed.');

        $this->assertDatabaseMissing('payments', ['reference_number' => 'GUEST-BEFORE-APPROVAL']);
        $this->assertSame('quotation_sent', $booking->fresh()->status);
    }

    public function test_declined_booking_cannot_be_client_accepted(): void
    {
        [$user, $booking] = $this->clientBooking('declined');
        $this->actingAs($user)->post(route('bookings.accept', $booking))->assertSessionHas('error');
        $this->assertSame('declined', $booking->fresh()->status);
    }

    private function clientBooking(string $status = 'quotation_sent'): array
    {
        $admin = User::first() ?? $this->user('admin');
        $user = $this->user('client');
        $client = Client::create([
            'full_name' => 'Client User',
            'email' => $user->email,
            'phone' => '09170000000',
            'address' => 'Address',
        ]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Venue',
            'status' => $status,
            'final_quoted_price' => 1000,
            'total_quoted' => 1000,
            'price_valid_until' => now()->addDays(3)->toDateString(),
        ]);
        \App\Models\Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $admin->id,
            'status' => \App\Models\Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 1000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => now()->addDays(3)->toDateString(),
        ]);

        return [$user, $booking];
    }

    private function guestBooking(): Booking
    {
        $admin = User::first() ?? $this->user('admin');
        $booking = Booking::create([
            'guest_name' => 'Guest User',
            'guest_email' => uniqid('guest-') . '@example.com',
            'guest_phone' => '09170000001',
            'guest_access_token' => (string) \Illuminate\Support\Str::uuid(),
            'event_type' => 'birthday',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Guest Venue',
            'status' => 'quotation_sent',
            'final_quoted_price' => 1000,
            'total_quoted' => 1000,
            'price_valid_until' => now()->addDays(3)->toDateString(),
        ]);
        \App\Models\Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $admin->id,
            'status' => \App\Models\Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 1000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => now()->addDays(3)->toDateString(),
        ]);

        return $booking;
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => uniqid($role . '-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
