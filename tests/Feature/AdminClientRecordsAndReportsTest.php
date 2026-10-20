<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Payment;
use App\Models\User;
use App\Services\SystemData\DemoDatasetGenerator;
use App\Services\SystemData\SystemDataImporter;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminClientRecordsAndReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Raflora Admin',
            'first_name' => 'Raflora',
            'last_name' => 'Admin',
            'email' => 'admin@raflora.com',
            'password' => bcrypt('AdminPassword123!'),
            'role' => 'admin',
            'is_bootstrap' => false,
        ]);

        $this->staff = User::create([
            'name' => 'Raflora Staff',
            'first_name' => 'Maria',
            'last_name' => 'Staff',
            'email' => 'staff@raflora.com',
            'password' => bcrypt('StaffPassword123!'),
            'role' => 'staff',
            'is_bootstrap' => false,
        ]);
    }

    // =========================================================================
    // CLIENT RECORDS TESTS
    // =========================================================================

    public function test_client_with_owned_bookings_displays_correct_count_and_activity(): void
    {
        $clientA = Client::create([
            'full_name' => 'Client With Three Bookings',
            'email' => 'client.a@raflora.test',
            'phone' => '09171234567',
        ]);

        $clientB = Client::create([
            'full_name' => 'Client With No Bookings',
            'email' => 'client.b@raflora.test',
            'phone' => '09181234567',
        ]);

        // Create 3 bookings owned by Client A, handled by Staff
        $b1 = Booking::create([
            'client_id' => $clientA->id,
            'handled_by' => $this->staff->id,
            'event_type' => 'wedding',
            'event_date' => '2026-11-10',
            'venue' => 'Venue A',
            'status' => 'pending',
        ]);
        $b1->timestamps = false;
        $b1->created_at = Carbon::parse('2026-10-01 10:00:00');
        $b1->save();

        $b2 = Booking::create([
            'client_id' => $clientA->id,
            'handled_by' => $this->admin->id,
            'event_type' => 'birthday',
            'event_date' => '2026-11-15',
            'venue' => 'Venue B',
            'status' => 'quotation_sent',
        ]);
        $b2->timestamps = false;
        $b2->created_at = Carbon::parse('2026-10-03 14:00:00');
        $b2->save();

        $b3 = Booking::create([
            'client_id' => $clientA->id,
            'handled_by' => $this->staff->id,
            'event_type' => 'corporate',
            'event_date' => '2026-11-20',
            'venue' => 'Venue C',
            'status' => 'confirmed',
        ]);
        $b3->timestamps = false;
        $b3->created_at = Carbon::parse('2026-10-07 16:30:00');
        $b3->save();

        $response = $this->actingAs($this->admin)->get(route('admin.client-records'));

        $response->assertOk();
        $response->assertSee('Client Records');
        $response->assertSee('Client With Three Bookings');
        $response->assertSee('Client With No Bookings');

        // Check client records data passed to view
        $viewClients = $response->viewData('clients');
        $this->assertCount(2, $viewClients);

        $recordA = $viewClients->firstWhere('id', $clientA->id);
        $recordB = $viewClients->firstWhere('id', $clientB->id);

        $this->assertNotNull($recordA);
        $this->assertNotNull($recordB);

        // 1. Client with 3 owned bookings displays count 3
        $this->assertSame(3, $recordA->bookings_count);
        // 2. Client with no owned bookings displays count 0
        $this->assertSame(0, $recordB->bookings_count);

        // 5. Last activity is based on latest created booking
        $this->assertSame('Oct 07, 2026', $recordA->bookings->first()->created_at->format('M d, Y'));
        $this->assertNull($recordB->bookings->first());

        // Check rendered HTML
        $response->assertSee('Oct 07, 2026');
        $response->assertSee('No activity');
    }

    public function test_booking_handled_by_staff_or_admin_does_not_count_as_staff_client_booking(): void
    {
        // Create client user and client record
        $clientUser = User::create([
            'name' => 'Registered Client User',
            'first_name' => 'Registered',
            'last_name' => 'Client',
            'email' => 'registered.client@raflora.test',
            'password' => bcrypt('Password123!'),
            'role' => 'client',
        ]);

        $clientRecord = Client::create([
            'full_name' => 'Registered Client User',
            'email' => 'registered.client@raflora.test',
        ]);

        // Booking owned by clientRecord, but handled by staff user
        Booking::create([
            'client_id' => $clientRecord->id,
            'handled_by' => $this->staff->id,
            'event_type' => 'wedding',
            'event_date' => '2026-12-01',
            'venue' => 'Grand Ballroom',
            'status' => 'pending',
        ]);

        // Staff user handled 1 booking
        $this->assertSame(1, $this->staff->bookings()->count());

        // Client Records controller must report:
        // ClientRecord has 1 booking based on client ownership (client_id)
        $response = $this->actingAs($this->admin)->get(route('admin.client-records'));
        $response->assertOk();

        $viewClients = $response->viewData('clients');
        $clientRow = $viewClients->firstWhere('email', 'registered.client@raflora.test');
        $this->assertNotNull($clientRow);
        $this->assertSame(1, $clientRow->bookings_count);

        // Staff user does NOT appear as a client in Client Records
        $staffRow = $viewClients->firstWhere('email', 'staff@raflora.com');
        $this->assertNull($staffRow);
    }

    public function test_imported_demo_clients_and_bookings_resolve_correctly_in_client_records(): void
    {
        $generator = new DemoDatasetGenerator();
        $zipPath = $generator->generate();

        $importer = new SystemDataImporter();
        $result = $importer->import($zipPath);
        $this->assertTrue($result['success']);
        @unlink($zipPath);

        $response = $this->actingAs($this->admin)->get(route('admin.client-records'));
        $response->assertOk();

        $viewClients = $response->viewData('clients');
        $this->assertGreaterThanOrEqual(4, $viewClients->count());

        // Verify Maria Clara Santos
        $clara = $viewClients->firstWhere('email', 'clara.santos@demo.com');
        $this->assertNotNull($clara);
        $this->assertSame('Maria Clara Santos', $clara->full_name);
        $this->assertGreaterThanOrEqual(2, $clara->bookings_count);
        $this->assertNotNull($clara->bookings->first());

        // Verify Roberto Gomez
        $roberto = $viewClients->firstWhere('email', 'roberto.gomez@demo.com');
        $this->assertNotNull($roberto);
        $this->assertSame('Roberto Gomez', $roberto->full_name);
        $this->assertGreaterThanOrEqual(2, $roberto->bookings_count);
        $this->assertNotNull($roberto->bookings->first());

        // Verify Beatrice Tan
        $beatrice = $viewClients->firstWhere('email', 'beatrice.tan@demo.com');
        $this->assertNotNull($beatrice);
        $this->assertSame('Beatrice Tan', $beatrice->full_name);
        $this->assertGreaterThanOrEqual(2, $beatrice->bookings_count);
        $this->assertNotNull($beatrice->bookings->first());

        // In HTML, verify names and booking counts are rendered
        $response->assertSee('Maria Clara Santos');
        $response->assertSee('Roberto Gomez');
        $response->assertSee('Beatrice Tan');
    }

    public function test_unauthorized_users_cannot_access_client_records(): void
    {
        // Unauthenticated
        $this->get(route('admin.client-records'))->assertRedirect(route('login'));

        // Staff user
        $this->actingAs($this->staff)->get(route('admin.client-records'))->assertForbidden();
    }

    // =========================================================================
    // DASHBOARD & REPORTS AUDIT TESTS
    // =========================================================================

    public function test_dashboard_and_reports_metrics_match_database_truth(): void
    {
        $generator = new DemoDatasetGenerator();
        $zipPath = $generator->generate();

        $importer = new SystemDataImporter();
        $result = $importer->import($zipPath);
        $this->assertTrue($result['success']);
        @unlink($zipPath);

        // 1. Dashboard Total Bookings
        $totalBookingsInDb = Booking::count();
        $dashboardResponse = $this->actingAs($this->admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertOk();
        $this->assertSame($totalBookingsInDb, $dashboardResponse->viewData('totalBookings'));

        // 2. Reports Metrics
        $reportsResponse = $this->actingAs($this->admin)->get(route('admin.reports'));
        $reportsResponse->assertOk();

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        // New Bookings this month
        $expectedNewBookings = Booking::whereBetween('created_at', [$startOfMonth, $endOfMonth])->count();
        $this->assertSame($expectedNewBookings, $reportsResponse->viewData('bookingsThisMonth'));

        // Verified Revenue this month
        $expectedRevenue = (float) Payment::whereNotNull('verified_at')
            ->whereIn('status', ['verified', 'fully_paid', 'downpayment_received'])
            ->whereBetween('verified_at', [$startOfMonth, $endOfMonth])
            ->sum('amount_paid');
        $this->assertEqualsWithDelta($expectedRevenue, $reportsResponse->viewData('verifiedRevenue'), 0.01);

        // Stock Exceptions
        $expectedStockAlerts = InventoryItem::whereColumn('current_stock', '<=', 'min_stock')->count();
        $this->assertSame($expectedStockAlerts, $reportsResponse->viewData('stockAlerts'));

        // Booking Pipeline status distribution total matches total bookings
        $statusData = $reportsResponse->viewData('statusData');
        $this->assertSame($totalBookingsInDb, array_sum($statusData));
    }

    // =========================================================================
    // SEARCH, FILTER, AND SORTING TESTS
    // =========================================================================

    public function test_client_records_search_by_name_email_and_phone(): void
    {
        $alice = Client::create([
            'full_name' => 'Alice Wonderland',
            'email' => 'alice@wonderland.test',
            'phone' => '09171112233',
        ]);

        $bob = Client::create([
            'full_name' => 'Bob Builder',
            'email' => 'bob@builder.test',
            'phone' => '09184445566',
        ]);

        // Search by name
        $responseName = $this->actingAs($this->admin)->get(route('admin.client-records', ['search' => 'Alice']));
        $responseName->assertOk();
        $responseName->assertSee('Alice Wonderland');
        $responseName->assertDontSee('Bob Builder');

        // Search by email
        $responseEmail = $this->actingAs($this->admin)->get(route('admin.client-records', ['search' => 'builder.test']));
        $responseEmail->assertOk();
        $responseEmail->assertSee('Bob Builder');
        $responseEmail->assertDontSee('Alice Wonderland');

        // Search by phone
        $responsePhone = $this->actingAs($this->admin)->get(route('admin.client-records', ['search' => '0917111']));
        $responsePhone->assertOk();
        $responsePhone->assertSee('Alice Wonderland');
        $responsePhone->assertDontSee('Bob Builder');
    }

    public function test_client_records_activity_filtering(): void
    {
        $activeClient = Client::create([
            'full_name' => 'Active Client',
            'email' => 'active@test.com',
        ]);
        Booking::create([
            'client_id' => $activeClient->id,
            'event_type' => 'wedding',
            'status' => 'confirmed',
        ]);

        $inactiveClient = Client::create([
            'full_name' => 'Inactive Client',
            'email' => 'inactive@test.com',
        ]);

        // 1. Has Bookings filter
        $responseHas = $this->actingAs($this->admin)->get(route('admin.client-records', ['activity' => 'has_bookings']));
        $responseHas->assertOk();
        $responseHas->assertSee('Active Client');
        $responseHas->assertDontSee('Inactive Client');

        // 2. No Bookings filter
        $responseNo = $this->actingAs($this->admin)->get(route('admin.client-records', ['activity' => 'no_bookings']));
        $responseNo->assertOk();
        $responseNo->assertSee('Inactive Client');
        $responseNo->assertDontSee('Active Client');

        // 3. All filter
        $responseAll = $this->actingAs($this->admin)->get(route('admin.client-records', ['activity' => 'all']));
        $responseAll->assertOk();
        $responseAll->assertSee('Active Client');
        $responseAll->assertSee('Inactive Client');
    }

    public function test_client_records_sorting(): void
    {
        $clientA = Client::create(['full_name' => 'Aaron Adams', 'email' => 'aaron@test.com']);
        $clientZ = Client::create(['full_name' => 'Zoe Zimmerman', 'email' => 'zoe@test.com']);

        // 1. Sort Name A-Z
        $responseAsc = $this->actingAs($this->admin)->get(route('admin.client-records', ['sort' => 'name_asc']));
        $responseAsc->assertOk();
        $itemsAsc = $responseAsc->viewData('clients')->pluck('full_name')->toArray();
        $this->assertSame(['Aaron Adams', 'Zoe Zimmerman'], $itemsAsc);

        // 2. Sort Name Z-A
        $responseDesc = $this->actingAs($this->admin)->get(route('admin.client-records', ['sort' => 'name_desc']));
        $responseDesc->assertOk();
        $itemsDesc = $responseDesc->viewData('clients')->pluck('full_name')->toArray();
        $this->assertSame(['Zoe Zimmerman', 'Aaron Adams'], $itemsDesc);

        // Add bookings for Most Bookings and Latest Activity sorting
        $bookingA = Booking::create(['client_id' => $clientA->id, 'status' => 'pending']);
        $bookingA->timestamps = false;
        $bookingA->created_at = Carbon::parse('2026-10-01 10:00:00');
        $bookingA->save();

        $bookingZ1 = Booking::create(['client_id' => $clientZ->id, 'status' => 'pending']);
        $bookingZ1->timestamps = false;
        $bookingZ1->created_at = Carbon::parse('2026-10-05 10:00:00');
        $bookingZ1->save();

        $bookingZ2 = Booking::create(['client_id' => $clientZ->id, 'status' => 'pending']);
        $bookingZ2->timestamps = false;
        $bookingZ2->created_at = Carbon::parse('2026-10-06 10:00:00');
        $bookingZ2->save();

        // 3. Sort Most Bookings (Zoe has 2, Aaron has 1)
        $responseMost = $this->actingAs($this->admin)->get(route('admin.client-records', ['sort' => 'most_bookings']));
        $responseMost->assertOk();
        $itemsMost = $responseMost->viewData('clients')->pluck('full_name')->toArray();
        $this->assertSame(['Zoe Zimmerman', 'Aaron Adams'], $itemsMost);

        // 4. Sort Latest Activity (Zoe Oct 6, Aaron Oct 1)
        $responseLatest = $this->actingAs($this->admin)->get(route('admin.client-records', ['sort' => 'latest_activity']));
        $responseLatest->assertOk();
        $itemsLatest = $responseLatest->viewData('clients')->pluck('full_name')->toArray();
        $this->assertSame(['Zoe Zimmerman', 'Aaron Adams'], $itemsLatest);
    }

    public function test_client_records_view_action_drawer_and_empty_state(): void
    {
        $client = Client::create([
            'full_name' => 'Display Test Client',
            'email' => 'display@test.com',
            'phone' => '09171234567',
            'address' => '123 Flower Street, Manila',
            'notes' => 'Prefers pastel roses and calla lilies.',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => '2026-11-20',
            'status' => 'confirmed',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.client-records'));
        $response->assertOk();

        // 1. Table action button
        $response->assertSee('View');
        $response->assertDontSee('View Client');

        // 2. Client initials & avatar
        $response->assertSee('DC'); // Initials for Display Test Client (Display + Client)

        // 3. Drawer elements and templates
        $response->assertSee('client-drawer-template-' . $client->id);
        $response->assertSee('clientDrawerContainer');
        $response->assertSee('Contact Information');
        $response->assertSee('Booking Summary');
        $response->assertSee('Recent Bookings');
        $response->assertSee('Client Notes');
        $response->assertSee('Prefers pastel roses and calla lilies.');
        $response->assertSee('123 Flower Street, Manila');

        // 4. Booking details in drawer
        $response->assertSee('Booking #' . $booking->id);
        $response->assertSee('Wedding');
        $response->assertSee('Nov 20, 2026');

        // 5. View Booking History action pointing to admin.bookings
        $response->assertSee('View Booking History');
        $response->assertSee(route('admin.bookings', ['search' => 'display@test.com']));

        // 6. Search with no results shows tailored empty state
        $emptyResponse = $this->actingAs($this->admin)->get(route('admin.client-records', ['search' => 'NonExistentClient']));
        $emptyResponse->assertOk();
        $emptyResponse->assertSee('No matching clients found');
        $emptyResponse->assertSee('Try adjusting your search or clearing the filters.');
        $emptyResponse->assertSee('Clear Filters');
    }

    public function test_client_drawer_with_zero_bookings_and_no_admin_exclusion(): void
    {
        $inactiveClient = Client::create([
            'full_name' => 'Inactive User',
            'email' => 'inactive@test.com',
            'phone' => null,
            'address' => null,
            'notes' => null,
        ]);

        $adminClient = Client::create([
            'full_name' => 'Raflora Admin',
            'email' => 'rafloraenterprise@gmail.com',
            'phone' => null,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.client-records'));
        $response->assertOk();

        // 1. Both clients visible (no hardcoded exclusion of Raflora Admin)
        $response->assertSee('Inactive User');
        $response->assertSee('Raflora Admin');

        // 2. Fallbacks for inactive client in table and drawer
        $response->assertSee('No phone provided');
        $response->assertSee('No activity');
        $response->assertSee('0 bookings');
        $response->assertSee('No bookings on record for this client.');

        // 3. View Booking History links generated for both
        $response->assertSee(route('admin.bookings', ['search' => 'inactive@test.com']));
        $response->assertSee(route('admin.bookings', ['search' => 'rafloraenterprise@gmail.com']));
    }
}

