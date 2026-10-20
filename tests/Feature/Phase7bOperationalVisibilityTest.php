<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase7bOperationalVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Officer',
            'email' => 'admin@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);
    }

    /**
     * Requirement 1 (NAV-01):
     * Verify quotation page displays registered client name via booking relationship,
     * retains guest fallback, and queries both 'pending' and 'issued' quotations.
     */
    public function test_quotation_page_displays_registered_client_name_and_guest_fallback_for_active_quotations(): void
    {
        $client = Client::create([
            'full_name' => 'Maria Clara de los Santos',
            'email' => 'maria@example.test',
            'phone' => '09123456789',
        ]);

        $registeredBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Wedding Gala',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'quotation_sent',
            'total_quoted' => 35000,
        ]);

        $issuedQuote = Quotation::create([
            'booking_id' => $registeredBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 35000,
            'recommended_price' => 35000,
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $guestBooking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Juan Luna (Guest)',
            'guest_email' => 'juan@example.test',
            'event_type' => 'Birthday Party',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Clubhouse',
            'status' => 'pending',
            'total_quoted' => 15000,
        ]);

        $pendingQuote = Quotation::create([
            'booking_id' => $guestBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'recommended_price' => 15000,
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);

        // Terminal/accepted quotation should NOT appear in pending/issued queue
        $acceptedBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Anniversary Dinner',
            'event_date' => now()->addDays(30)->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 20000,
        ]);

        Quotation::create([
            'booking_id' => $acceptedBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 20000,
            'valid_until' => now()->addDays(7)->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations'));

        $response->assertOk();
        // 1. Registered client name is properly retrieved and displayed
        $response->assertSee('Maria Clara de los Santos');
        // 2. Guest fallback is displayed
        $response->assertSee('Juan Luna (Guest)');
        // 3. Both issued and pending quotations appear
        $response->assertSee('Wedding Gala');
        $response->assertSee('Birthday Party');
        // 4. Accepted quotation is excluded
        $response->assertDontSee('Anniversary Dinner');
        // 5. Correct prices are formatted
        $response->assertSee('35,000.00');
        $response->assertSee('15,000.00');
    }

    /**
     * Requirement 2 (NAV-02):
     * Verify dashboard quick actions have distinct destinations:
     * Card 1 -> admin.bookings, Card 2 -> admin.return-tracking.
     */
    public function test_dashboard_quick_actions_have_distinct_destinations(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // Card 1: Review Bookings
        $response->assertSee('Review Bookings');
        $response->assertSee(route('admin.bookings'));

        // Card 2: Return Tracking (retasked from duplicate admin.bookings)
        $response->assertSee('Return Tracking');
        $response->assertSee(route('admin.return-tracking'));
        $response->assertSee('Review post-event asset returns, assess damages, and reconcile warehouse stock.');
    }

    /**
     * Requirement 3 (VIS-01):
     * Verify dashboard actionable count includes admin_approved, pending_return,
     * and pending_resolution in addition to previous actionable statuses.
     */
    public function test_dashboard_actionable_count_includes_returns_and_admin_approved(): void
    {
        $actionableStatuses = [
            'pending',
            'quotation_sent',
            'payment_submitted',
            'payment_pending',
            'approved',
            'admin_approved',
            'change_requested',
            'cancellation_requested',
            'pending_return',
            'pending_resolution',
        ];

        foreach ($actionableStatuses as $status) {
            Booking::create([
                'event_type' => 'Event ' . $status,
                'event_date' => now()->addDays(5)->toDateString(),
                'status' => $status,
                'total_quoted' => 10000,
            ]);
        }

        // Non-actionable statuses should not be counted
        Booking::create(['event_type' => 'Confirmed Event', 'event_date' => now()->toDateString(), 'status' => 'confirmed']);
        Booking::create(['event_type' => 'In Prep Event', 'event_date' => now()->toDateString(), 'status' => 'in_preparation']);
        Booking::create(['event_type' => 'Completed Event', 'event_date' => now()->toDateString(), 'status' => 'completed']);
        Booking::create(['event_type' => 'Cancelled Event', 'event_date' => now()->toDateString(), 'status' => 'cancelled']);
        Booking::create(['event_type' => 'Declined Event', 'event_date' => now()->toDateString(), 'status' => 'declined']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(count($actionableStatuses), (int) $response->viewData('pendingBookings'));
    }

    /**
     * Requirement 4 (VIS-02):
     * Verify sidebar notification badge reflects both persistent unread alerts
     * and dynamic pending payment verifications without double counting.
     */
    public function test_sidebar_badge_counts_both_persistent_alerts_and_pending_payments(): void
    {
        // 1 persistent unread alert
        AdminAlert::create([
            'type' => 'quotation_expired',
            'title' => 'Quotation Expired',
            'message' => 'Quotation has expired.',
            'is_read' => false,
        ]);

        // 1 read persistent alert (should not be counted)
        AdminAlert::create([
            'type' => 'quotation_expired',
            'title' => 'Read Alert',
            'message' => 'Already read alert.',
            'is_read' => true,
        ]);

        // 2 bookings requiring payment review
        Booking::create([
            'event_type' => 'Payment Sub 1',
            'event_date' => now()->addDays(5)->toDateString(),
            'status' => 'payment_submitted',
        ]);
        Booking::create([
            'event_type' => 'Payment Sub 2',
            'event_date' => now()->addDays(6)->toDateString(),
            'status' => 'payment_pending',
        ]);

        // Expected badge count: 1 unread AdminAlert + 2 pending payment bookings = 3
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // The badge renders: <span ...>3</span>
        $response->assertSee('min-w-6 items-center justify-center rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-semibold text-white">3</span>', false);
    }

    /**
     * Requirement 5 (VIS-03):
     * Verify cancelled bookings expose the Return Audit shortcut only when qualifying
     * dispatch transactions or return records exist.
     */
    public function test_cancelled_booking_exposes_return_audit_action_when_dispatches_exist(): void
    {
        $hardwareItem = InventoryItem::create([
            'name' => 'Arbor Stand',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 5,
            'unit_cost' => 1500,
            'unit' => 'piece',
        ]);

        // Case A: Cancelled booking WITH dispatched hardware
        $cancelledWithDispatch = Booking::create([
            'event_type' => 'Cancelled With Dispatch',
            'event_date' => now()->toDateString(),
            'status' => 'cancelled',
            'total_quoted' => 10000,
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $hardwareItem->id,
            'booking_id' => $cancelledWithDispatch->id,
            'transaction_type' => 'dispatch',
            'quantity_change' => -2,
            'previous_stock' => 5,
            'new_stock' => 3,
            'performed_by' => $this->admin->id,
            'notes' => 'Dispatched prior to cancellation',
        ]);

        $responseA = $this->actingAs($this->admin)->get(route('admin.bookings.show', $cancelledWithDispatch));
        $responseA->assertOk();
        $responseA->assertSee('Manage Return Audit');
        $responseA->assertSee(route('admin.return-tracking.manage', $cancelledWithDispatch));

        // Case B: Cancelled booking WITH existing return record (even without active tx query)
        $cancelledWithReturn = Booking::create([
            'event_type' => 'Cancelled With Return Record',
            'event_date' => now()->toDateString(),
            'status' => 'cancelled',
            'total_quoted' => 10000,
        ]);

        AssetReturn::create([
            'booking_id' => $cancelledWithReturn->id,
            'status' => 'Pending',
            'total_damage_charge' => 0,
        ]);

        $responseB = $this->actingAs($this->admin)->get(route('admin.bookings.show', $cancelledWithReturn));
        $responseB->assertOk();
        $responseB->assertSee('Manage Return Audit');

        // Case C: Cancelled booking WITHOUT dispatches and WITHOUT return record
        $ordinaryCancelled = Booking::create([
            'event_type' => 'Ordinary Cancelled Booking',
            'event_date' => now()->toDateString(),
            'status' => 'cancelled',
            'total_quoted' => 10000,
        ]);

        $responseC = $this->actingAs($this->admin)->get(route('admin.bookings.show', $ordinaryCancelled));
        $responseC->assertOk();
        $responseC->assertDontSee('Manage Return Audit');
    }
}
