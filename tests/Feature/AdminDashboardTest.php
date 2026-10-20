<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $clientUser;
    private User $staffUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Maria Santos',
            'email' => 'admin@raflora.com',
            'email_verified_at' => now(),
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'name' => 'Client User',
            'email' => 'client@raflora.com',
            'email_verified_at' => now(),
        ]);

        $this->staffUser = User::factory()->create([
            'role' => 'staff',
            'name' => 'Staff User',
            'email' => 'staff@raflora.com',
            'email_verified_at' => now(),
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createPayment(array $attributes = []): Payment
    {
        return Payment::create(array_merge([
            'booking_id' => 1,
            'reference_number' => 'REF-' . uniqid(),
            'amount' => 5000,
            'amount_paid' => 5000,
            'remaining_balance' => 0,
            'status' => 'fully_paid',
            'payment_type' => 'bank_transfer',
            'verified_at' => Carbon::now(),
            'verified_by' => $this->admin->id,
        ], $attributes));
    }

    /**
     * Test 1: Admin dashboard loads successfully with status 200 and operational greeting.
     */
    public function test_admin_dashboard_loads_successfully(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('Welcome back, Maria Santos');
        $response->assertSee('Operational Overview');
        $response->assertSeeText("Here's what's happening with your floral event business today.", false);
        $response->assertSee('Total Bookings');
        $response->assertSee('Collected Revenue');
        $response->assertSee('Active Packages');
        $response->assertSee('Total Clients');
        $response->assertSee('Revenue Overview');
        $response->assertSee('Bookings by Event Type');
        $response->assertSee('Upcoming Events');
        $response->assertSee('Payment Summary');
        $response->assertSee('Top Packages');
        $response->assertSee('Inventory Status');
        $response->assertSee('Recent Bookings');
        $response->assertSee('Low Stock Items');
        // The header has no New Booking button or booking search on the Dashboard.
        $response->assertDontSee('New Booking');
        $response->assertDontSee('id="adminGlobalSearch"', false);

        // Confirm required view data contracts exist
        $response->assertViewHas([
            'bookingsThisMonth',
            'bookingsLastMonth',
            'bookingsGrowth',
            'revenueThisMonth',
            'revenueLastMonth',
            'revenueGrowth',
            'revenueThisYear',
            'revenueAllTime',
            'activePackagesCount',
            'totalClientsCount',
            'clientsGrowth',
            'monthlyRevenueLabels',
            'monthlyRevenueData',
            'eventTypes',
            'eventTypesLabels',
            'eventTypesCounts',
            'upcomingEvents',
            'paymentSummary',
            'topPackages',
            'inventoryStatus',
            'lowStockItems',
            'recentBookings',
            'recentActivities',
            'activeAlerts',
        ]);
    }

    /**
     * Test 2: Non-admin users cannot access the admin dashboard.
     */
    public function test_non_admin_cannot_access_dashboard(): void
    {
        // Unauthenticated guest must be redirected to login
        $guestResponse = $this->get(route('admin.dashboard'));
        $guestResponse->assertRedirect(route('login'));

        // Client role must be denied
        $clientResponse = $this->actingAs($this->clientUser)->get(route('admin.dashboard'));
        $this->assertTrue(in_array($clientResponse->status(), [403, 302]));

        // Staff role must be denied
        $staffResponse = $this->actingAs($this->staffUser)->get(route('admin.dashboard'));
        $this->assertTrue(in_array($staffResponse->status(), [403, 302]));
    }

    /**
     * Test 3: Bookings KPI uses actual Booking data and compares with previous month.
     */
    public function test_bookings_kpi_uses_booking_data_and_compares_with_previous_month(): void
    {
        $now = Carbon::create(2026, 6, 15, 12, 0, 0);
        Carbon::setTestNow($now);

        // 2 bookings last month
        $lastMonth = $now->copy()->subMonth();
        for ($i = 0; $i < 2; $i++) {
            $b = Booking::create([
                'event_type' => 'birthday',
                'event_date' => $lastMonth->toDateString(),
                'status' => 'confirmed',
            ]);
            $b->created_at = $lastMonth->copy()->startOfMonth()->addDays($i);
            $b->saveQuietly();
        }

        // 3 bookings this month
        for ($i = 0; $i < 3; $i++) {
            $b = Booking::create([
                'event_type' => 'wedding',
                'event_date' => $now->toDateString(),
                'status' => 'confirmed',
            ]);
            $b->created_at = $now->copy()->startOfMonth()->addDays($i);
            $b->saveQuietly();
        }

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(3, $response->viewData('bookingsThisMonth'));
        $this->assertSame(2, $response->viewData('bookingsLastMonth'));
        $this->assertSame(50.0, (float) $response->viewData('bookingsGrowth'));
    }

    /**
     * Test 4: Total Clients KPI uses Client model records, NOT User count.
     */
    public function test_client_kpi_uses_client_data_not_user_count(): void
    {
        // There are already 3 User records ($admin, $clientUser, $staffUser)
        $this->assertGreaterThanOrEqual(3, User::count());

        // Create exactly 2 Client records
        Client::create([
            'full_name' => 'Alice Guo',
            'email' => 'alice@example.com',
            'phone' => '09123456789',
        ]);
        Client::create([
            'full_name' => 'Bob Ong',
            'email' => 'bob@example.com',
            'phone' => '09987654321',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // totalClientsCount must equal 2 (Client model count), NOT the user count (>= 3)
        $this->assertSame(2, $response->viewData('totalClientsCount'));
        $this->assertNotSame(User::count(), $response->viewData('totalClientsCount'));
    }

    /**
     * Test 5: Active package KPI excludes inactive and archived packages.
     */
    public function test_active_package_kpi_excludes_inactive_and_archived_packages(): void
    {
        // Active & not archived
        Package::create([
            'title' => 'Active Wedding Package',
            'category' => 'wedding',
            'price' => 50000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        // Inactive
        Package::create([
            'title' => 'Inactive Package',
            'category' => 'birthday',
            'price' => 20000,
            'is_active' => false,
            'is_archived' => false,
        ]);

        // Archived
        Package::create([
            'title' => 'Archived Package',
            'category' => 'corporate',
            'price' => 35000,
            'is_active' => true,
            'is_archived' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('activePackagesCount'));
    }

    /**
     * Test 6: Collected revenue counts verified payment amounts, not quotations.
     */
    public function test_collected_revenue_counts_verified_payment_amounts(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 150000,
            'final_quoted_price' => 140000,
        ]);

        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 40000,
            'amount_paid' => 40000,
            'remaining_balance' => 100000,
            'status' => 'downpayment_received',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
            'payment_type' => 'bank_transfer',
        ]);

        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 100000,
            'amount_paid' => 100000,
            'remaining_balance' => 0,
            'status' => 'fully_paid',
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
            'payment_type' => 'bank_transfer',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // Collected revenue must be 140,000 from amount_paid sum
        $this->assertSame(140000.0, (float) $response->viewData('revenueThisMonth'));
        $this->assertSame(140000.0, (float) $response->viewData('revenueAllTime'));
        // Must NOT use total_quoted (150,000)
        $this->assertNotSame(150000.0, (float) $response->viewData('revenueThisMonth'));
    }

    /**
     * Test 7: Pending and rejected payments are not treated as collected revenue.
     */
    public function test_pending_and_rejected_payments_are_not_treated_as_collected_revenue(): void
    {
        $booking = Booking::create([
            'event_type' => 'debut',
            'event_date' => now()->addDays(7)->toDateString(),
            'status' => 'payment_submitted',
            'total_quoted' => 50000,
        ]);

        // Pending unverified payment
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 25000,
            'amount_paid' => 0,
            'remaining_balance' => 50000,
            'status' => 'pending',
            'verified_at' => null,
            'payment_type' => 'gcash',
        ]);

        // Rejected payment
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 25000,
            'amount_paid' => 0,
            'remaining_balance' => 50000,
            'status' => 'rejected',
            'verified_at' => null,
            'payment_type' => 'gcash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $this->assertSame(0.0, (float) $response->viewData('revenueThisMonth'));
        $this->assertSame(0.0, (float) $response->viewData('revenueAllTime'));
    }

    /**
     * Test 8: Revenue trend uses monthly verified payment data.
     */
    public function test_revenue_trend_uses_monthly_verified_payment_data(): void
    {
        $now = Carbon::create(2026, 6, 15, 12, 0, 0);
        Carbon::setTestNow($now);

        $booking = Booking::create([
            'event_type' => 'corporate',
            'event_date' => $now->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 80000,
        ]);

        // Month 1 (January of current year)
        $janDate = Carbon::create(2026, 1, 15, 12, 0, 0);
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 15000,
            'amount_paid' => 15000,
            'status' => 'fully_paid',
            'verified_at' => $janDate,
            'created_at' => $janDate,
            'payment_type' => 'bank_transfer',
        ]);

        // Month 2 (February of current year)
        $febDate = Carbon::create(2026, 2, 20, 12, 0, 0);
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 25000,
            'amount_paid' => 25000,
            'status' => 'downpayment_received',
            'verified_at' => $febDate,
            'created_at' => $febDate,
            'payment_type' => 'cash',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $monthlyData = $response->viewData('monthlyRevenueData');

        $this->assertSame(15000.0, (float) $monthlyData[0]); // January
        $this->assertSame(25000.0, (float) $monthlyData[1]); // February
    }

    /**
     * Test 9: Event type distribution reflects actual booking records.
     */
    public function test_event_type_distribution_reflects_actual_booking_records(): void
    {
        Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(10)->toDateString(), 'status' => 'confirmed']);
        Booking::create(['event_type' => 'wedding', 'event_date' => now()->addDays(12)->toDateString(), 'status' => 'approved']);
        Booking::create(['event_type' => 'birthday', 'event_date' => now()->addDays(15)->toDateString(), 'status' => 'confirmed']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $eventTypes = $response->viewData('eventTypes');

        $weddingItem = collect($eventTypes)->firstWhere('name', 'Wedding');
        $birthdayItem = collect($eventTypes)->firstWhere('name', 'Birthday');

        $this->assertNotNull($weddingItem);
        $this->assertSame(2, $weddingItem['count']);
        $this->assertNotNull($birthdayItem);
        $this->assertSame(1, $birthdayItem['count']);
    }

    /**
     * Test 10: Cancelled and declined bookings are excluded from event type distribution.
     */
    public function test_cancelled_and_declined_bookings_are_excluded_from_event_types(): void
    {
        Booking::create(['event_type' => 'anniversary', 'event_date' => now()->addDays(5)->toDateString(), 'status' => 'confirmed']);
        Booking::create(['event_type' => 'anniversary', 'event_date' => now()->addDays(6)->toDateString(), 'status' => 'cancelled']);
        Booking::create(['event_type' => 'anniversary', 'event_date' => now()->addDays(7)->toDateString(), 'status' => 'declined']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $eventTypes = $response->viewData('eventTypes');
        $item = collect($eventTypes)->firstWhere('name', 'Anniversary');

        $this->assertNotNull($item);
        $this->assertSame(1, $item['count']);
    }

    /**
     * Test 11: Upcoming events are correctly sorted ascending by event date and time.
     */
    public function test_upcoming_events_are_correctly_sorted_ascending(): void
    {
        $farEvent = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '14:00:00',
            'status' => 'confirmed',
        ]);

        $nearEvent = Booking::create([
            'event_type' => 'birthday',
            'event_date' => now()->addDays(2)->toDateString(),
            'event_time' => '10:00:00',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $upcoming = $response->viewData('upcomingEvents');

        $this->assertCount(2, $upcoming);
        $this->assertSame($nearEvent->id, $upcoming->first()->id);
        $this->assertSame($farEvent->id, $upcoming->last()->id);
    }

    /**
     * Test 12: Upcoming events exclude cancelled and declined records.
     */
    public function test_upcoming_events_exclude_cancelled_and_declined_records(): void
    {
        Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'status' => 'cancelled',
        ]);

        Booking::create([
            'event_type' => 'birthday',
            'event_date' => now()->addDays(4)->toDateString(),
            'status' => 'declined',
        ]);

        $activeBooking = Booking::create([
            'event_type' => 'corporate',
            'event_date' => now()->addDays(5)->toDateString(),
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $upcoming = $response->viewData('upcomingEvents');

        $this->assertCount(1, $upcoming);
        $this->assertSame($activeBooking->id, $upcoming->first()->id);
    }

    /**
     * Test 13: Payment summary values match actual database records.
     */
    public function test_payment_summary_values_match_actual_database_records(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 50000,
            'remaining_balance' => 20000,
        ]);

        // Verified paid
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 30000,
            'amount_paid' => 30000,
            'status' => 'downpayment_received',
            'verified_at' => now(),
            'payment_type' => 'bank_transfer',
        ]);

        // Pending payment
        $this->createPayment([
            'booking_id' => $booking->id,
            'amount' => 10000,
            'amount_paid' => 0,
            'status' => 'pending',
            'verified_at' => null,
            'payment_type' => 'bank_transfer',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $summary = $response->viewData('paymentSummary');

        $this->assertSame(30000.0, (float) $summary['paid']);
        $this->assertSame(10000.0, (float) $summary['pending']);
        $this->assertSame(20000.0, (float) $summary['balance_due']);
    }

    /**
     * Test 14: Top packages reflect actual booking package usage.
     */
    public function test_top_packages_reflect_actual_booking_package_usage(): void
    {
        $pkgA = Package::create(['title' => 'Package Alpha', 'category' => 'wedding', 'price' => 30000, 'is_active' => true]);
        $pkgB = Package::create(['title' => 'Package Beta', 'category' => 'birthday', 'price' => 20000, 'is_active' => true]);

        // Package A booked twice
        Booking::create(['package_id' => $pkgA->id, 'event_type' => 'wedding', 'event_date' => now()->addDays(5)->toDateString(), 'status' => 'confirmed']);
        Booking::create(['package_id' => $pkgA->id, 'event_type' => 'wedding', 'event_date' => now()->addDays(6)->toDateString(), 'status' => 'approved']);

        // Package B booked once
        Booking::create(['package_id' => $pkgB->id, 'event_type' => 'birthday', 'event_date' => now()->addDays(7)->toDateString(), 'status' => 'confirmed']);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $topPackages = $response->viewData('topPackages');

        $this->assertGreaterThanOrEqual(2, $topPackages->count());
        $this->assertSame($pkgA->id, $topPackages->first()->id);
        $this->assertSame(2, $topPackages->first()->bookings_count);
        $this->assertSame($pkgB->id, $topPackages->get(1)->id);
        $this->assertSame(1, $topPackages->get(1)->bookings_count);
    }

    /**
     * Test 15: Inventory status counts match current stock and min_stock rules.
     */
    public function test_inventory_status_counts_match_current_stock_and_min_stock_rules(): void
    {
        // In Stock: current_stock > min_stock
        InventoryItem::create(['name' => 'Rose Red', 'category' => 'flowers', 'current_stock' => 100, 'min_stock' => 20, 'unit' => 'stem', 'unit_cost' => 25]);

        // Low Stock: 0 < current_stock <= min_stock
        InventoryItem::create(['name' => 'Tulip Pink', 'category' => 'flowers', 'current_stock' => 15, 'min_stock' => 20, 'unit' => 'stem', 'unit_cost' => 30]);

        // Out of Stock: current_stock <= 0
        InventoryItem::create(['name' => 'Orchid White', 'category' => 'flowers', 'current_stock' => 0, 'min_stock' => 10, 'unit' => 'stem', 'unit_cost' => 45]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $invStatus = $response->viewData('inventoryStatus');

        $this->assertSame(3, $invStatus['total']);
        $this->assertSame(1, $invStatus['in_stock']);
        $this->assertSame(1, $invStatus['low_stock']);
        $this->assertSame(1, $invStatus['out_of_stock']);
    }

    /**
     * Test 16: Low stock list contains correct urgent items.
     */
    public function test_low_stock_list_contains_correct_items(): void
    {
        $lowItem = InventoryItem::create([
            'name' => 'Floral Foam Brick',
            'category' => 'supplies',
            'current_stock' => 3,
            'min_stock' => 10,
            'unit' => 'piece',
            'unit_cost' => 50,
        ]);

        $healthyItem = InventoryItem::create([
            'name' => 'Satin Ribbon Pink',
            'category' => 'supplies',
            'current_stock' => 50,
            'min_stock' => 10,
            'unit' => 'roll',
            'unit_cost' => 80,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $lowStockItems = $response->viewData('lowStockItems');

        $this->assertTrue($lowStockItems->contains('id', $lowItem->id));
        $this->assertFalse($lowStockItems->contains('id', $healthyItem->id));
    }

    /**
     * Test 17: Active alerts load and unread alerts are displayed.
     */
    public function test_active_alerts_load(): void
    {
        $alert = AdminAlert::create([
            'type' => 'inventory_shortage',
            'title' => 'Low Stock Warning',
            'message' => 'Red Roses are running low.',
            'is_read' => false,
        ]);

        $readAlert = AdminAlert::create([
            'type' => 'quotation_expired',
            'title' => 'Expired Quote',
            'message' => 'A quotation has expired.',
            'is_read' => true,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $alerts = $response->viewData('activeAlerts');

        $this->assertTrue($alerts->contains('id', $alert->id));
        $this->assertFalse($alerts->contains('id', $readAlert->id));
        $response->assertSee('Low Stock Warning');
    }

    /**
     * Test 18: Recent bookings load with client information and formatted amounts.
     */
    public function test_recent_bookings_load(): void
    {
        $client = Client::create([
            'full_name' => 'Maria Clara',
            'email' => 'maria@example.com',
            'phone' => '09123456789',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(14)->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 75000,
            'final_quoted_price' => 70000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $recentBookings = $response->viewData('recentBookings');

        $this->assertTrue($recentBookings->contains('id', $booking->id));
        $response->assertSee('Maria Clara');
        $response->assertSee('₱70,000.00');
    }

    /**
     * Test 19: Recent audit activities load from AuditLog.
     */
    public function test_recent_audit_activities_load(): void
    {
        AuditLog::record(
            $this->admin->id,
            'Package Updated',
            ['package' => 'Grand Floral Setup', 'status' => 'updated'],
            'packages'
        );

        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $activities = $response->viewData('recentActivities');

        $this->assertNotEmpty($activities);
        $this->assertSame('Package Updated', $activities->first()->action);
        $response->assertSee('Package Updated');
    }

    /**
     * Test 20: Dashboard does not expose sensitive data.
     */
    public function test_dashboard_does_not_expose_sensitive_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        // Check that secrets, tokens, or hashed passwords are not leaked
        $content = $response->getContent();
        $this->assertStringNotContainsString('password_hash', $content);
        $this->assertStringNotContainsString('remember_token', $content);
        $this->assertStringNotContainsString('APP_KEY', $content);
    }

    /**
     * Test 21: Existing dashboard backward compatibility view data is preserved.
     */
    public function test_existing_dashboard_backward_compatibility_view_data_is_preserved(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalBookings');
        $response->assertViewHas('pendingBookings');
        $response->assertViewHas('totalUsers');
        $response->assertViewHas('recentBookings');
        $response->assertViewHas('activeAlerts');
    }
}
