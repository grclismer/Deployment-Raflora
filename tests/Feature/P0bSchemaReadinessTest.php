<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P0bSchemaReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_formal_confirmation_sets_confirmed_at_timestamp(): void
    {
        $client = Client::create([
            'full_name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '09171234567',
            'address' => 'Pasig City',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'The Garden',
            'status' => 'payment_submitted',
        ]);

        $this->assertNull($booking->confirmed_at);

        $booking->setNormalizedStatus('confirmed');
        $booking->save();

        $this->assertNotNull($booking->confirmed_at);
        $originalTime = $booking->confirmed_at->timestamp;

        // Ensure repeated updates don't overwrite it
        $booking->setNormalizedStatus('downpayment_received');
        $booking->save();

        $this->assertEquals($originalTime, $booking->fresh()->confirmed_at->timestamp);
    }

    public function test_quotation_and_payment_submission_do_not_set_confirmed_at(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'status' => 'pending',
            'guest_email' => 'guest@example.com',
        ]);

        $booking->setNormalizedStatus('quotation_sent');
        $booking->save();

        $this->assertNull($booking->fresh()->confirmed_at);

        $booking->setNormalizedStatus('payment_submitted');
        $booking->save();

        $this->assertNull($booking->fresh()->confirmed_at);
    }

    public function test_inventory_transaction_can_reference_another_transaction(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'name' => 'Red Roses',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 10,
            'unit_cost' => 1.5,
            'unit' => 'stem',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'status' => 'confirmed',
            'guest_email' => 'guest@example.com',
        ]);

        $original = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -5,
            'transaction_type' => 'dispatch',
            'performed_by' => $admin->id,
            'reason' => 'Original dispatch',
        ]);

        $correction = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'reference_transaction_id' => $original->id,
            'quantity_change' => 2,
            'transaction_type' => 'dispatch_correction',
            'performed_by' => $admin->id,
            'reason' => 'Correction',
        ]);

        $this->assertEquals($original->id, $correction->fresh()->referenceTransaction->id);
    }

    public function test_reference_transaction_delete_is_restricted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $item = InventoryItem::create([
            'name' => 'Red Roses 2',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 10,
            'unit_cost' => 1.5,
            'unit' => 'stem',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'status' => 'confirmed',
            'guest_email' => 'guest@example.com',
        ]);

        $original = InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -5,
            'transaction_type' => 'dispatch',
            'performed_by' => $admin->id,
            'reason' => 'Original dispatch',
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'reference_transaction_id' => $original->id,
            'quantity_change' => 2,
            'transaction_type' => 'dispatch_correction',
            'performed_by' => $admin->id,
            'reason' => 'Correction',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $original->delete();
    }
}
