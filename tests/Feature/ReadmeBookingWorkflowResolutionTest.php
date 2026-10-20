<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ReturnItem;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\BookingWorkflowService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * README.md end-to-end booking workflow:
 *   Guest  : Request Submitted → Awaiting Claim
 *   Client : Raflora Review → Material Preparation/Validation → Quotation → Approval → Payment → Confirmed
 *   Staff  : Preparation & Reservation → Dispatch → Event Execution → Material Return
 *            → Inventory Reconciliation → Completion
 */
class ReadmeBookingWorkflowResolutionTest extends TestCase
{
    use RefreshDatabase;

    private BookingWorkflowService $workflow;
    private User $admin;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflow = app(BookingWorkflowService::class);
        $this->admin = User::create([
            'name' => 'Workflow Admin',
            'email' => 'workflow-admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
        $this->client = Client::create([
            'full_name' => 'Workflow Client',
            'email' => 'workflow-client@example.com',
            'phone' => '09170000000',
        ]);
    }

    public function test_stage_definitions_follow_the_readme_order_and_phases(): void
    {
        $this->assertSame([
            'request_submitted' => 'Request Submitted',
            'awaiting_claim' => 'Awaiting Claim',
            'raflora_review' => 'Raflora Review',
            'material_validation' => 'Material Preparation / Validation',
            'quotation' => 'Quotation',
            'approval' => 'Approval',
            'payment' => 'Payment',
            'confirmed' => 'Confirmed',
            'preparation_reservation' => 'Preparation & Reservation',
            'dispatch' => 'Dispatch',
            'event_execution' => 'Event Execution',
            'material_return' => 'Material Return',
            'inventory_reconciliation' => 'Inventory Reconciliation',
            'completion' => 'Completion',
        ], array_map(fn ($stage) => $stage['label'], BookingWorkflowService::STAGES));

        $phases = array_count_values(array_map(fn ($stage) => $stage['phase'], BookingWorkflowService::STAGES));
        $this->assertSame(['guest' => 2, 'client' => 6, 'staff' => 6], $phases);
    }

    public function test_unclaimed_temporary_guest_request_is_awaiting_claim(): void
    {
        $temp = $this->temporaryRequest(Carbon::now()->addHours(10));

        $result = $this->workflow->resolve($temp);

        $this->assertSame('awaiting_claim', $result['current']);
        $this->assertSame(2, $result['current_number']);
        $this->assertSame('guest', $result['current_phase']);
        $this->assertSame('complete', $result['stages']['request_submitted']['state']);
        $this->assertSame('upcoming', $result['stages']['raflora_review']['state']);
        $this->assertNull($result['terminal']);
        $this->assertSame('current', $result['phases']['guest']['state']);
        $this->assertSame('upcoming', $result['phases']['client']['state']);
    }

    public function test_expired_temporary_guest_request_stops_the_workflow(): void
    {
        $temp = $this->temporaryRequest(Carbon::now()->subMinute());

        $result = $this->workflow->resolve($temp);

        $this->assertNull($result['current']);
        $this->assertSame('expired', $result['terminal']);
        $this->assertSame('complete', $result['stages']['request_submitted']['state']);
        $this->assertSame('stopped', $result['stages']['awaiting_claim']['state']);
        $this->assertSame('stopped', $result['stages']['completion']['state']);
    }

    public function test_unclaimed_permanent_guest_booking_requires_claim_but_keeps_completed_raflora_work(): void
    {
        $booking = Booking::create([
            'guest_name' => 'Legacy Guest',
            'guest_email' => 'legacy-guest@example.com',
            'guest_access_token' => (string) Str::uuid(),
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Legacy Hall',
            'status' => 'quotation_sent',
        ]);

        $result = $this->workflow->resolve($booking);

        $this->assertSame('awaiting_claim', $result['current']);
        $this->assertSame('complete', $result['stages']['raflora_review']['state']);
        $this->assertSame('complete', $result['stages']['material_validation']['state']);
        $this->assertSame('upcoming', $result['stages']['quotation']['state']);
        $this->assertStringContainsString('Continues after the booking is claimed.', $result['stages']['quotation']['detail']);
    }

    public function test_claimed_pending_booking_starts_at_raflora_review(): void
    {
        $booking = $this->clientBooking('pending', ['guest_access_token' => (string) Str::uuid()]);
        $this->materialItem($booking, ['is_ai_suggested' => true, 'confirmed_at' => null]);

        $result = $this->workflow->resolve($booking);

        $this->assertSame('raflora_review', $result['current']);
        $this->assertSame(3, $result['current_number']);
        $this->assertSame('complete', $result['stages']['awaiting_claim']['state']);
        $this->assertSame('Claimed and linked to the client account.', $result['stages']['awaiting_claim']['detail']);
    }

    public function test_booking_created_from_client_account_skips_claim(): void
    {
        $booking = $this->clientBooking('pending');

        $result = $this->workflow->resolve($booking);

        $this->assertSame('complete', $result['stages']['awaiting_claim']['state']);
        $this->assertSame('Submitted from a registered client account — no claim needed.', $result['stages']['awaiting_claim']['detail']);
    }

    public function test_reviewed_booking_with_unvalidated_materials_is_in_material_validation(): void
    {
        $booking = $this->clientBooking('pending');
        $this->markReviewed($booking);
        $this->materialItem($booking, ['confirmed_at' => now()]);
        $this->materialItem($booking, ['item_name' => 'Peony', 'confirmed_at' => null]);

        $result = $this->workflow->resolve($booking->fresh());

        $this->assertSame('material_validation', $result['current']);
        $this->assertSame('1 of 2 materials validated by Raflora.', $result['current_detail']);
        $this->assertSame('complete', $result['stages']['raflora_review']['state']);
    }

    public function test_reviewed_booking_without_materials_is_preparing_the_material_list(): void
    {
        $booking = $this->clientBooking('pending');
        $this->markReviewed($booking);

        $result = $this->workflow->resolve($booking->fresh());

        $this->assertSame('material_validation', $result['current']);
        $this->assertSame('Raflora is preparing the material list for this event.', $result['current_detail']);
    }

    public function test_admin_confirmed_ai_material_counts_as_review_evidence_for_existing_bookings(): void
    {
        $booking = $this->clientBooking('pending');
        $this->materialItem($booking, ['is_ai_suggested' => true, 'confirmed_at' => now()]);

        $result = $this->workflow->resolve($booking);

        $this->assertSame('quotation', $result['current']);
        $this->assertSame('All materials validated — the official quotation is being prepared.', $result['current_detail']);
    }

    public function test_package_items_confirmed_at_creation_do_not_skip_raflora_review(): void
    {
        $booking = $this->clientBooking('pending');
        $this->materialItem($booking, ['is_ai_suggested' => false, 'confirmed_at' => now()]);

        $this->assertSame('raflora_review', $this->workflow->resolve($booking)['current']);
    }

    public function test_quotation_negotiation_and_expiry_stay_in_the_quotation_stage(): void
    {
        $sent = $this->clientBooking('quotation_sent', ['price_valid_until' => now()->addDays(3)->toDateString()]);
        Quotation::create([
            'booking_id' => $sent->id,
            'issued_by' => $this->admin->id,
            'version' => 2,
            'status' => Quotation::STATUS_ISSUED,
            'final_quoted_price' => 5000,
            'downpayment_percentage' => 50.0,
            'items_snapshot' => [],
            'valid_until' => now()->addDays(3)->toDateString(),
        ]);
        $result = $this->workflow->resolve($sent->fresh());
        $this->assertSame('quotation', $result['current']);
        $this->assertStringStartsWith('Quotation v2 issued — awaiting client review and acceptance', $result['current_detail']);

        $expired = $this->clientBooking('quotation_sent', ['price_valid_until' => now()->subDays(2)->toDateString()]);
        $this->assertStringStartsWith('Quotation expired on', $this->workflow->resolve($expired)['current_detail']);

        $changes = $this->clientBooking('change_requested');
        $result = $this->workflow->resolve($changes);
        $this->assertSame('quotation', $result['current']);
        $this->assertSame('Changes requested by the client — Raflora is revising the quotation.', $result['current_detail']);
    }

    public function test_approval_and_payment_stages_follow_booking_status(): void
    {
        $this->assertSame('approval', $this->workflow->resolve($this->clientBooking('approved'))['current']);
        $this->assertSame('payment', $this->workflow->resolve($this->clientBooking('admin_approved'))['current']);
        $this->assertSame('payment', $this->workflow->resolve($this->clientBooking('payment_submitted'))['current']);

        $rejected = $this->clientBooking('admin_approved');
        Payment::create([
            'booking_id' => $rejected->id,
            'reference_number' => 'REJECTED-REF-1',
            'payment_type' => 'gcash',
            'amount' => 500,
            'status' => 'rejected',
        ]);
        $this->assertSame(
            'The previous payment reference was not verified — a corrected reference is required.',
            $this->workflow->resolve($rejected->fresh())['current_detail']
        );
    }

    public function test_confirmed_booking_waits_for_preparation_schedule(): void
    {
        $booking = $this->clientBooking('downpayment_received', [
            'preparation_start_date' => now()->addDays(5)->toDateString(),
        ]);

        $result = $this->workflow->resolve($booking);

        $this->assertSame('confirmed', $result['current']);
        $this->assertSame(8, $result['current_number']);
        $this->assertStringStartsWith('Booking confirmed — preparation starts on', $result['current_detail']);
        $this->assertSame('current', $result['phases']['client']['state']);
        $this->assertSame('upcoming', $result['phases']['staff']['state']);
        $this->assertSame('complete', $result['phases']['guest']['state']);
    }

    public function test_preparation_reservation_and_dispatch_follow_inventory_transactions(): void
    {
        $booking = $this->clientBooking('confirmed', ['preparation_start_date' => now()->toDateString()]);
        $arch = $this->reusableItem('Gold Arch');
        $this->materialItem($booking, ['inventory_item_id' => $arch->id, 'item_name' => $arch->name, 'quantity' => 4, 'confirmed_at' => now()]);

        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('preparation_reservation', $result['current']);
        $this->assertSame('Preparation period started — reusable materials are awaiting reservation.', $result['current_detail']);

        $this->transaction($booking, $arch, -4, 'booking_lock');
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('preparation_reservation', $result['current']);
        $this->assertSame('Reusable materials reserved — preparing for dispatch.', $result['current_detail']);

        $this->transaction($booking, $arch, -1, 'dispatch');
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('dispatch', $result['current']);
        $this->assertSame('Dispatch in progress — 3 reserved unit(s) still to dispatch.', $result['current_detail']);

        $this->transaction($booking, $arch, -3, 'dispatch');
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('dispatch', $result['current']);
        $this->assertSame('All reserved materials dispatched — ready for event execution.', $result['current_detail']);
        $this->assertSame('complete', $result['stages']['preparation_reservation']['state']);
    }

    public function test_event_execution_material_return_reconciliation_and_completion(): void
    {
        $booking = $this->clientBooking('event_in_progress');
        $this->assertSame('event_execution', $this->workflow->resolve($booking)['current']);

        $arch = $this->reusableItem('Crystal Stand');
        $this->materialItem($booking, ['inventory_item_id' => $arch->id, 'item_name' => $arch->name, 'quantity' => 2, 'confirmed_at' => now()]);
        $this->transaction($booking, $arch, -2, 'dispatch');
        $booking->update(['status' => 'pending_return']);

        $return = AssetReturn::create(['booking_id' => $booking->id, 'status' => 'Pending', 'total_damage_charge' => 0]);
        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $arch->id,
            'quantity_returned' => 0,
            'quantity_good' => 0,
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
            'condition' => 'pending',
            'damage_charge' => 0,
        ]);

        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('material_return', $result['current']);
        $this->assertSame('0 of 2 dispatched unit(s) accounted for.', $result['current_detail']);

