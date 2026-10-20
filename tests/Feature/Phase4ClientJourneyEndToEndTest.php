<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stabilization Plan — Phase 4: Booking → Quotation → Acceptance → Payment → Confirmation,
 * driven only through real HTTP routes as the Client and the Admin.
 */
class Phase4ClientJourneyEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $clientUser;
    private User $otherClientUser;
    private Package $package;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'p4-admin@example.com');
        $this->clientUser = $this->user('client', 'p4-client@example.com');
        $this->otherClientUser = $this->user('client', 'p4-other@example.com');
        Client::create(['full_name' => 'Other Client', 'email' => $this->otherClientUser->email]);

        $this->package = Package::create([
            'title' => 'Garden Romance Package',
            'price' => 20000,
            'is_active' => true,
            'is_archived' => false,
        ]);
        $vase = InventoryItem::create([
            'name' => 'Glass Cylinder Vase',
            'category' => 'vase',
            'is_perishable' => false,
            'current_stock' => 30,
            'unit_cost' => 150,
            'unit' => 'piece',
            'min_stock' => 0,
        ]);
        $this->package->inventoryItems()->attach($vase->id, ['quantity' => 10]);
    }

    public function test_client_completes_booking_through_confirmation(): void
    {
        // 1. Booking — the client submits a package booking.
        $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $this->package->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(40)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'Tagaytay Garden, Cavite',
            'guest_count' => 120,
            'special_requests' => 'White and blush palette',
        ])->assertSessionHasNoErrors();

        $booking = Booking::firstOrFail();
        $client = Client::where('email', $this->clientUser->email)->firstOrFail();
        $this->assertSame($client->id, $booking->client_id);
        $this->assertSame('pending', $booking->status);
        $this->assertGreaterThan(0, $booking->bookingItems()->count());

        $this->actingAs($this->clientUser)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('data-workflow-stage="raflora_review" data-workflow-state="current"', false)
            ->assertDontSee('Submit Payment Reference');

        // Another client can never see or act on it.
        $this->actingAs($this->otherClientUser)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($this->otherClientUser)->post(route('bookings.accept', $booking))->assertForbidden();

        // 2. Quotation — Admin reviews and issues the official quotation.
        $this->actingAs($this->admin)->post(route('admin.bookings.complete-review', $booking))->assertSessionHas('success');
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'wedding',
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'send_quotation',
            'valid_until' => now()->addDays(7)->toDateString(),
        ])->assertSessionMissing('error');

        $booking->refresh();
        $this->assertSame('quotation_sent', $booking->status);
        $quotation = Quotation::where('booking_id', $booking->id)->where('status', Quotation::STATUS_ISSUED)->firstOrFail();
        $quotedTotal = (float) $quotation->final_quoted_price;
        $this->assertGreaterThan(0, $quotedTotal);

        $this->actingAs($this->clientUser)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Quotation Ready')
            ->assertSee('Accept Quotation')
            ->assertSee('data-workflow-stage="quotation" data-workflow-state="current"', false);

        // 3. Acceptance — the client accepts; this does not confirm the booking.
        $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking))
            ->assertRedirect(route('bookings.analysis', ['booking' => $booking->id]));
        $this->assertSame('approved', $booking->fresh()->status);
        $this->assertSame(Quotation::STATUS_ACCEPTED, $quotation->fresh()->status);

        // Payment stays locked until Raflora's final approval.
        $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'EARLY-REF-1',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])->assertSessionHas('error');
        $this->assertSame(0, Payment::count());

        $this->actingAs($this->admin)->post(route('admin.bookings.final-approve', $booking))->assertSessionHas('success');
        $this->assertSame('admin_approved', $booking->fresh()->status);

        $this->actingAs($this->clientUser)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Submit Payment Reference')
            ->assertSee('data-workflow-stage="payment" data-workflow-state="current"', false);

        // 4. Payment — the client submits a downpayment reference (not yet verified).
        $this->actingAs($this->clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'GCASH-778899',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ])->assertSessionHas('success');

        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $expectedDownpayment = round($quotedTotal * ((float) $quotation->downpayment_percentage / 100), 2);
        $this->assertSame('pending', $payment->status);
        $this->assertSame($quotation->id, $payment->quotation_id);
        $this->assertEquals($expectedDownpayment, (float) $payment->amount);
        $this->assertSame('payment_submitted', $booking->fresh()->status);
        $this->assertSame(0.0, $booking->fresh()->total_paid, 'A submitted payment is not a verified payment.');

        // 5. Confirmation — Raflora verifies the exact amount.
        $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment), ['amount_received' => $expectedDownpayment + 1])
            ->assertSessionHasErrors('amount_received');
        $this->assertSame('payment_submitted', $booking->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment), ['amount_received' => $expectedDownpayment])
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('downpayment_received', $booking->status);
        $this->assertNotNull($booking->confirmed_at);
        $this->assertEquals($expectedDownpayment, $booking->total_paid);
        $this->assertEquals(round($quotedTotal - $expectedDownpayment, 2), round($booking->remaining_balance, 2));

        $this->actingAs($this->clientUser)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Booking Confirmed')
            ->assertSee('data-workflow-stage="confirmed" data-workflow-state="current"', false)
            ->assertDontSee('Submit Payment Reference');

        $this->actingAs($this->clientUser)->getJson(route('bookings.status', $booking))
            ->assertOk()
            ->assertJsonPath('status', 'downpayment_received');
        $this->actingAs($this->otherClientUser)->getJson(route('bookings.status', $booking))->assertForbidden();
    }

    public function test_client_can_request_changes_and_receive_a_revised_quotation(): void
    {
        $this->actingAs($this->clientUser)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $this->package->id,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '18:00',
            'venue' => 'Manila Hotel, Manila',
        ])->assertSessionHasNoErrors();
        $booking = Booking::firstOrFail();

        $send = fn () => $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => 'birthday',
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => $booking->fresh()->status,
            'action' => 'send_quotation',
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);
        $send()->assertSessionMissing('error');

        $this->actingAs($this->clientUser)->post(route('bookings.request-changes', $booking), [
            'change_type' => 'material',
            'change_item' => 'Glass Cylinder Vase',
            'change_quantity' => 12,
            'change_reason' => 'Two more tables',
        ])->assertSessionHas('success');
        $this->assertSame('change_requested', $booking->fresh()->status);

        // An outdated quotation cannot be accepted while changes are pending.
        $this->actingAs($this->clientUser)->post(route('bookings.accept', $booking))
            ->assertSessionHas('error', 'This booking cannot be accepted at this stage.');

        $send()->assertSessionMissing('error');
        $this->assertSame('quotation_sent', $booking->fresh()->status);
        $this->assertSame(2, Quotation::where('booking_id', $booking->id)->count());
        $this->assertSame(1, Quotation::where('booking_id', $booking->id)->where('status', Quotation::STATUS_ISSUED)->count());
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Journey',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
