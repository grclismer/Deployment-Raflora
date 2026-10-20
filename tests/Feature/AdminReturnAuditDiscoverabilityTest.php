<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReturnAuditDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $clientUser;
    protected Client $clientRecord;
    protected InventoryItem $hardwareItem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->staff = User::factory()->create(['role' => 'staff']);
        $this->clientUser = User::factory()->create(['role' => 'client']);

        $this->clientRecord = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);

        $this->hardwareItem = InventoryItem::create([
            'name' => 'Rustic Backdrop Arch',
            'category' => 'arch',
            'is_perishable' => false,
            'current_stock' => 5,
            'unit_cost' => 500,
            'unit' => 'piece',
            'min_stock' => 1,
        ]);
    }

    /**
     * Helper to create a booking with dispatch.
     */
    protected function createBookingWithDispatch(string $status, float $quote = 5000, float $dispatchedQty = 2): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'venue' => 'Sunset Gardens',
            'status' => $status,
            'total_quoted' => $quote,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $this->hardwareItem->id,
            'item_name' => $this->hardwareItem->name,
            'quantity' => $dispatchedQty,
            'quoted_unit_price' => 500,
            'confirmed_at' => now(),
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $this->hardwareItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$dispatchedQty,
            'transaction_type' => 'dispatch',
            'reason' => 'Event Dispatch',
            'performed_by' => $this->admin->id,
        ]);

        return $booking;
    }

    /**
     * 1. Balance-pending event_completed booking exposes Manage Return Audit in queue.
     */
    public function test_balance_pending_event_completed_booking_exposes_manage_return_audit_in_queue(): void
    {
        $booking = $this->createBookingWithDispatch('event_completed', 5000, 2);

        // Verify remaining balance is pending (> 0)
        $this->assertGreaterThan(0, $booking->remaining_balance);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));
        $response->assertOk();

        // Must see Manage Return Audit link
        $response->assertSee(route('admin.return-tracking.manage', ['booking' => $booking->id]));
        $response->assertSee('Manage Return Audit');

        // Must also see balance pending badge and final payment action
        $response->assertSee('Event Completed (Balance Pending)');
        $response->assertSee('Log Final Payment');
    }

    /**
     * 2. pending_return and pending_resolution remain accessible in queue.
     */
    public function test_pending_return_and_pending_resolution_remain_accessible_in_queue(): void
    {
        $bookingReturn = $this->createBookingWithDispatch('pending_return', 5000, 2);
        $bookingResolution = $this->createBookingWithDispatch('pending_resolution', 5000, 2);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));
        $response->assertOk();

        $response->assertSee(route('admin.return-tracking.manage', ['booking' => $bookingReturn->id]));
        $response->assertSee(route('admin.return-tracking.manage', ['booking' => $bookingResolution->id]));
    }

    /**
     * 3. Completed booking exposes historical View Return Audit in queue.
     */
    public function test_completed_booking_exposes_historical_view_return_audit_in_queue(): void
    {
        $booking = $this->createBookingWithDispatch('completed', 5000, 2);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));
        $response->assertOk();

        $response->assertSee('Completed &amp; Fully Paid', false);
        $response->assertSee(route('admin.return-tracking.manage', ['booking' => $booking->id]));
        $response->assertSee('View Return Audit');
    }

    /**
     * 4. Cancelled booking with dispatched materials retains recovery access in queue.
     */
    public function test_cancelled_booking_with_dispatched_materials_retains_recovery_access_in_queue(): void
    {
        $cancelledWithDispatch = $this->createBookingWithDispatch('cancelled', 5000, 2);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));
        $response->assertOk();

        $response->assertSee(route('admin.return-tracking.manage', ['booking' => $cancelledWithDispatch->id]));
        $response->assertSee('Manage Return Audit');
    }

    /**
     * 5. Cancelled booking without eligible dispatch does not receive return action.
     */
    public function test_cancelled_booking_without_eligible_dispatch_does_not_receive_return_action(): void
    {
        $cancelledWithoutDispatch = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'venue' => 'No Dispatch Venue',
            'status' => 'cancelled',
            'total_quoted' => 5000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));
        $response->assertOk();

        $response->assertDontSee(route('admin.return-tracking.manage', ['booking' => $cancelledWithoutDispatch->id]));
    }

    /**
     * 6. Booking detail exposes compact, status-aware Return Audit action in operational header.
     */
    public function test_booking_detail_exposes_status_aware_return_audit_action_in_header(): void
    {
        // Active return booking
        $activeBooking = $this->createBookingWithDispatch('event_completed', 5000, 2);
        $activeResponse = $this->actingAs($this->admin)->get(route('admin.bookings.show', $activeBooking));
        $activeResponse->assertOk();
        $activeResponse->assertSee(route('admin.return-tracking.manage', $activeBooking));
        $activeResponse->assertSee('Manage Return Audit');

        // Completed booking
        $completedBooking = $this->createBookingWithDispatch('completed', 5000, 2);
        $completedBooking->returns()->create([
            'status' => 'Completed',
            'return_date' => now()->toDateString(),
            'notes' => 'All items returned in good condition',
        ]);
        $completedResponse = $this->actingAs($this->admin)->get(route('admin.bookings.show', $completedBooking));
        $completedResponse->assertOk();
        $completedResponse->assertSee(route('admin.return-tracking.manage', $completedBooking));
        $completedResponse->assertSee('View Return Audit');
    }

    /**
     * 7. Premature booking statuses cannot initialize return records (rejects and redirects).
     */
    public function test_premature_booking_statuses_cannot_initialize_return_records(): void
    {
        $prematureStatuses = ['pending', 'quotation_sent', 'approved', 'confirmed', 'downpayment_received', 'in_preparation', 'event_in_progress'];

        foreach ($prematureStatuses as $status) {
            $booking = Booking::create([
                'client_id' => $this->clientRecord->id,
                'event_type' => 'wedding',
                'event_date' => now()->addDays(10)->toDateString(),
                'status' => $status,
                'total_quoted' => 5000,
            ]);

            $response = $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $booking));
            $response->assertRedirect(route('admin.bookings'));
            $response->assertSessionHas('error');

            // Must NOT have created an AssetReturn record
            $this->assertDatabaseMissing('returns', [
                'booking_id' => $booking->id,
            ]);
        }
    }

    /**
     * 8. Completed audit cannot be mutated through direct PUT endpoint.
     */
    public function test_completed_audit_cannot_be_mutated_through_direct_endpoint(): void
    {
        $booking = $this->createBookingWithDispatch('completed', 5000, 2);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Completed',
            'total_damage_charge' => 0,
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $this->hardwareItem->id,
            'quantity_returned' => 2,
            'quantity_good' => 2,
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
            'condition' => 'good',
            'damage_charge' => 0,
            'charge_decision' => 'no_charge',
        ]);

        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 1,
                    'quantity_damaged' => 1,
                    'damage_charge' => 250,
                    'charge_decision' => 'charge',
                ],
            ],
        ]);

        $response->assertSessionHas('error', 'This return audit is completed and archived. Completed return records cannot be modified.');

        // Verify returnItem remains untouched
        $returnItem->refresh();
        $this->assertSame(2.0, (float) $returnItem->quantity_good);
        $this->assertSame(0.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(0.0, (float) $returnItem->damage_charge);
    }

    /**
     * 9. Completed return audit view displays archived banner, disables inputs, and omits submit button.
     */
    public function test_completed_return_audit_view_is_read_only(): void
    {
        $booking = $this->createBookingWithDispatch('completed', 5000, 2);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Completed',
            'total_damage_charge' => 0,
        ]);

        ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $this->hardwareItem->id,
            'quantity_returned' => 2,
            'quantity_good' => 2,
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
            'condition' => 'good',
            'damage_charge' => 0,
            'charge_decision' => 'no_charge',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $booking));
        $response->assertOk();

        // Archived banner present
        $response->assertSee('Return Audit Completed &amp; Archived', false);
        $response->assertSee('This return audit record is finalized and locked.', false);

        // Disabled controls present
        $response->assertSee('disabled', false);

        // Submit button omitted
        $response->assertDontSee('Save Return Audit');
        $response->assertSee('Back to Booking Review');
    }

    /**
     * 10. Staff and Client cannot access admin return management.
     */
    public function test_staff_and_client_cannot_access_admin_return_management(): void
    {
        $booking = $this->createBookingWithDispatch('event_completed', 5000, 2);

        // Staff access blocked
        $staffResponse = $this->actingAs($this->staff)->get(route('admin.return-tracking.manage', $booking));
        $staffResponse->assertForbidden();

        // Client access blocked
        $clientResponse = $this->actingAs($this->clientUser)->get(route('admin.return-tracking.manage', $booking));
        $clientResponse->assertForbidden();
    }

    /**
     * 11. Verification that handleCompletedBooking is removed and finalPayment completes cleanly.
     */
    public function test_final_payment_completion_works_without_legacy_helper(): void
    {
        // Confirm method no longer exists on controller
        $this->assertFalse(method_exists(\App\Http\Controllers\Admin\BookingController::class, 'handleCompletedBooking'));

        // Confirm active final payment works cleanly and sets completed
        $booking = $this->createBookingWithDispatch('event_completed', 2000, 2);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Completed',
            'total_damage_charge' => 0,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.final_payment', $booking), [
            'amount_received' => 2000,
            'payment_type' => 'cash',
        ]);

        $response->assertRedirect();
        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertSame(0.0, (float) $booking->fresh()->remaining_balance);
    }
}
