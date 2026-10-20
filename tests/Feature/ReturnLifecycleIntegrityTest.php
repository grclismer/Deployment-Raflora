<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Admin audit F11/F13: a return record is created in one way — from what was actually dispatched —
 * at the post-event transition or an explicit Admin "start", never by viewing a page or twice; and
 * Admin's damage/loss approval re-evaluates the booking with the existing completion rules and
 * tells the client once.
 */
class ReturnLifecycleIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $clientUser;
    private User $staffUser;
    private InventoryItem $arch;
    private InventoryItem $vase;
    private InventoryItem $candleStand;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
    }

    // F11 — Ending the event creates one record from the dispatch ledger, and nothing in the catalog.
    public function test_event_completion_creates_one_return_from_dispatched_items_only(): void
    {
        $booking = $this->dispatchedBooking('event_in_progress', 18000);
        $catalogBefore = InventoryItem::count();

        $this->admin()->put(route('admin.bookings.update', $booking), $this->form($booking, ['action' => 'mark_event_completed']))
            ->assertSessionMissing('error');

        $this->assertSame('pending_return', $booking->fresh()->status);
        $this->assertSame(1, $booking->returns()->count());
        $items = $booking->returns()->first()->returnItems->pluck('inventory_item_id')->sort()->values()->all();
        $this->assertSame(collect([$this->arch->id, $this->vase->id])->sort()->values()->all(), $items);
        $this->assertSame($catalogBefore, InventoryItem::count());
    }

    // F11 — Viewing the return pages and the Staff event page writes nothing.
    public function test_display_pages_do_not_create_return_records(): void
    {
        $booking = $this->dispatchedBooking('pending_return', 18000);

        $this->admin()->get(route('admin.return-tracking'))->assertOk();
        $this->admin()->get(route('admin.return-tracking.manage', $booking))->assertOk();
        $this->actingAs($this->staffUser)->get(route('staff.events.show', $booking))->assertOk();

        $this->assertSame(0, AssetReturn::count());
        $this->assertSame(0, ReturnItem::count());
    }

    // F11 — Admin starts an audit explicitly; repeats and the Staff path reuse the same record.
    public function test_starting_an_audit_is_explicit_and_repeat_safe(): void
    {
        $booking = $this->dispatchedBooking('pending_return', 18000);

        $this->admin()->post(route('admin.return-tracking.start', $booking))
            ->assertRedirect(route('admin.return-tracking.manage', $booking));
        $this->admin()->post(route('admin.return-tracking.start', $booking));
        $this->actingAs($this->staffUser)->put(route('staff.events.return.update', $booking), [
            'items' => [],
        ]);

        $this->assertSame(1, AssetReturn::count());
        $this->assertSame(2, ReturnItem::count());
    }

    // F11 — A booking with nothing dispatched gets an empty audit Admin can close.
    public function test_zero_hardware_audit_can_be_started_and_closed(): void
    {
        $booking = $this->booking('pending_return', 18000);
        $this->verifiedPayment($booking, 18000);

        $this->admin()->post(route('admin.return-tracking.start', $booking));
        $return = $booking->returns()->sole();
        $this->assertSame(0, $return->returnItems()->count());

        $this->admin()->put(route('admin.return-tracking.update', $return), ['notes' => 'Fresh flowers only'])
            ->assertSessionHas('success');

        $this->assertSame('Completed', $return->fresh()->status);
        $this->assertSame('completed', $booking->fresh()->status);
    }

    // Existing behaviour kept: split quantities with damage waiting for a decision.
    public function test_split_quantities_with_pending_damage_wait_for_resolution(): void
    {
        [$booking, $return] = $this->assessedReturn(18000);

        $this->assertSame('pending_resolution', $booking->fresh()->status);
        $this->assertSame('pending', $return->fresh()->approval_status);
        $arch = $this->returnItem($return, $this->arch);
        $this->assertEquals([1.0, 1.0, 0.0], [(float) $arch->quantity_good, (float) $arch->quantity_damaged, (float) $arch->quantity_lost]);
    }

    // F13 — Approval with everything accounted and paid completes the booking and tells the client once.
    public function test_approval_resolving_everything_completes_a_paid_booking_once(): void
    {
        [$booking, $return] = $this->assessedReturn(18000);
        $stockBefore = [(float) $this->arch->fresh()->current_stock, (float) $this->vase->fresh()->current_stock];

        $this->admin()->put(route('admin.return-tracking.approve', $return), ['decision' => 'approved'])->assertSessionHas('success');
        $this->admin()->put(route('admin.return-tracking.approve', $return), ['decision' => 'approved'])->assertSessionHas('info');

        $this->assertSame('no_charge', $this->returnItem($return, $this->arch)->charge_decision);
        $this->assertSame('Completed', $return->fresh()->status);
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(1, $this->notifications($booking, 'Return review complete'));
        $this->assertSame(1, $this->notifications($booking, 'Booking completed'));
        $this->assertSame($stockBefore, [(float) $this->arch->fresh()->current_stock, (float) $this->vase->fresh()->current_stock]);
        $this->assertSame(1, Payment::where('booking_id', $booking->id)->count());
    }

    // F13 — An approved charge leaves a balance: the event stays completed, awaiting the client's payment.
    public function test_approved_charge_adds_to_balance_and_notifies_the_amount(): void
    {
        [$booking, $return] = $this->assessedReturn(18000);
        $this->returnItem($return, $this->arch)->update(['damage_charge' => 500]);

        $this->admin()->put(route('admin.return-tracking.approve', $return), ['decision' => 'approved'])->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('charge', $this->returnItem($return, $this->arch)->charge_decision);
        $this->assertEquals(500.0, (float) $return->fresh()->total_damage_charge);
        $this->assertSame('event_completed', $booking->status);
        $this->assertEquals(500.0, $booking->remaining_balance);
        $message = ClientNotification::where('booking_id', $booking->id)->where('title', 'Return review complete')->sole()->message;
        $this->assertStringContainsString('₱500.00', $message);
        $this->assertSame(0, $this->notifications($booking, 'Booking completed'));
    }

    // F13 — Approval does not complete anything while returned quantities are still missing.
    public function test_approval_keeps_booking_open_while_items_are_unaccounted(): void
    {
        [$booking, $return] = $this->assessedReturn(18000, vaseGood: 4);

        $this->admin()->put(route('admin.return-tracking.approve', $return), ['decision' => 'approved'])->assertSessionHas('success');

        $this->assertSame('Partially Returned', $return->fresh()->status);
        $this->assertSame('pending_return', $booking->fresh()->status);
        $this->assertSame(0, $this->notifications($booking, 'Booking completed'));
    }

    // F13 — Rejecting the adjudication leaves the decisions open and the client is not told anything.
    public function test_rejected_adjudication_keeps_decisions_pending(): void
    {
        [$booking, $return] = $this->assessedReturn(18000);

        $this->admin()->put(route('admin.return-tracking.approve', $return), ['decision' => 'rejected', 'reason' => 'Recount needed'])->assertSessionHas('success');

        $this->assertSame('pending', $this->returnItem($return, $this->arch)->charge_decision);
        $this->assertSame('pending_resolution', $booking->fresh()->status);
        $this->assertSame(0, ClientNotification::where('booking_id', $booking->id)->count());
    }

    // F13 — Nothing to decide, an invalid decision, or a non-Admin: refused without changes.
    public function test_invalid_or_unauthorized_approvals_change_nothing(): void
    {
        [$booking, $return] = $this->assessedReturn(18000);

        $this->actingAs($this->staffUser)->put(route('admin.return-tracking.approve', $return), ['decision' => 'approved'])->assertForbidden();
        $this->actingAs($this->clientUser)->put(route('admin.return-tracking.approve', $return), ['decision' => 'approved'])->assertForbidden();
        $this->admin()->put(route('admin.return-tracking.approve', $return), ['decision' => 'maybe'])->assertSessionHasErrors('decision');
        $this->assertSame('pending', $return->fresh()->approval_status);

        $clean = $this->dispatchedBooking('pending_return', 18000);
        $cleanReturn = $this->openReturn($clean);
        $this->admin()->put(route('admin.return-tracking.approve', $cleanReturn), ['decision' => 'approved'])->assertSessionHas('error');
        $this->assertSame('not_required', $cleanReturn->fresh()->approval_status);
        $this->assertSame(0, AuditLog::where('action', 'return_damage_approved')->count());
    }

    /**
     * Post-event booking whose return is assessed: arch 1 good + 1 damaged (decision pending),
     * vase $vaseGood of 6 good.
     */
    private function assessedReturn(float $paid, float $vaseGood = 6): array
    {
        $booking = $this->dispatchedBooking('pending_return', $paid);
        $return = $this->openReturn($booking);

        $this->admin()->put(route('admin.return-tracking.update', $return), ['items' => [
            $this->returnItem($return, $this->arch)->id => ['quantity_good' => 1, 'quantity_damaged' => 1, 'quantity_lost' => 0, 'charge_decision' => 'pending'],
            $this->returnItem($return, $this->vase)->id => ['quantity_good' => $vaseGood, 'quantity_damaged' => 0, 'quantity_lost' => 0],
        ]])->assertSessionHas('success');

        return [$booking, $return->fresh()];
    }

    /**
     * Booking for ₱18,000 with: Gold Arch ×2 dispatched, Glass Vase 10 confirmed but 6 dispatched,
     * Brass Candle Stand ×4 reserved but never dispatched, and an unlinked "Velvet Backdrop Stand" line.
     */
    private function dispatchedBooking(string $status, float $paid): Booking
    {
        $booking = $this->booking($status, $paid > 0 ? $paid : 0);
        if ($paid > 0) {
            $this->verifiedPayment($booking, $paid);
        }

        $this->arch = $this->inventory('Gold Arch', 5);
        $this->vase = $this->inventory('Glass Vase', 20);
        $this->candleStand = $this->inventory('Brass Candle Stand', 8);

        foreach ([[$this->arch, 2, 2], [$this->vase, 10, 6], [$this->candleStand, 4, 0]] as [$item, $reserved, $dispatched]) {
            BookingItem::create(['booking_id' => $booking->id, 'inventory_item_id' => $item->id, 'item_name' => $item->name,
                'quantity' => $reserved, 'quoted_unit_price' => 100, 'confirmed_at' => now()]);
            InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $booking->id, 'quantity_change' => -$reserved,
                'transaction_type' => 'booking_lock', 'reason' => 'Reserve', 'performed_by' => $this->adminUser()->id]);
            if ($dispatched > 0) {
                InventoryTransaction::create(['inventory_item_id' => $item->id, 'booking_id' => $booking->id, 'quantity_change' => -$dispatched,
                    'transaction_type' => 'dispatch', 'reason' => 'Dispatch', 'performed_by' => $this->adminUser()->id]);
                $item->decrement('current_stock', $dispatched);
            }
        }
        BookingItem::create(['booking_id' => $booking->id, 'item_name' => 'Velvet Backdrop Stand', 'quantity' => 1,
            'quoted_unit_price' => 300, 'confirmed_at' => now()]);

        return $booking;
    }

    /** An open return record for the dispatched items, built directly so approval tests do not depend on how it was created. */
    private function openReturn(Booking $booking): AssetReturn
    {
        $return = AssetReturn::create(['booking_id' => $booking->id, 'status' => 'Pending', 'total_damage_charge' => 0, 'approval_status' => 'not_required']);
        foreach ([$this->arch, $this->vase] as $item) {
            ReturnItem::create(['return_id' => $return->id, 'inventory_item_id' => $item->id, 'quantity_returned' => 0, 'quantity_good' => 0,
                'quantity_damaged' => 0, 'quantity_lost' => 0, 'condition' => 'pending', 'damage_charge' => 0]);
        }

        return $return;
    }

    private function booking(string $status, float $price): Booking
    {
        $this->clientUser = User::factory()->create(['role' => 'client', 'email' => 'returns-' . uniqid() . '@example.com']);
        $this->staffUser = User::factory()->create(['role' => 'staff', 'email' => 'staff-' . uniqid() . '@example.com']);
        $client = Client::create(['full_name' => 'Return Client', 'email' => $this->clientUser->email, 'phone' => '09170000000', 'address' => 'Manila']);

        return Booking::create([
            'client_id' => $client->id,
            'staff_id' => $this->staffUser->id,
            'event_type' => 'wedding',
            'event_date' => now()->subDay()->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => $status,
            'final_quoted_price' => 18000,
            'total_quoted' => 18000,
            'confirmed_at' => now()->subDays(10),
        ]);
    }

    private function verifiedPayment(Booking $booking, float $amount): void
    {
        Payment::create(['booking_id' => $booking->id, 'amount' => $amount, 'amount_paid' => $amount, 'payment_option' => 'full_payment',
            'payment_type' => 'gcash', 'reference_number' => 'PAID-' . uniqid(), 'status' => 'fully_paid', 'verified_at' => now()]);
    }

    private function inventory(string $name, float $stock): InventoryItem
    {
        return InventoryItem::create(['name' => $name, 'category' => 'props', 'is_perishable' => false, 'current_stock' => $stock,
            'unit_cost' => 100, 'min_stock' => 0, 'unit' => 'piece']);
    }

    private function returnItem(AssetReturn $return, InventoryItem $item): ReturnItem
    {
        return ReturnItem::where('return_id', $return->id)->where('inventory_item_id', $item->id)->sole();
    }

    private function notifications(Booking $booking, string $title): int
    {
        return ClientNotification::where('booking_id', $booking->id)->where('title', $title)->count();
    }

    private function form(Booking $booking, array $fields): array
    {
        return array_merge(['event_type' => $booking->event_type, 'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue, 'status' => $booking->status], $fields);
    }

    private function adminUser(): User
    {
        return User::firstOrCreate(['email' => 'admin-returns@example.com'], ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin']);
    }

    private function admin(): static
    {
        return $this->actingAs($this->adminUser());
    }
}
