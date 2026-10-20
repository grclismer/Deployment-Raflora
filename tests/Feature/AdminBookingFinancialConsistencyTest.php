<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;

class AdminBookingFinancialConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_queue_and_review_display_consistent_quotation()
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => 'admin_finance_1@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $booking = Booking::create([
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30),
            'venue' => 'Manila',
            'status' => 'quotation_sent',
            'raw_materials_sum' => 10000.00,
            'multiplier' => 3.0,
            'final_quoted_price' => 30000.00,
            'total_quoted' => 30000.00,
            'guest_email' => 'guest@example.com',
            'guest_name' => 'Guest',
            'guest_phone' => '1234567890'
        ]);

        // Access Queue
        $response = $this->actingAs($admin)->get(route('admin.bookings'));
        $response->assertStatus(200);
        $response->assertSee('30,000.00');

        // Access Review
        $response = $this->actingAs($admin)->get(route('admin.bookings.edit', $booking));
        $response->assertStatus(200);
        $response->assertSee((string) floatval('30000.00'), false);
    }

    public function test_override_quotation_is_consistent_and_persisted_in_view()
    {
        $admin = User::create([
            'name' => 'Admin User 2',
            'email' => 'admin_finance_2@example.com',
            'password' => bcrypt('password'),
            'role' => 'admin'
        ]);

        $booking = Booking::create([
            'event_type' => 'Birthday',
            'event_date' => now()->addDays(30),
            'venue' => 'Makati',
            'status' => 'quotation_sent',
            'raw_materials_sum' => 10000.00,
            'multiplier' => 3.0,
            'final_quoted_price' => 35000.00,
            'total_quoted' => 35000.00,
            'guest_email' => 'guest2@example.com',
            'guest_name' => 'Guest 2',
            'guest_phone' => '1234567890'
        ]);

        // Access Queue
        $responseQueue = $this->actingAs($admin)->get(route('admin.bookings'));
        $responseQueue->assertStatus(200);
        $responseQueue->assertSee('35,000.00');

        // Access Review
        $responseReview = $this->actingAs($admin)->get(route('admin.bookings.edit', $booking));
        $responseReview->assertStatus(200);
        $responseReview->assertSee((string) floatval('35000.00'), false);
    }
}
