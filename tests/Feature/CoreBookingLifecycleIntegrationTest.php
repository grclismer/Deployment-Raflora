<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\Payment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Carbon\Carbon;

class CoreBookingLifecycleIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_booking_lifecycle()
    {
        Storage::fake('public');

        // Setup users
        $clientUser = User::factory()->create(['role' => 'client']);
        Client::create([
            'id' => $clientUser->id,
            'email' => $clientUser->email,
            'full_name' => $clientUser->name,
            'phone' => '09171234567',
            'address' => 'Test Address',
        ]);
        $adminUser = User::factory()->create(['role' => 'admin']);
        $staffUser = User::factory()->create(['role' => 'staff']);

        // --- Stage D: Inventory Planning (Pre-setup) ---
        $inventoryItem = InventoryItem::create([
            'name' => 'AI Vase',
            'category' => 'prop',
            'is_perishable' => false,
            'current_stock' => 100,
            'unit_cost' => 50,
            'unit' => 'piece',
            'min_stock' => 10,
        ]);

        $image = UploadedFile::fake()->create('inspiration.jpg', 100);
        $eventDate = Carbon::now()->addDays(30)->toDateString();
        $analysisToken = \Illuminate\Support\Str::random(40);

        $response = $this->actingAs($clientUser)->withSession([
            "booking_analysis_payloads.{$analysisToken}" => [
                'image_hash' => sha1_file($image->getRealPath()),
                'temp_path' => 'bookings/inspiration-images/inspiration.jpg',
                'analysis' => [
                    'theme' => 'Test Theme',
                    'suggested_materials' => [
                        [
                            'item_name' => 'AI Vase',
                            'category' => 'prop',
                            'unit_type' => 'piece',
                            'quantity' => 10,
                            'unit_cost_php' => 100,
                        ]
                    ]
                ],
                'raw_response' => 'mocked',
            ]
        ])->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => $eventDate,
            'venue' => 'Test Venue',
            'guest_count' => 50,
            'special_requests' => 'Theme is good',
            'analysis_token' => $analysisToken,
            'inspiration_image' => $image,
        ]);

        $booking = Booking::first();
        $this->assertNotNull($booking, "Stage A Failed: Booking not created.");
        $response->assertRedirect(route('bookings.show', $booking));
        $this->assertEquals('pending', $booking->status, "Stage A/B Failed: Incorrect initial status.");

        // --- Stage C: Material Confirmation ---
        $aiItem = $booking->bookingItems()->first();
        $this->assertNotNull($aiItem, "Stage B Failed: AI suggestion not saved.");
        $this->assertEquals($inventoryItem->id, $aiItem->inventory_item_id, "Stage B Failed: AI suggestion not mapped to inventory.");

        $response = $this->actingAs($adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'save',
            'items' => [[
                'booking_item_id' => $aiItem->id,
                'item_name' => $aiItem->item_name,
                'inventory_item_id' => $aiItem->inventory_item_id,
                'quantity' => 10,
                'unit_price' => 100,
                'is_ai_suggested' => '1',
            ]],
        ]);
        if (session('error')) {
            $response->dumpSession();
        }
        $response->assertRedirect();
        
        $response = $this->actingAs($adminUser)->post(route('admin.bookings.items.confirm', [
            'booking' => $booking,
            'bookingItem' => $aiItem->fresh(),
        ]));
        $response->assertRedirect();
        $this->assertNotNull($aiItem->fresh()->confirmed_at, "Stage C Failed: Material not confirmed.");

        // --- Stage E: Quotation and Client Acceptance ---
        $response = $this->actingAs($adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('quotation_sent', $booking->status, "Stage E Failed: Quotation not sent.");

        $response = $this->actingAs($clientUser)->post(route('bookings.accept', $booking));
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('approved', $booking->status, "Stage E Failed: Client could not accept.");

        $response = $this->actingAs($adminUser)->post(route('admin.bookings.final-approve', $booking));
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('admin_approved', $booking->status, "Stage E Failed: Admin could not final approve.");

        // --- Stage F: Payment and Confirmation ---
        $response = $this->actingAs($clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-DP-123',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);
        $response->assertRedirect();
        $payment = Payment::where('booking_id', $booking->id)->first();
        $this->assertNotNull($payment, "Stage F Failed: Payment not created.");

        $response = $this->actingAs($adminUser)->post(route('admin.payments.verify', $payment), [
            'amount_received' => $payment->amount,
        ]);
        $response->assertRedirect();
        
        $response = $this->actingAs($adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'preparation_start_date' => now()->toDateString(),
            'status' => 'confirmed',
            'action' => 'save',
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('confirmed', $booking->status, "Stage F Failed: Booking not confirmed.");
        
        $this->assertEquals(10, $inventoryItem->fresh()->reserved_stock, "Stage D/F Failed: Stock not reserved upon preparation eligibility.");
        
        // --- Stage G: Staff Assignment and Preparation ---
        $response = $this->actingAs($adminUser)->post(route('admin.bookings.assign-staff', $booking), [
            'staff_id' => $staffUser->id,
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals($staffUser->id, $booking->staff_id, "Stage G Failed: Staff not assigned.");

        // --- Stage H: Dispatch ---
        $dispatchService = new \App\Services\InventoryDispatchService();
        $dispatchService->dispatchItems($booking, [
            ['inventory_item_id' => $inventoryItem->id, 'quantity' => 10]
        ], $adminUser->id, 'Admin Dispatch');
        
        $inventoryItem->refresh();
        $this->assertEquals(90, $inventoryItem->current_stock, "Stage H Failed: Physical stock not deducted on dispatch.");

        $response = $this->actingAs($adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_in_progress',
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('event_in_progress', $booking->status);
        
        // --- Stage I: Event Completion and Return ---
        $response = $this->actingAs($adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_completed',
            'action' => 'save',
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('event_completed', $booking->status, "Stage I Failed: Event not marked completed.");

        $response = $this->actingAs($adminUser)->get(route('admin.return-tracking.manage', $booking));
        $response->assertOk();
        $return = $booking->returns()->first();
        $this->assertNotNull($return, "Stage I Failed: Return tracking not initialized.");
        $returnItem = $return->returnItems()->first();
        $this->assertNotNull($returnItem, "Stage I Failed: Return Item not created for dispatch.");

        $response = $this->actingAs($adminUser)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 10,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
        ]);
        $response->assertRedirect();

        // --- Stage J: Final Reconciliation & Payment ---
        $inventoryItem->refresh();
        $this->assertEquals(100, $inventoryItem->current_stock, "Stage J Failed: Stock not restored upon good return.");
        
        // Client pays the remaining balance
        $response = $this->actingAs($clientUser)->post(route('bookings.payment.reference', $booking), [
            'reference_number' => 'CLIENT-FINAL-456',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);
        $response->assertRedirect();
        
        $finalPayment = Payment::where('booking_id', $booking->id)->where('payment_option', 'full_payment')->first();
        $this->assertNotNull($finalPayment, "Stage J Failed: Final payment not created.");

        // Admin verifies the final payment
        $response = $this->actingAs($adminUser)->post(route('admin.payments.verify', $finalPayment), [
            'amount_received' => $finalPayment->amount,
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals(0, $booking->remaining_balance, "Stage J Failed: Remaining balance not 0.");

        $response = $this->actingAs($adminUser)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'completed',
            'action' => 'save',
        ]);
        $response->assertRedirect();
        $booking->refresh();
        $this->assertEquals('completed', $booking->status, "Stage J Failed: Booking not finally completed.");
    }
}
