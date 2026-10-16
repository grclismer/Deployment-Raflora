<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientViewBookingDataFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_working_and_ai_prices_stay_hidden_until_a_quotation_is_issued()
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Address', 'user_id' => $user->id]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'pending',
            'final_quoted_price' => 5799.99,
            'ai_analysis_data' => [
                'pricing_summary' => [
                    'estimated_grand_total_php' => 9999.99
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get("/client/bookings/analysis/{$booking->id}");

        $response->assertStatus(200);
        $response->assertDontSee('5,799.99');
        $response->assertDontSee('9,999.99');
        $response->assertSee('Not yet issued');
        $this->assertNull($response->original->gatherData()['sharedQuotation']);
    }

    public function test_current_quotation_is_loaded_and_historical_is_ignored()
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Address', 'user_id' => $user->id]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'quotation_sent',
        ]);

        // Historical superseded quotation
        Quotation::create([
            'booking_id' => $booking->id,
            'status' => Quotation::STATUS_SUPERSEDED,
            'version' => 1,
            'final_quoted_price' => 4000
        ]);

        // Current issued quotation
        $activeQuotation = Quotation::create([
            'booking_id' => $booking->id,
            'status' => Quotation::STATUS_ISSUED,
            'version' => 2,
            'final_quoted_price' => 5000,
            'items_snapshot' => [
                ['item_name' => 'Roses', 'quantity' => 10, 'quoted_unit_price' => 500]
            ]
        ]);

        $response = $this->actingAs($user)->get("/client/bookings/analysis/{$booking->id}");

        $response->assertStatus(200);
        $viewData = $response->original->gatherData();
        
        $this->assertNotNull($viewData['activeQuotation']);
        $this->assertEquals(2, $viewData['activeQuotation']->version);
        $this->assertEquals(5000, $viewData['activeQuotation']->final_quoted_price);
        $this->assertEquals('Roses', $viewData['activeQuotation']->items_snapshot[0]['item_name']);
    }

    public function test_no_quotation_yet_shows_no_booking_or_ai_price()
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Address', 'user_id' => $user->id]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'status' => 'pending',
            'final_quoted_price' => 6000,
            'ai_analysis_data' => [
                'pricing_summary' => [
                    'estimated_grand_total_php' => 8000
                ]
            ]
        ]);

        $response = $this->actingAs($user)->get("/client/bookings/analysis/{$booking->id}");

        $response->assertStatus(200);
        $viewData = $response->original->gatherData();
        
        $this->assertNull($viewData['activeQuotation']);
        $this->assertNull($viewData['sharedQuotation']);
        $this->assertNull($booking->fresh()->shared_quote_total);
        $response->assertDontSee('6,000.00');
        $response->assertDontSee('8,000.00');
    }

    public function test_package_integrity()
    {
        $user = User::factory()->create(['role' => 'client']);
        $client = Client::create(['full_name' => 'Test Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Address', 'user_id' => $user->id]);
        $package = Package::create(['title' => 'Gold Package', 'price' => 15000]);
        $booking = Booking::create([
            'client_id' => $client->id,
            'package_id' => $package->id,
            'status' => 'pending',
            'final_quoted_price' => 15000,
        ]);

        $response = $this->actingAs($user)->get("/client/bookings/analysis/{$booking->id}");

        $response->assertStatus(200);
        $viewData = $response->original->gatherData();
        
        $this->assertEquals($package->id, $viewData['booking']->package_id);
    }
}
