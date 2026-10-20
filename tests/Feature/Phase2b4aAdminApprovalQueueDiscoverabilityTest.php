<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2b4aAdminApprovalQueueDiscoverabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Officer',
            'email' => 'admin@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $this->staff = User::create([
            'name' => 'Staff Officer',
            'email' => 'staff@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'staff',
        ]);

        $this->clientUser = User::create([
            'name' => 'Client Officer',
            'email' => 'client@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
    }

    public function test_admin_can_access_bookings_queue(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));

        $response->assertOk();
        $response->assertSee('Bookings queue');
        $response->assertSee('Awaiting Admin Approval');
        $response->assertSee('All Statuses');
        $response->assertSee('Search client, event, ID...');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.bookings'));

        $response->assertRedirect(route('login'));
    }

    public function test_staff_and_client_users_are_forbidden_from_admin_bookings(): void
    {
        $this->actingAs($this->staff)
            ->get(route('admin.bookings'))
            ->assertForbidden();

        $this->actingAs($this->clientUser)
            ->get(route('admin.bookings'))
            ->assertForbidden();
    }

    public function test_status_filter_approved_shows_only_client_accepted_bookings_awaiting_approval(): void
    {
        $approvedBooking = Booking::create([
            'event_type' => 'Awaiting Royal Wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Grand Palace Ballroom',
            'status' => 'approved',
            'total_quoted' => 150000,
            'final_quoted_price' => 150000,
        ]);

        $pendingBooking = Booking::create([
            'event_type' => 'Pending Birthday Party',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Sunset Hall',
            'status' => 'pending',
            'total_quoted' => 25000,
        ]);

        $quotationSentBooking = Booking::create([
            'event_type' => 'Quotation Debut Extravaganza',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Sky Garden',
            'status' => 'quotation_sent',
            'total_quoted' => 80000,
        ]);

        $adminApprovedBooking = Booking::create([
            'event_type' => 'Admin Approved Gala',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Diamond Ballroom',
            'status' => 'admin_approved',
            'total_quoted' => 200000,
            'final_quoted_price' => 200000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['status' => 'approved']));

        $response->assertOk();
        $response->assertSee('Awaiting Royal Wedding');
        $response->assertDontSee('• Pending Birthday Party');
        $response->assertDontSee('• Quotation Debut Extravaganza');
        $response->assertDontSee('• Admin Approved Gala');

        // Verify badge and label
        $response->assertSee('Awaiting Admin Approval');

        // Verify direct action form for final approval is rendered
        $response->assertSee(route('admin.bookings.final-approve', ['booking' => $approvedBooking->id]));
        $response->assertSee('Final Approve &amp; Enable Payment', false);
    }

    public function test_quick_filter_pill_renders_with_accurate_awaiting_approval_count(): void
    {
        Booking::create([
            'event_type' => 'Booking Approved Alpha',
            'event_date' => now()->addDays(5)->toDateString(),
            'status' => 'approved',
            'total_quoted' => 50000,
        ]);

        Booking::create([
            'event_type' => 'Booking Approved Beta',
            'event_date' => now()->addDays(7)->toDateString(),
            'status' => 'approved',
            'total_quoted' => 60000,
        ]);

        Booking::create([
            'event_type' => 'Booking Pending Gamma',
            'event_date' => now()->addDays(10)->toDateString(),
            'status' => 'pending',
            'total_quoted' => 40000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));

        $response->assertOk();
        $response->assertSee(route('admin.bookings', ['stage' => 'approval']));
        $response->assertSee('Awaiting approval');
        // Should show the count of 2 awaiting approvals
        $response->assertSee('2');
    }

    public function test_status_dropdown_contains_awaiting_admin_approval_option(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings'));

        $response->assertOk();
        $response->assertSee('<option value="approved"', false);
        $response->assertSee('Awaiting Admin Approval');
        $response->assertSee('<option value="admin_approved"', false);
        $response->assertSee('Admin Approved (Payment Pending)');
    }

    public function test_final_approval_action_is_reachable_and_transitions_booking_to_admin_approved(): void
    {
        $booking = Booking::create([
            'event_type' => 'Executive Summit Reception',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Oasis Center',
            'status' => 'approved',
            'total_quoted' => 95000,
            'final_quoted_price' => 95000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.final-approve', ['booking' => $booking->id]));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quotation final-approved. Payment submission is now available.');

        $booking->refresh();
        $this->assertSame('admin_approved', $booking->status);
    }

    public function test_final_approval_action_rejects_non_approved_bookings(): void
    {
        $booking = Booking::create([
            'event_type' => 'Unconfirmed Inquirer Gathering',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Garden Terrace',
            'status' => 'quotation_sent',
            'total_quoted' => 45000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.final-approve', ['booking' => $booking->id]));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This booking is not awaiting Admin final approval.');

        $booking->refresh();
        $this->assertSame('quotation_sent', $booking->status);
    }

    public function test_booking_detail_and_edit_navigation_retained_from_listing(): void
    {
        $booking = Booking::create([
            'event_type' => 'Bridal Shower Bliss',
            'event_date' => now()->addDays(8)->toDateString(),
            'venue' => 'Boutique Garden',
            'status' => 'approved',
            'total_quoted' => 35000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['status' => 'approved']));

        $response->assertOk();
        $response->assertSee(route('admin.bookings.edit', ['booking' => $booking->id]));
        $response->assertSee('Review / Edit Quote');
    }

    public function test_search_by_client_name_and_event_type(): void
    {
        $clientA = Client::create([
            'full_name' => 'Eleanor Vance',
            'email' => 'eleanor@vance.test',
            'phone' => '09171112233',
        ]);

        $clientB = Client::create([
            'full_name' => 'Theodora Crain',
            'email' => 'theo@crain.test',
            'phone' => '09172223344',
        ]);

        $bookingA = Booking::create([
            'client_id' => $clientA->id,
            'event_type' => 'Hill House Gala',
            'event_date' => now()->addDays(12)->toDateString(),
            'venue' => 'Hill House Manor',
            'status' => 'approved',
            'total_quoted' => 120000,
        ]);

        $bookingB = Booking::create([
            'client_id' => $clientB->id,
            'event_type' => 'Studio Launch Party',
            'event_date' => now()->addDays(16)->toDateString(),
            'venue' => 'Art District',
            'status' => 'approved',
            'total_quoted' => 55000,
        ]);

        // Search by Client Name
        $responseA = $this->actingAs($this->admin)->get(route('admin.bookings', [
            'status' => 'approved',
            'search' => 'Eleanor',
        ]));
        $responseA->assertOk();
        $responseA->assertSee('Hill House Gala');
        $responseA->assertDontSee('• Studio Launch Party');

        // Search by Event Type
        $responseB = $this->actingAs($this->admin)->get(route('admin.bookings', [
            'status' => 'approved',
            'search' => 'Studio Launch',
        ]));
        $responseB->assertOk();
        $responseB->assertSee('Studio Launch Party');
        $responseB->assertDontSee('• Hill House Gala');
    }

    public function test_invalid_status_filter_falls_back_safely_to_all(): void
    {
        Booking::create([
            'event_type' => 'Invalid Test Booking A',
            'event_date' => now()->addDays(5)->toDateString(),
            'status' => 'approved',
            'total_quoted' => 30000,
        ]);

        Booking::create([
            'event_type' => 'Invalid Test Booking B',
            'event_date' => now()->addDays(7)->toDateString(),
            'status' => 'pending',
            'total_quoted' => 20000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['status' => 'nonexistent_status_xyz']));

        $response->assertOk();
        // Since invalid status falls back to all, both bookings are returned
        $response->assertSee('Invalid Test Booking A');
        $response->assertSee('Invalid Test Booking B');
    }

    public function test_empty_results_when_filtering_by_approved_renders_friendly_message(): void
    {
        // No bookings in approved status
        Booking::create([
            'event_type' => 'Active Ongoing Event',
            'event_date' => now()->addDays(3)->toDateString(),
            'status' => 'confirmed',
            'total_quoted' => 60000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['status' => 'approved']));

        $response->assertOk();
        $response->assertSee('No bookings are currently awaiting Admin final approval.');
    }

    public function test_empty_results_when_search_finds_no_records(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['search' => 'NoSuchBookingKeyword12345']));

        $response->assertOk();
        $response->assertSee('No bookings found matching your search and filter criteria.');
        $response->assertSee('Clear filters');
    }

    public function test_pagination_works_and_preserves_query_string(): void
    {
        for ($i = 1; $i <= 18; $i++) {
            Booking::create([
                'event_type' => sprintf('Batch Approved Event #%02d', $i),
                'event_date' => now()->addDays($i)->toDateString(),
                'venue' => 'Batch Ballroom',
                'status' => 'approved',
                'total_quoted' => 50000 + ($i * 1000),
            ]);
        }

        $response = $this->actingAs($this->admin)->get(route('admin.bookings', ['status' => 'approved']));

        $response->assertOk();
        $response->assertSee('Showing 18 bookings');
        // Pagination link should preserve status=approved
        $response->assertSee('page=2');
        $response->assertSee('status=approved');
    }
}
