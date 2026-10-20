<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin audit F12: marking alerts read, singly or in bulk, never closes a Staff stock-count
 * request — only Admin's approve or reject decision does, and only once. Payment and stock
 * decisions in the Notifications pop-up ask for confirmation.
 */
class AdminAlertDecisionIntegrityTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_stock_count_request_read_is_refused_and_keeps_it_open(): void
    {
        [$variance, $item] = $this->varianceRequest();

        $this->actingAs($this->admin())->post(route('admin.alerts.read', $variance))->assertSessionHas('error');

        $this->assertFalse($variance->fresh()->is_read);
        $this->assertEquals(10.0, (float) $item->fresh()->current_stock);
    }

    public function test_ordinary_alert_can_still_be_marked_read(): void
    {
        $message = $this->alert('client_message');

        $this->actingAs($this->admin())->post(route('admin.alerts.read', $message))->assertSessionHas('success');

        $this->assertTrue($message->fresh()->is_read);
    }

    public function test_dismiss_all_closes_acknowledgements_but_keeps_stock_count_requests(): void
    {
        [$variance, $item] = $this->varianceRequest();
        $message = $this->alert('client_message');
        $expired = $this->alert('quotation_expired');

        $this->actingAs($this->admin())->post(route('admin.alerts.read-all'))
            ->assertSessionHas('success', 'Alerts dismissed. 1 stock count request(s) still need your approve or reject decision.');

        $this->assertTrue($message->fresh()->is_read);
        $this->assertTrue($expired->fresh()->is_read);
        $this->assertFalse($variance->fresh()->is_read);
        $this->assertEquals(10.0, (float) $item->fresh()->current_stock);
        $this->assertSame(0, AuditLog::whereIn('action', ['stock_adjustment_approved', 'stock_adjustment_rejected'])->count());
    }

    public function test_approval_still_applies_the_count_and_closes_the_request(): void
    {
        [$variance, $item] = $this->varianceRequest();

        $this->actingAs($this->admin())->post(route('admin.inventory.adjustments.approve', $variance))->assertSessionHas('success');

        $this->assertTrue($variance->fresh()->is_read);
        $this->assertEquals(4.0, (float) $item->fresh()->current_stock);
        $this->assertSame(1, AuditLog::where('action', 'stock_adjustment_approved')->count());

        // Once decided, it can be acknowledged like any other alert.
        $this->actingAs($this->admin())->post(route('admin.alerts.read', $variance))->assertSessionHas('success');
    }

    public function test_a_stock_count_request_is_decided_only_once(): void
    {
        [$variance, $item] = $this->varianceRequest();

        $this->actingAs($this->admin())->post(route('admin.inventory.adjustments.reject', $variance))->assertSessionHas('success');
        $this->actingAs($this->admin())->post(route('admin.inventory.adjustments.reject', $variance))->assertSessionHas('warning');
        $this->actingAs($this->admin())->post(route('admin.inventory.adjustments.approve', $variance))->assertSessionHas('warning');

        $this->assertSame(1, AuditLog::where('action', 'stock_adjustment_rejected')->count());
        $this->assertSame(0, AuditLog::where('action', 'stock_adjustment_approved')->count());
        $this->assertEquals(10.0, (float) $item->fresh()->current_stock);
    }

    public function test_staff_cannot_mark_alerts_read_or_decide_requests(): void
    {
        [$variance] = $this->varianceRequest();
        $message = $this->alert('client_message');
        $staff = User::create(['name' => 'Staff', 'email' => 'staff-alerts@example.com', 'password' => bcrypt('password123'), 'role' => 'staff']);

        $this->actingAs($staff)->post(route('admin.alerts.read', $message))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.alerts.read-all'))->assertForbidden();
        $this->actingAs($staff)->post(route('admin.inventory.adjustments.approve', $variance))->assertForbidden();

        $this->assertFalse($message->fresh()->is_read);
        $this->assertFalse($variance->fresh()->is_read);
    }

    public function test_pages_offer_no_dismiss_for_requests_and_confirm_decisions(): void
    {
        [$variance] = $this->varianceRequest();
        $message = $this->alert('client_message');
        $booking = $this->booking('payment_submitted');
        $payment = Payment::create([
            'booking_id' => $booking->id, 'amount' => 9000, 'amount_paid' => 0, 'payment_option' => 'downpayment',
            'payment_type' => 'gcash', 'reference_number' => 'GCASH-ALERT', 'status' => 'pending',
        ]);

        $notifications = $this->actingAs($this->admin())->get(route('admin.notifications'))->assertOk();
        $notifications->assertDontSee('action="' . route('admin.alerts.read', $variance) . '"', false);
        $notifications->assertSee('action="' . route('admin.alerts.read', $message) . '"', false);
        $notifications->assertSee('data-confirm="Verify this payment?"', false);
        $notifications->assertSee('data-confirm="Reject this payment?"', false);
        $notifications->assertSee('data-confirm="Apply this stock count?"', false);
        $notifications->assertSee('data-confirm="Dismiss all alerts?"', false);
        $notifications->assertSee(route('admin.payments.verify', ['payment' => $payment->id]), false);

        $dashboard = $this->actingAs($this->admin())->get(route('admin.dashboard'))->assertOk();
        $dashboard->assertDontSee('action="' . route('admin.alerts.read', $variance) . '"', false);
        $dashboard->assertSee('action="' . route('admin.alerts.read', $message) . '"', false);
    }

    public function test_stock_count_popup_offers_approve_and_reject_not_a_restock_form(): void
    {
        [$variance, $item] = $this->varianceRequest();

        $page = $this->actingAs($this->admin())->get(route('admin.notifications'))->assertOk();
        $page->assertSee('action="' . route('admin.inventory.adjustments.approve', $variance) . '"', false);
        $page->assertSee('action="' . route('admin.inventory.adjustments.reject', $variance) . '"', false);
        $page->assertDontSee('action="' . route('admin.notifications.resolve-shortage', ['inventoryItem' => $item->id]) . '"', false);
    }

    public function test_a_restock_never_closes_a_stock_count_request(): void
    {
        [$variance, $item] = $this->varianceRequest();
        $shortage = AdminAlert::create(['type' => 'inventory_shortage', 'title' => 'Stock alert', 'message' => 'Low', 'inventory_item_id' => $item->id, 'is_read' => false]);

        // Different quantities, so the restock's duplicate-submission guard does not skip the second one.
        foreach ([[$variance, 1], [$shortage, 2]] as [$alert, $quantity]) {
            $this->actingAs($this->admin())->post(route('admin.notifications.resolve-shortage', $item), [
                'additional_stock' => $quantity,
                'alert_id' => $alert->id,
            ]);
        }

        $this->assertFalse($variance->fresh()->is_read);
        $this->assertTrue($shortage->fresh()->is_read);
    }

    private function varianceRequest(): array
    {
        $booking = $this->booking('confirmed');
        $item = InventoryItem::create(['name' => 'Gold Arch', 'category' => 'props', 'current_stock' => 10, 'unit_cost' => 1500, 'min_stock' => 0, 'unit' => 'piece']);
        $alert = AdminAlert::create([
            'type' => 'physical_count_variance',
            'title' => 'Physical Stock Count Variance: Gold Arch',
            'message' => 'Staff Probe recorded an observed physical count of 4 for Gold Arch (system stock: 10, variance: -6) on Booking #' . $booking->id . '.',
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'is_read' => false,
        ]);

        return [$alert, $item];
    }

    private function alert(string $type): AdminAlert
    {
        return AdminAlert::create(['type' => $type, 'title' => ucfirst($type), 'message' => 'Alert body', 'is_read' => false]);
    }

    private function booking(string $status): Booking
    {
        return Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => $status,
            'final_quoted_price' => 18000,
            'total_quoted' => 18000,
        ]);
    }

    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin-alerts@example.com'],
            ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin']
        );
    }
}
