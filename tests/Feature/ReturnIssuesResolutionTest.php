<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Damaged / lost returns: each item needs a charge decision, every item must be accounted and the
 * charges paid; then the issues are resolved and the booking completes (Admin and client portals).
 */
class ReturnIssuesResolutionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $clientUser;
    private Booking $booking;
    private InventoryItem $vase;
    private AssetReturn $return;
    private ReturnItem $line;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Raflora Admin']);
        $this->clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $this->clientUser->email, 'phone' => '09170000000', 'user_id' => $this->clientUser->id]);
        $this->booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'pending_return',
            'confirmed_at' => now()->subDays(5),
            'event_type' => 'wedding',
            'event_date' => now()->subDay()->toDateString(),
            'venue' => 'Grand Hall',
            'final_quoted_price' => 1000,
            'total_quoted' => 1000,
        ]);
        Payment::create(['booking_id' => $this->booking->id, 'amount' => 1000, 'amount_paid' => 1000, 'payment_option' => 'full_payment', 'payment_type' => 'gcash', 'reference_number' => 'REF-FULL', 'status' => 'fully_paid', 'verified_at' => now()]);
        $this->vase = InventoryItem::create(['name' => 'Glass Vase', 'category' => 'Props', 'is_perishable' => false, 'current_stock' => 0, 'unit_cost' => 50, 'min_stock' => 0, 'unit' => 'pieces']);
        InventoryTransaction::create(['booking_id' => $this->booking->id, 'inventory_item_id' => $this->vase->id, 'transaction_type' => 'dispatch', 'quantity_change' => -10]);
        $this->return = AssetReturn::create(['booking_id' => $this->booking->id, 'status' => 'Pending', 'total_damage_charge' => 0, 'approval_status' => 'not_required']);
        $this->line = ReturnItem::create(['return_id' => $this->return->id, 'inventory_item_id' => $this->vase->id, 'quantity_returned' => 0, 'quantity_good' => 0, 'quantity_damaged' => 0, 'quantity_lost' => 0, 'condition' => 'pending', 'damage_charge' => 0, 'charge_decision' => 'pending']);
    }

    public function test_undecided_and_unpaid_issues_block_until_settled_then_the_booking_completes(): void
    {
        // 1. Damage and loss recorded without a decision: the booking waits for resolution.
        $this->record(['quantity_good' => 7, 'quantity_damaged' => 2, 'quantity_lost' => 1, 'charge_decision' => 'pending', 'damage_charge' => 0]);
        $this->assertSame('pending_resolution', $this->booking->fresh()->status);

        $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $this->booking))
            ->assertOk()
            ->assertSee('Damage &amp; Loss Resolution', false)
            ->assertSee('Choose Charge Client or No Charge for: Glass Vase, then Save &amp; Reconcile.', false);
        $this->resolve()->assertSessionHas('error', 'Cannot resolve yet: Choose Charge Client or No Charge for: Glass Vase, then Save & Reconcile.');

        // 2. Charge decided but not paid yet: still blocked, naming the amount owed.
        $this->record(['quantity_good' => 7, 'quantity_damaged' => 2, 'quantity_lost' => 1, 'charge_decision' => 'charge', 'damage_charge' => 300]);
        $this->assertSame(300.0, (float) $this->booking->fresh()->remaining_balance);
        $this->resolve()->assertSessionHas('error', 'Cannot resolve yet: ₱300.00 is still unpaid (damage/loss charges included). Log or verify the payment first.');
        $this->assertNotSame('completed', $this->booking->fresh()->status);

        // 3. The client pays the charge and Admin verifies it: the booking completes.
        $payment = Payment::create(['booking_id' => $this->booking->id, 'amount' => 300, 'payment_option' => 'full_payment', 'payment_type' => 'gcash', 'reference_number' => 'REF-DAMAGE', 'status' => 'pending']);
        $this->resolve()->assertSessionHas('error');
        $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment->id))->assertSessionHas('success');

        $this->assertSame('completed', $this->booking->fresh()->status);
        $this->assertSame('Completed', $this->return->fresh()->status);
        $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $this->booking))
            ->assertSee('Resolved — the issues are settled and the return audit is completed.');
        $this->actingAs($this->clientUser)->get(route('bookings.show', $this->booking))->assertSee('Completed');
    }

    public function test_a_settled_but_stuck_return_is_completed_by_mark_issues_resolved(): void
    {
        // Every issue decided and paid, but the return was left Partially Returned / pending approval.
        $this->line->update(['quantity_good' => 7, 'quantity_damaged' => 2, 'quantity_lost' => 1, 'quantity_returned' => 9, 'condition' => 'mixed', 'charge_decision' => 'no_charge', 'damage_charge' => 0]);
        $this->return->update(['status' => 'Partially Returned', 'approval_status' => 'pending']);
        $this->booking->update(['status' => 'pending_resolution']);

        $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $this->booking))
            ->assertSee('Ready to resolve')
            ->assertSee('Mark Issues Resolved &amp; Complete Booking', false);

        $this->resolve()->assertSessionHas('success', 'Damage and loss issues resolved. Booking #' . $this->booking->id . ' is now Completed.');

        $this->assertSame('completed', $this->booking->fresh()->status);
        $this->assertSame(['Completed', 'approved'], [$this->return->fresh()->status, $this->return->fresh()->approval_status]);
        $this->assertTrue(AuditLog::where('action', 'return_issues_resolved')->exists());
        $this->assertTrue(ClientNotification::where('booking_id', $this->booking->id)->where('title', 'Booking completed')->exists());

        $this->resolve()->assertSessionHas('info', 'The damage and loss issues on this return are already resolved.');
    }

    public function test_only_admin_can_resolve_and_returns_without_issues_have_nothing_to_resolve(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']))
            ->put(route('admin.return-tracking.resolve', $this->return))
            ->assertForbidden();

        $this->line->update(['quantity_good' => 10, 'quantity_returned' => 10, 'condition' => 'good']);
        $this->resolve()->assertSessionHas('error', 'No item on this return is damaged or lost, so there is nothing to resolve. Save & Reconcile completes it.');
    }

    private function record(array $values)
    {
        return $this->actingAs($this->admin)
            ->put(route('admin.return-tracking.update', $this->return), [
                'items' => [$this->line->id => ['inventory_item_id' => $this->vase->id] + $values],
                'notes' => 'Inspected',
            ])
            ->assertSessionHasNoErrors();
    }

    private function resolve()
    {
        return $this->actingAs($this->admin)
            ->from(route('admin.return-tracking.manage', $this->booking))
            ->put(route('admin.return-tracking.resolve', $this->return));
    }
}
