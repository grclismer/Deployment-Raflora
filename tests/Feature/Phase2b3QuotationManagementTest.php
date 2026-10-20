<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2b3QuotationManagementTest extends TestCase
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
            'name' => 'Client User',
            'email' => 'client@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'client',
        ]);
    }

    public function test_admin_can_access_quotations_management(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.quotations'));

        $response->assertOk();
        $response->assertSee('Pending Quotations');
        $response->assertSee('Status');
        $response->assertSee('Sort by');
        $response->assertSee('Search client, event, ID...');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('admin.quotations'));

        $response->assertRedirect(route('login'));
    }

    public function test_staff_user_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->staff)->get(route('admin.quotations'));

        $response->assertForbidden();
    }

    public function test_client_user_receives_403_forbidden(): void
    {
        $response = $this->actingAs($this->clientUser)->get(route('admin.quotations'));

        $response->assertForbidden();
    }

    public function test_default_filter_shows_active_pending_and_issued_quotations_only(): void
    {
        $client = Client::create([
            'full_name' => 'Adeline Chen',
            'email' => 'adeline@example.test',
            'phone' => '09171112233',
            'address' => 'Quezon City',
        ]);

        $pendingBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Pending Gala Event',
            'event_date' => now()->addDays(20),
            'venue' => 'Grand Ballroom',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $pendingBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 12000,
            'valid_until' => now()->addDays(7),
        ]);

        $issuedBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Issued Wedding Expo',
            'event_date' => now()->addDays(25),
            'venue' => 'Seaside Pavilion',
            'status' => 'quotation_sent',
        ]);

        Quotation::create([
            'booking_id' => $issuedBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 25000,
            'valid_until' => now()->addDays(7),
        ]);

        $acceptedBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Accepted Silver Anniversary',
            'event_date' => now()->addDays(30),
            'venue' => 'Manor House',
            'status' => 'approved',
        ]);

        Quotation::create([
            'booking_id' => $acceptedBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 40000,
            'valid_until' => now()->addDays(7),
        ]);

        $supersededBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Superseded Corporate Lunch',
            'event_date' => now()->addDays(35),
            'venue' => 'Sky Garden',
            'status' => 'quotation_sent',
        ]);

        Quotation::create([
            'booking_id' => $supersededBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_SUPERSEDED,
            'version' => 1,
            'final_quoted_price' => 18000,
            'valid_until' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations'));

        $response->assertOk();
        $response->assertSee('Pending Gala Event');
        $response->assertSee('Issued Wedding Expo');
        $response->assertDontSee('Accepted Silver Anniversary');
        $response->assertDontSee('Superseded Corporate Lunch');
    }

    public function test_status_filter_all_shows_all_quotations(): void
    {
        $client = Client::create([
            'full_name' => 'Clara Oswald',
            'email' => 'clara@example.test',
            'phone' => '09172223344',
            'address' => 'Makati City',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Botanical Exhibit',
            'event_date' => now()->addDays(15),
            'venue' => 'Glasshouse',
            'status' => 'approved',
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 32000,
            'valid_until' => now()->addDays(7),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['status' => 'all']));

        $response->assertOk();
        $response->assertSee('Botanical Exhibit');
        $response->assertSee('Accepted');
        $response->assertSee('32,000.00');
    }

    public function test_status_filter_pending_shows_only_pending(): void
    {
        $client = Client::create([
            'full_name' => 'Donna Noble',
            'email' => 'donna@example.test',
            'phone' => '09173334455',
            'address' => 'Pasig City',
        ]);

        $pendingBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Chiswick Debut',
            'event_date' => now()->addDays(18),
            'venue' => 'Heritage Hall',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $pendingBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 15000,
        ]);

        $issuedBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Gallifrey Soiree',
            'event_date' => now()->addDays(22),
            'venue' => 'Starlight Lounge',
            'status' => 'quotation_sent',
        ]);

        Quotation::create([
            'booking_id' => $issuedBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 28000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['status' => 'pending']));

        $response->assertOk();
        $response->assertSee('Chiswick Debut');
        $response->assertDontSee('Gallifrey Soiree');
    }

    public function test_status_filter_accepted_shows_only_accepted(): void
    {
        $client = Client::create([
            'full_name' => 'Rose Tyler',
            'email' => 'rose@example.test',
            'phone' => '09174445566',
            'address' => 'Taguig City',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Powell Estate Banquet',
            'event_date' => now()->addDays(14),
            'venue' => 'Central Hall',
            'status' => 'approved',
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 55000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['status' => 'accepted']));

        $response->assertOk();
        $response->assertSee('Powell Estate Banquet');
        $response->assertSee('Rose Tyler');
        $response->assertSee('55,000.00');
    }

    public function test_status_filter_issued_shows_only_issued(): void
    {
        $client = Client::create([
            'full_name' => 'Martha Jones',
            'email' => 'martha@example.test',
            'phone' => '09178889900',
            'address' => 'Quezon City',
        ]);

        $pendingBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Medical Gala',
            'event_date' => now()->addDays(25),
            'venue' => 'Hospital Hall',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $pendingBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 16000,
        ]);

        $issuedBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'UNIT Conference',
            'event_date' => now()->addDays(28),
            'venue' => 'Defense Center',
            'status' => 'quotation_sent',
        ]);

        Quotation::create([
            'booking_id' => $issuedBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 1,
            'final_quoted_price' => 42000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['status' => 'issued']));

        $response->assertOk();
        $response->assertSee('UNIT Conference');
        $response->assertDontSee('Medical Gala');
    }

    public function test_status_filter_superseded_shows_only_superseded(): void
    {
        $client = Client::create([
            'full_name' => 'Sarah Jane Smith',
            'email' => 'sarah@example.test',
            'phone' => '09179990011',
            'address' => 'Ealing',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Bannerman Road Gathering',
            'event_date' => now()->addDays(32),
            'venue' => 'Attic Studio',
            'status' => 'quotation_sent',
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_SUPERSEDED,
            'version' => 1,
            'final_quoted_price' => 17500,
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 2,
            'final_quoted_price' => 23000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['status' => 'superseded']));

        $response->assertOk();
        $response->assertSee('Bannerman Road Gathering');
        $response->assertSee('v1');
        $response->assertSee('17,500.00');
        $response->assertSee('Superseded');
        $response->assertDontSee('23,000.00');
    }

    public function test_invalid_status_filter_falls_back_to_active_gracefully(): void
    {
        $client = Client::create([
            'full_name' => 'Amy Pond',
            'email' => 'amy@example.test',
            'phone' => '09175556677',
            'address' => 'Mandaluyong City',
        ]);

        $pendingBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Leadworth Fair',
            'event_date' => now()->addDays(12),
            'venue' => 'Village Green',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $pendingBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 11000,
        ]);

        $acceptedBooking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'Rory Wedding Feast',
            'event_date' => now()->addDays(16),
            'venue' => 'Country Club',
            'status' => 'approved',
        ]);

        Quotation::create([
            'booking_id' => $acceptedBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ACCEPTED,
            'version' => 1,
            'final_quoted_price' => 22000,
        ]);

        // Passing an unrecognized status
        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['status' => 'nonexistent_junk_status']));

        $response->assertOk();
        $response->assertSee('Leadworth Fair');
        $response->assertDontSee('Rory Wedding Feast');
    }

    public function test_search_by_client_name_filters_results(): void
    {
        $clientA = Client::create([
            'full_name' => 'Aurelia Vance',
            'email' => 'aurelia@example.test',
            'phone' => '09176667788',
            'address' => 'Cebu City',
        ]);

        $clientB = Client::create([
            'full_name' => 'Cassian Cole',
            'email' => 'cassian@example.test',
            'phone' => '09177778899',
            'address' => 'Davao City',
        ]);

        $bookingA = Booking::create([
            'client_id' => $clientA->id,
            'event_type' => 'Golden Reception',
            'event_date' => now()->addDays(10),
            'venue' => 'Manila Hotel',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $bookingA->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 20000,
        ]);

        $bookingB = Booking::create([
            'client_id' => $clientB->id,
            'event_type' => 'Silver Reception',
            'event_date' => now()->addDays(11),
            'venue' => 'Diamond Hotel',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $bookingB->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 25000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['search' => 'Aurelia']));

        $response->assertOk();
        $response->assertSee('Aurelia Vance');
        $response->assertSee('Golden Reception');
        $response->assertDontSee('Cassian Cole');
        $response->assertDontSee('Silver Reception');
    }

    public function test_search_by_guest_name_filters_results(): void
    {
        $guestBooking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Fernando Amorsolo',
            'guest_email' => 'fernando@example.test',
            'event_type' => 'Art Exhibition Reception',
            'event_date' => now()->addDays(20),
            'venue' => 'National Museum',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $guestBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 30000,
        ]);

        $otherBooking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guillermo Tolentino',
            'guest_email' => 'guillermo@example.test',
            'event_type' => 'Sculpture Launch',
            'event_date' => now()->addDays(21),
            'venue' => 'Art Gallery',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $otherBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 35000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['search' => 'Amorsolo']));

        $response->assertOk();
        $response->assertSee('Fernando Amorsolo');
        $response->assertSee('Art Exhibition Reception');
        $response->assertDontSee('Guillermo Tolentino');
    }

    public function test_search_by_event_type_and_numeric_booking_id(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Leonor Rivera',
            'guest_email' => 'leonor@example.test',
            'event_type' => 'Victorian Tea Party',
            'event_date' => now()->addDays(15),
            'venue' => 'Intramuros Courtyard',
            'status' => 'pending',
        ]);

        $quote = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 18500,
        ]);

        // Search by event type
        $responseEvent = $this->actingAs($this->admin)->get(route('admin.quotations', ['search' => 'Victorian']));
        $responseEvent->assertOk();
        $responseEvent->assertSee('Victorian Tea Party');

        // Search by numeric booking ID
        $responseId = $this->actingAs($this->admin)->get(route('admin.quotations', ['search' => (string) $booking->id]));
        $responseId->assertOk();
        $responseId->assertSee('Victorian Tea Party');
        $responseId->assertSee('Booking #' . $booking->id);
    }

    public function test_sorting_by_amount_high_and_low(): void
    {
        $lowBooking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Low Tier Client',
            'event_type' => 'Simple Bouquet Package',
            'event_date' => now()->addDays(5),
            'venue' => 'Office',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $lowBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 5000,
        ]);

        $highBooking = Booking::create([
            'client_id' => null,
            'guest_name' => 'High Tier Client',
            'event_type' => 'Grand Presidential Gala',
            'event_date' => now()->addDays(10),
            'venue' => 'Convention Center',
            'status' => 'pending',
        ]);

        Quotation::create([
            'booking_id' => $highBooking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 95000,
        ]);

        // Sort amount_high: high price should appear before low price
        $responseHigh = $this->actingAs($this->admin)->get(route('admin.quotations', ['sort' => 'amount_high']));
        $responseHigh->assertOk();
        $contentHigh = $responseHigh->getContent();
        $posHigh = strpos($contentHigh, '95,000.00');
        $posLow = strpos($contentHigh, '5,000.00');
        $this->assertTrue($posHigh !== false && $posLow !== false && $posHigh < $posLow);

        // Sort amount_low: low price should appear before high price
        $responseLow = $this->actingAs($this->admin)->get(route('admin.quotations', ['sort' => 'amount_low']));
        $responseLow->assertOk();
        $contentLow = $responseLow->getContent();
        $posLowOrder = strpos($contentLow, '5,000.00');
        $posHighOrder = strpos($contentLow, '95,000.00');
        $this->assertTrue($posLowOrder !== false && $posHighOrder !== false && $posLowOrder < $posHighOrder);
    }

    public function test_action_link_navigates_to_correct_booking_review_screen(): void
    {
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Test Link Guest',
            'event_type' => 'Navigation Test Event',
            'event_date' => now()->addDays(8),
            'venue' => 'Ballroom A',
            'status' => 'pending',
        ]);

        $quote = Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_PENDING,
            'version' => 1,
            'final_quoted_price' => 14000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations'));

        $response->assertOk();
        $expectedUrl = route('admin.bookings.show', $booking->id);
        $response->assertSee($expectedUrl, false);
        $response->assertSee('Review quotation');
    }

    public function test_displays_accurate_version_validity_and_downpayment_information(): void
    {
        $validUntilDate = now()->addDays(7);

        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Detailed Meta Guest',
            'event_type' => 'Detailed Metadata Gala',
            'event_date' => now()->addDays(20),
            'venue' => 'Grand Plaza',
            'status' => 'quotation_sent',
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'issued_by' => $this->admin->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 3,
            'final_quoted_price' => 45000,
            'downpayment_percentage' => 50,
            'valid_until' => $validUntilDate->toDateString(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.quotations'));

        $response->assertOk();
        $response->assertSee('v3');
        $response->assertSee('45,000.00');
        $response->assertSee('50% Downpayment');
        $response->assertSee($validUntilDate->format('M j, Y'));
        $response->assertSee('Issued');
    }

    public function test_empty_search_produces_graceful_empty_state(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.quotations', ['search' => 'definitely_zero_matches_12345']));

        $response->assertOk();
        $response->assertSee('No quotations found matching your filter criteria.');
        $response->assertSee('Clear filters');
    }
}
