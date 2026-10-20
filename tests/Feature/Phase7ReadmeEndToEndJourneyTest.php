<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\BookingWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stabilization Plan — Phase 7: trace one booking from the guest request through event
 * execution and the final return records, across all 14 README workflow stages, using
 * only real HTTP routes for every role.
 */
class Phase7ReadmeEndToEndJourneyTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_travels_all_readme_stages_from_guest_request_to_completion(): void
    {
        $admin = $this->user('admin', 'e2e-admin@example.com');
        $staff = $this->user('staff', 'e2e-staff@example.com');
        $client = $this->user('client', 'e2e-guest@example.com');
        $workflow = app(BookingWorkflowService::class);

        $vase = InventoryItem::create([
            'name' => 'Crystal Pedestal Vase',
            'category' => 'vase',
            'is_perishable' => false,
            'current_stock' => 30,
            'unit_cost' => 200,
            'unit' => 'piece',
            'min_stock' => 0,
        ]);
        $package = Package::create(['title' => 'Elegant Reception Package', 'price' => 20000, 'is_active' => true, 'is_archived' => false]);
        $package->inventoryItems()->attach($vase->id, ['quantity' => 10]);

        // ── Guest Workflow ────────────────────────────────────────────────
        // 1. Request Submitted
        $response = $this->post(route('guest.booking.store'), [
            'guest_name' => 'Elena Santos',
            'guest_email' => 'e2e-guest@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => '12 Rizal Ave, Manila',
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(45)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'Manila',
            'venue_specific' => 'The Garden Pavilion',
        ]);
        $response->assertSessionHasNoErrors();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $temp = TemporaryGuestBooking::where('claim_token_hash', hash('sha256', $token))->firstOrFail();

        // 2. Awaiting Claim
        $this->assertSame('awaiting_claim', $workflow->resolve($temp)['current']);
        $this->get(route('guest.bookings.show', ['token' => $token]))
            ->assertOk()
            ->assertSee('data-workflow-stage="awaiting_claim" data-workflow-state="current"', false);

        $this->actingAs($client)->post(route('client.claim-guest-booking.claim', ['token' => $token]))->assertRedirect(route('bookings'));
        $booking = Booking::firstOrFail();

        // ── Client Workflow ───────────────────────────────────────────────
        // 3. Raflora Review
        $this->assertStage($workflow, $booking, 'raflora_review');
        $this->actingAs($admin)->post(route('admin.bookings.complete-review', $booking))->assertSessionHas('success');

        // 4. Material Preparation / Validation (package materials arrive validated) → quotation preparation
        $this->assertSame('complete', $workflow->resolve($booking->fresh())['stages']['material_validation']['state']);

        // 5. Quotation
        $this->adminUpdate($admin, $booking, ['action' => 'send_quotation', 'valid_until' => now()->addDays(7)->toDateString()])->assertSessionMissing('error');
        $this->assertStage($workflow, $booking, 'quotation');

        // 6. Approval
        $this->actingAs($client)->post(route('bookings.accept', $booking))->assertSessionHas('success');
        $this->assertStage($workflow, $booking, 'approval');
        $this->actingAs($admin)->post(route('admin.bookings.final-approve', $booking))->assertSessionHas('success');

        // 7. Payment (full payment)
        $this->assertStage($workflow, $booking, 'payment');
        $this->actingAs($client)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'BPI-2026-0001',
            'payment_type' => 'bank_transfer',
            'payment_option' => 'full_payment',
        ])->assertSessionHas('success');
        $payment = Payment::where('booking_id', $booking->id)->firstOrFail();
        $quotedTotal = (float) Quotation::where('booking_id', $booking->id)->firstOrFail()->final_quoted_price;
        $this->assertEquals($quotedTotal, (float) $payment->amount);
        $this->assertStage($workflow, $booking, 'payment');

        // 8. Confirmed
        $this->actingAs($admin)->post(route('admin.payments.verify', $payment), ['amount_received' => (float) $payment->amount])->assertSessionHas('success');
        $this->assertSame('confirmed', $booking->fresh()->status);
        $this->assertSame(0.0, $booking->fresh()->remaining_balance);
        $this->assertStage($workflow, $booking, 'confirmed');

        // ── Staff Workflow ────────────────────────────────────────────────
        $this->actingAs($admin)->post(route('admin.bookings.assign-staff', $booking), ['staff_id' => $staff->id])->assertSessionHas('success');

        // 9. Preparation & Reservation
        $this->adminUpdate($admin, $booking, ['preparation_start_date' => now()->toDateString()])->assertSessionMissing('error');
        $this->actingAs($admin)->post(route('admin.bookings.reserve-materials', $booking))->assertSessionHas('success');
        $this->assertSame(-10.0, (float) InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'booking_lock')->sum('quantity_change'));
        $this->assertStage($workflow, $booking, 'preparation_reservation');
        $this->actingAs($staff)->get(route('staff.events.show', $booking))
            ->assertOk()
            ->assertSee('data-workflow-stage="preparation_reservation" data-workflow-state="current"', false);

        // 10. Dispatch (by assigned Staff)
        $this->actingAs($staff)->post(route('staff.events.dispatch', $booking), [
            'items' => [['inventory_item_id' => $vase->id, 'quantity' => 10]],
            'reason' => 'Loaded for venue setup',
        ])->assertSessionHas('success');
        $this->assertSame(20.0, (float) $vase->fresh()->current_stock);
        $this->assertStage($workflow, $booking, 'dispatch');

        // 11. Event Execution
        $this->adminUpdate($admin, $booking, ['action' => 'mark_event_in_progress'])->assertSessionMissing('error');
        $this->assertStage($workflow, $booking, 'event_execution');

        // 12. Material Return
        $this->adminUpdate($admin, $booking, ['action' => 'mark_event_completed'])->assertSessionMissing('error');
        $this->assertSame('pending_return', $booking->fresh()->status);
        $this->assertStage($workflow, $booking, 'material_return');

        $this->actingAs($staff)->get(route('staff.events.show', $booking))->assertOk();
        $return = $booking->fresh()->returns()->firstOrFail();
        $returnItem = $return->returnItems()->firstOrFail();
        $this->actingAs($staff)->put(route('staff.events.return.update', $booking), [
            'items' => [$returnItem->id => ['quantity_good' => 9, 'quantity_damaged' => 1, 'quantity_lost' => 0]],
        ])->assertSessionHas('success');

        // 13. Inventory Reconciliation
        $this->assertStage($workflow, $booking, 'inventory_reconciliation');
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [$returnItem->id => [
                'quantity_good' => 9,
                'quantity_damaged' => 1,
                'quantity_lost' => 0,
                'charge_decision' => 'no_charge',
                'charge_reason' => 'Minor chip, absorbed by Raflora',
            ]],
        ])->assertRedirect(route('admin.return-tracking'));

        // 14. Completion
        $booking->refresh();
        $this->assertSame('completed', $booking->status);
        $result = $workflow->resolve($booking);
        $this->assertTrue($result['finished']);
        foreach ($result['stages'] as $stage) {
            $this->assertSame('complete', $stage['state'], $stage['label'] . ' must be complete at the end of the journey.');
        }

        // Records: usable stock excludes the damaged unit; the history is traceable.
        $this->assertSame(29.0, (float) $vase->fresh()->current_stock);
        $this->assertSame('Completed', $return->fresh()->status);
        $this->actingAs($client)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Workflow complete');

        $statusTrail = AuditLog::where('entity_type', Booking::class)
            ->where('entity_id', $booking->id)
            ->where('action', 'status_changed')
            ->orderBy('id')
            ->get()
            ->map(fn ($log) => $log->new_values['status'] ?? null)
            ->all();
        $this->assertSame(
            ['quotation_sent', 'approved', 'admin_approved', 'payment_submitted', 'confirmed', 'event_in_progress', 'pending_return', 'completed'],
            $statusTrail,
            'Each status change is audited exactly once, in order.'
        );
    }

    private function assertStage(BookingWorkflowService $workflow, Booking $booking, string $expected): void
    {
        $result = $workflow->resolve($booking->fresh());
        $this->assertSame($expected, $result['current'], 'Expected stage ' . $expected . ', got ' . ($result['current'] ?? 'none') . ' (' . ($result['current_detail'] ?? '') . ')');
    }

    private function adminUpdate(User $admin, Booking $booking, array $payload)
    {
        $booking = $booking->fresh();

        return $this->actingAs($admin)->put(route('admin.bookings.update', $booking), array_merge([
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => $booking->status,
            'action' => 'save',
        ], $payload));
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