        $returnItem->update(['quantity_returned' => 2, 'quantity_good' => 2]);
        $return->update(['status' => 'Partially Returned']);
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('inventory_reconciliation', $result['current']);

        $returnItem->update(['quantity_good' => 1, 'quantity_damaged' => 1, 'condition' => 'mixed', 'charge_decision' => 'pending']);
        $booking->update(['status' => 'pending_resolution']);
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('inventory_reconciliation', $result['current']);
        $this->assertSame('Damage or loss charges are awaiting Raflora resolution.', $result['current_detail']);

        $returnItem->update(['charge_decision' => 'no_charge']);
        $return->update(['status' => 'Completed']);
        $booking->update(['status' => 'event_completed', 'final_quoted_price' => 1000]);
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertSame('completion', $result['current']);
        $this->assertSame('Return audit complete — awaiting final balance settlement.', $result['current_detail']);
        $this->assertFalse($result['finished']);

        $booking->update(['status' => 'completed']);
        $result = $this->workflow->resolve($booking->fresh());
        $this->assertTrue($result['finished']);
        $this->assertSame('completion', $result['current']);
        foreach ($result['stages'] as $stage) {
            $this->assertSame('complete', $stage['state'], $stage['key'] . ' should be complete');
        }
    }

    public function test_zero_hardware_event_waits_for_raflora_to_close_the_return_audit(): void
    {
        $booking = $this->clientBooking('pending_return');

        $result = $this->workflow->resolve($booking);

        $this->assertSame('material_return', $result['current']);
        $this->assertSame('No reusable materials were dispatched — awaiting Raflora to close the return audit.', $result['current_detail']);
    }

    public function test_cancelled_and_declined_bookings_are_terminal_without_a_current_stage(): void
    {
        foreach (['cancelled' => 'Booking Cancelled', 'declined' => 'Booking Declined'] as $status => $label) {
            $result = $this->workflow->resolve($this->clientBooking($status));

            $this->assertNull($result['current']);
            $this->assertSame($status, $result['terminal']);
            $this->assertSame($label, $result['terminal_label']);
            $this->assertSame('complete', $result['stages']['awaiting_claim']['state']);
            $this->assertSame('stopped', $result['stages']['raflora_review']['state']);
        }
    }

    public function test_cancellation_request_keeps_the_stage_it_was_requested_from(): void
    {
        $booking = $this->clientBooking('cancellation_requested', ['pre_cancellation_status' => 'admin_approved']);

        $result = $this->workflow->resolve($booking);

        $this->assertSame('payment', $result['current']);
        $this->assertStringStartsWith('Cancellation requested — awaiting Raflora decision.', $result['current_detail']);
    }

    private function temporaryRequest(Carbon $expiresAt): TemporaryGuestBooking
    {
        $temp = new TemporaryGuestBooking([
            'guest_name' => 'Temporary Guest',
            'guest_email' => 'temporary-guest@example.com',
            'guest_phone' => '09171234567',
            'guest_address' => 'Manila',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Guest Hall',
            'expires_at' => $expiresAt,
        ]);
        $temp->generateToken();
        $temp->save();

        return $temp;
    }

    private function clientBooking(string $status, array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Workflow Venue',
            'status' => $status,
            'confirmed_at' => in_array($status, ['downpayment_received', 'confirmed', 'event_in_progress'], true) ? now() : null,
        ], $attributes));
    }

    private function markReviewed(Booking $booking): void
    {
        $booking->markReviewed($this->admin->id);
        $booking->save();
    }

    private function materialItem(Booking $booking, array $attributes = []): BookingItem
    {
        return BookingItem::create(array_merge([
            'booking_id' => $booking->id,
            'item_name' => 'White Rose',
            'quantity' => 10,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
        ], $attributes));
    }

    private function reusableItem(string $name): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => 20,
            'unit_cost' => 100,
            'unit' => 'piece',
            'min_stock' => 0,
        ]);
    }

    private function transaction(Booking $booking, InventoryItem $item, float $change, string $type): void
    {
        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => $change,
            'transaction_type' => $type,
            'reason' => 'Workflow test ' . $type,
            'performed_by' => $this->admin->id,
        ]);
    }
}
