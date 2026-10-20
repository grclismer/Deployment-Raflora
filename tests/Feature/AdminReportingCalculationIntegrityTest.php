<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReportingCalculationIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | A. Financial Reporting Integrity Tests
    |--------------------------------------------------------------------------
    */

    public function test_verified_payments_within_period_are_counted_as_revenue(): void
    {
        $booking = $this->createBooking(['status' => 'confirmed', 'total_quoted' => 10000]);

        // Verified payment in current month
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 5000,
            'amount_paid' => 5000,
            'remaining_balance' => 5000,
            'status' => 'downpayment_received',
            'verified_at' => Carbon::now(),
            'verified_by' => $this->admin->id,
            'payment_type' => 'bank_transfer',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(5000.0, (float) $response->viewData('verifiedRevenue'));
        $this->assertSame(5000.0, (float) $response->viewData('revenueEstimate'));
        $response->assertSee('₱5,000.00');
    }

    public function test_unverified_and_rejected_payments_are_excluded_from_revenue(): void
    {
        $booking = $this->createBooking(['status' => 'payment_submitted', 'total_quoted' => 12000]);

        // Pending unverified payment (verified_at is null)
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 6000,
            'amount_paid' => 6000,
            'remaining_balance' => 6000,
            'status' => 'pending',
            'verified_at' => null,
            'payment_type' => 'gcash',
        ]);

        // Rejected payment
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 2000,
            'amount_paid' => 2000,
            'remaining_balance' => 10000,
            'status' => 'rejected',
            'verified_at' => Carbon::now(),
            'payment_type' => 'gcash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(0.0, (float) $response->viewData('verifiedRevenue'));
    }

    public function test_quotation_values_are_not_counted_as_collected_revenue(): void
    {
        // Booking with high quotation value but no payments
        $this->createBooking([
            'status' => 'confirmed',
            'total_quoted' => 85000,
            'final_quoted_price' => 85000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        // Verified revenue must be 0, not 85,000
        $this->assertSame(0.0, (float) $response->viewData('verifiedRevenue'));
        // Average booking value should reflect contract value
        $this->assertSame(85000.0, (float) $response->viewData('averageBookingValue'));
    }

    public function test_pipeline_value_remains_distinct_from_verified_revenue(): void
    {
        // Booking in quotation_sent stage
        $this->createBooking([
            'status' => 'quotation_sent',
            'total_quoted' => 25000,
        ]);

        // Booking with partial payment
        $confirmedBooking = $this->createBooking([
            'status' => 'downpayment_received',
            'total_quoted' => 40000,
        ]);

        $this->createPayment([
            'booking_id' => $confirmedBooking->id,
            'amount' => 20000,
            'amount_paid' => 20000,
            'remaining_balance' => 20000,
            'status' => 'downpayment_received',
            'verified_at' => Carbon::now(),
            'verified_by' => $this->admin->id,
            'payment_type' => 'bank_transfer',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        // Verified cash collected: 20,000
        $this->assertSame(20000.0, (float) $response->viewData('verifiedRevenue'));
        // Pipeline: total quoted for active prospective bookings (25,000 + 40,000 = 65,000)
        $this->assertSame(65000.0, (float) $response->viewData('pipelineValue'));
    }

    public function test_multiple_verified_payments_aggregate_correctly_across_different_bookings(): void
    {
        $booking1 = $this->createBooking(['status' => 'confirmed']);
        $booking2 = $this->createBooking(['status' => 'completed']);

        $this->createPayment([
            'booking_id' => $booking1->id,
            'amount' => 15000,
            'amount_paid' => 15000,
            'status' => 'verified',
            'verified_at' => Carbon::now(),
            'payment_type' => 'cash',
        ]);

        $this->createPayment([
            'booking_id' => $booking2->id,
            'amount' => 30000,
            'amount_paid' => 30000,
            'status' => 'fully_paid',
            'verified_at' => Carbon::now(),
            'payment_type' => 'bank_transfer',
        ]);

        // Payment verified in a previous month (should not be in monthly revenue)
        $this->createPayment([
            'booking_id' => $booking2->id,
            'amount' => 10000,
            'amount_paid' => 10000,
            'status' => 'fully_paid',
            'verified_at' => Carbon::now()->subMonths(2),
            'payment_type' => 'cash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(45000.0, (float) $response->viewData('verifiedRevenue'));
    }

    public function test_empty_payment_data_handles_safely(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(0.0, (float) $response->viewData('verifiedRevenue'));
        $this->assertSame(0.0, (float) $response->viewData('averageBookingValue'));
        $this->assertSame(0, (int) $response->viewData('confirmedThisMonth'));
        $this->assertSame(0, (int) $response->viewData('bookingsThisMonth'));
    }

    /*
    |--------------------------------------------------------------------------
    | B. Booking Metrics and Status Classifications
    |--------------------------------------------------------------------------
    */

    public function test_quotation_sent_is_not_counted_as_confirmed(): void
    {
        $this->createBooking(['status' => 'quotation_sent']);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(1, (int) $response->viewData('bookingsThisMonth'));
        $this->assertSame(0, (int) $response->viewData('confirmedThisMonth'));
    }

    public function test_active_operational_statuses_are_counted_in_confirmed_metrics(): void
    {
        $operationalStatuses = [
            'downpayment_received',
            'confirmed',
            'in_preparation',
            'event_in_progress',
            'event_completed',
            'completed',
            'pending_return',
            'pending_resolution',
        ];

        foreach ($operationalStatuses as $status) {
            $this->createBooking([
                'status' => $status,
                'total_quoted' => 1000,
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(count($operationalStatuses), (int) $response->viewData('confirmedThisMonth'));
    }

    public function test_cancelled_and_declined_bookings_are_excluded_from_confirmed_and_pipeline(): void
    {
        $this->createBooking(['status' => 'cancelled', 'total_quoted' => 50000]);
        $this->createBooking(['status' => 'declined', 'total_quoted' => 30000]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(2, (int) $response->viewData('bookingsThisMonth'));
        $this->assertSame(0, (int) $response->viewData('confirmedThisMonth'));
        $this->assertSame(0.0, (float) $response->viewData('pipelineValue'));
    }

    public function test_report_view_renders_accurate_metric_labels(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $response->assertSee('Verified revenue');
        $response->assertSee('Verified payments collected this month');
        $response->assertSee('confirmed or active this month');
        $response->assertSee('Based on confirmed monthly bookings');
        $response->assertSee('Top confirmed materials by total quantity.');
    }

    /*
    |--------------------------------------------------------------------------
    | C. Admin Dashboard Pending-Action Counter
    |--------------------------------------------------------------------------
    */

    public function test_dashboard_pending_count_includes_all_actionable_statuses(): void
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
            $this->createBooking(['status' => $status]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertSuccessful();
        $this->assertSame(count($actionableStatuses), (int) $response->viewData('pendingBookings'));
    }

    public function test_dashboard_pending_count_excludes_operational_and_terminal_statuses(): void
    {
        // Operational bookings (already confirmed/in execution)
        $this->createBooking(['status' => 'confirmed']);
        $this->createBooking(['status' => 'in_preparation']);
        $this->createBooking(['status' => 'completed']);

        // Terminal bookings
        $this->createBooking(['status' => 'cancelled']);
        $this->createBooking(['status' => 'declined']);

        // Only 1 actionable booking
        $this->createBooking(['status' => 'pending']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertSuccessful();
        $this->assertSame(1, (int) $response->viewData('pendingBookings'));
    }

    /*
    |--------------------------------------------------------------------------
    | D. Material Demand Aggregation
    |--------------------------------------------------------------------------
    */

    public function test_material_demand_excludes_unconfirmed_inquiry_items(): void
    {
        $booking = $this->createBooking(['status' => 'pending']);
        $item = $this->createInventoryItem('Unconfirmed Rose');

        // Unconfirmed booking item (confirmed_at is null)
        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 100,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
            'confirmed_at' => null,
            'procurement_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertEmpty($response->viewData('materialLabels'));
        $this->assertEmpty($response->viewData('materialData'));
    }

    public function test_material_demand_includes_confirmed_items_on_active_bookings(): void
    {
        $booking = $this->createBooking(['status' => 'confirmed']);
        $item = $this->createInventoryItem('Confirmed Lily');

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 45,
            'quoted_unit_price' => 75,
            'confirmed_at' => Carbon::now(),
            'procurement_status' => 'pending',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertSame(['Confirmed Lily'], $response->viewData('materialLabels'));
        $this->assertSame([45.0], $response->viewData('materialData'));
    }

    public function test_material_demand_excludes_items_on_cancelled_or_declined_bookings(): void
    {
        $cancelledBooking = $this->createBooking(['status' => 'cancelled']);
        $declinedBooking = $this->createBooking(['status' => 'declined']);
        $item = $this->createInventoryItem('Cancelled Orchid');

        BookingItem::create([
            'booking_id' => $cancelledBooking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 60,
            'confirmed_at' => Carbon::now(),
        ]);

        BookingItem::create([
            'booking_id' => $declinedBooking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 40,
            'confirmed_at' => Carbon::now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertEmpty($response->viewData('materialLabels'));
    }

    public function test_material_demand_excludes_soft_deleted_inventory_items(): void
    {
        $booking = $this->createBooking(['status' => 'confirmed']);
        $item = $this->createInventoryItem('Deleted Vase');

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 15,
            'confirmed_at' => Carbon::now(),
        ]);

        // Soft-delete the inventory item
        $item->delete();

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));

        $response->assertSuccessful();
        $this->assertEmpty($response->viewData('materialLabels'));
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    private function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'pending',
            'total_quoted' => 5000,
            'final_quoted_price' => 0,
        ], $attributes));
    }

    private function createInventoryItem(string $name, float $stock = 20): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => $stock,
            'unit_cost' => 50,
            'min_stock' => 5,
            'unit' => 'stem',
        ]);
    }

    private function createPayment(array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'booking_id' => 1,
            'amount' => 5000,
            'amount_paid' => 5000,
            'remaining_balance' => 0,
            'payment_type' => 'bank_transfer',
            'status' => 'verified',
            'reference_number' => 'REF-' . uniqid(),
            'verified_at' => Carbon::now(),
            'verified_by' => $this->admin->id,
        ], $attributes));
    }
}

