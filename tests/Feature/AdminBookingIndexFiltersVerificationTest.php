<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\TemporaryGuestBooking;
use Carbon\Carbon;

class AdminBookingIndexFiltersVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->adminUser = User::factory()->create(['role' => 'admin']);
    }

    public function test_workflow_navigation_and_event_type_filters()
    {
        // 1. Setup Data
        Booking::create([
            'status' => 'cancelled',
            'event_type' => 'wedding',
            'event_date' => '2026-10-10',
            'venue' => 'CancelledVenue',
        ]);
        
        Booking::create([
            'status' => 'pending',
            'event_type' => 'birthday',
            'event_date' => '2026-10-12',
            'venue' => 'PendingVenue',
        ]);

        Booking::create([
            'status' => 'confirmed',
            'event_type' => 'corporate',
            'event_date' => '2026-11-01',
            'venue' => 'ConfirmedVenue',
        ]);

        TemporaryGuestBooking::create([
            'status' => 'pending',
            'event_type' => 'birthday',
            'event_date' => '2026-10-15',
            'venue' => 'RequestVenue',
            'guest_name' => 'Guest',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09123456789',
            'guest_address' => 'Test Address',
            'claim_token_hash' => hash('sha256', 'test_token'),
            'booking_reference' => 'REF-123456',
            'expires_at' => Carbon::now()->addDays(7),
        ]);

        // 2. Test Cancelled Stage
        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings', ['stage' => 'cancelled']));
        $response->assertOk();
        $response->assertSee('CancelledVenue');
        $response->assertDontSee('ConfirmedVenue');
        
        // 3. Test Event Type Filter
        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings', ['stage' => 'all', 'event_type' => 'birthday']));
        $response->assertOk();
        $response->assertSee('PendingVenue');
        $response->assertDontSee('CancelledVenue');
        $response->assertDontSee('ConfirmedVenue');

        // 4. Test Event Date (From)
        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings', ['stage' => 'all', 'event_date_from' => '2026-10-20']));
        $response->assertOk();
        $response->assertSee('ConfirmedVenue'); // 2026-11-01
        $response->assertDontSee('CancelledVenue'); // 2026-10-10

        // 5. Test Event Date (To)
        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings', ['stage' => 'all', 'event_date_to' => '2026-10-11']));
        $response->assertOk();
        $response->assertSee('CancelledVenue');
        $response->assertDontSee('ConfirmedVenue');

        // 6. Test Event Date Range
        $response = $this->actingAs($this->adminUser)->get(route('admin.bookings', [
            'stage' => 'all', 
            'event_date_from' => '2026-10-10', 
            'event_date_to' => '2026-10-10'
        ]));
        $response->assertOk();
        $response->assertSee('CancelledVenue');
        $response->assertDontSee('PendingVenue');
    }
}
