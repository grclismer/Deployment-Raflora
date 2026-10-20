<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\BookingWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeliveryOrderFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $clientUser;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin');
        $this->clientUser = $this->user('client');
        $this->client = Client::create(['full_name' => 'Delivery Client', 'email' => $this->clientUser->email, 'phone' => '09171234567']);
    }

    public function test_booking_forms_offer_delivery_and_hide_guest_and_table_counts_for_it(): void
    {
        $this->actingAs($this->clientUser)
            ->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('<option value="delivery"', false)
            ->assertSee('data-delivery-hidden', false);

        $this->get(route('guest.booking.create'))
            ->assertOk()
            ->assertSee('<option value="delivery"', false)
            ->assertSee('data-delivery-hidden', false);
    }

    public function test_delivery_workflow_has_no_return_stages_and_renames_event_execution(): void
    {
        $workflow = app(BookingWorkflowService::class)->resolve($this->deliveryBooking('event_in_progress'));

        $this->assertSame(12, $workflow['total']);
        $this->assertArrayNotHasKey('material_return', $workflow['stages']);
        $this->assertArrayNotHasKey('inventory_reconciliation', $workflow['stages']);
        $this->assertSame('event_execution', $workflow['current']);
        $this->assertSame('Delivery', $workflow['current_label']);
        $this->assertSame(11, $workflow['current_number']);
        $this->assertSame(12, $workflow['stages']['completion']['number']);
        $this->assertSame(4, $workflow['phases']['staff']['total']);

        // Other event types keep the full 14-stage workflow.
        $event = $this->deliveryBooking('event_in_progress');
        $event->update(['event_type' => 'wedding']);
        $this->assertSame(14, app(BookingWorkflowService::class)->resolve($event->fresh())['total']);
    }

    public function test_admin_marks_a_paid_delivery_received_and_it_completes_without_a_return_audit(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 5000);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.delivery-received', $booking))
            ->assertSessionHas('success', 'Item received — the delivery order is complete.');

        $booking->refresh();
        $this->assertSame('completed', $booking->status);
        $this->assertNotNull($booking->delivery_received_at);
        $this->assertSame('admin', $booking->delivery_received_via);
        $this->assertSame($this->admin->id, (int) $booking->delivery_received_by);
        $this->assertFalse(AssetReturn::where('booking_id', $booking->id)->exists());
        $this->assertTrue(AuditLog::where('action', 'delivery_received')->exists());
        $this->assertTrue(app(BookingWorkflowService::class)->resolve($booking)['finished']);
    }

    public function test_client_marks_received_with_a_balance_then_final_payment_completes_it(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 2000);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.delivery-received', $booking))
            ->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('event_completed', $booking->status);
        $this->assertSame('client', $booking->delivery_received_via);
        $this->assertTrue(AdminAlert::where('type', 'delivery_received')->where('booking_id', $booking->id)->exists());

        $workflow = app(BookingWorkflowService::class)->resolve($booking);
        $this->assertSame('completion', $workflow['current']);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.final_payment', $booking), ['amount_received' => 3000, 'payment_type' => 'cash'])
            ->assertSessionHas('success');

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertFalse(AssetReturn::where('booking_id', $booking->id)->exists());
    }

    public function test_log_final_payment_action_completes_a_received_delivery(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 2000);
        $this->actingAs($this->clientUser)->post(route('bookings.delivery-received', $booking));

        $this->actingAs($this->admin)
            ->put(route('admin.bookings.update', $booking), $this->formFields($booking->fresh()) + ['action' => 'log_final_payment']);

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertFalse(AssetReturn::where('booking_id', $booking->id)->exists());
    }

    public function test_verifying_the_clients_balance_reference_completes_a_received_delivery(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 2000);
        $this->actingAs($this->clientUser)->post(route('bookings.delivery-received', $booking));
        $payment = Payment::create([
            'booking_id' => $booking->id,
            'amount' => 3000,
            'payment_option' => 'full_payment',
            'payment_type' => 'gcash',
            'reference_number' => 'REF-BALANCE-1',
            'status' => 'pending',
        ]);

        $this->actingAs($this->admin)->post(route('admin.payments.verify', $payment->id))->assertSessionHas('success');

        $this->assertSame('completed', $booking->fresh()->status);
        $this->assertFalse(AssetReturn::where('booking_id', $booking->id)->exists());
    }

    public function test_paying_before_the_delivery_arrives_keeps_it_out_for_delivery(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 2000);

        $this->actingAs($this->admin)
            ->post(route('admin.bookings.final_payment', $booking), ['amount_received' => 3000, 'payment_type' => 'cash']);

        $this->assertSame('event_in_progress', $booking->fresh()->status);
        $this->assertFalse(AssetReturn::where('booking_id', $booking->id)->exists());
    }

    public function test_received_can_only_be_marked_once_and_only_for_out_for_delivery_orders(): void
    {
        $confirmed = $this->deliveryBooking('downpayment_received', paid: 2000);
        $this->actingAs($this->clientUser)
            ->post(route('bookings.delivery-received', $confirmed))
            ->assertSessionHas('error', 'Only a delivery order that is out for delivery can be marked as received.');
        $this->assertNull($confirmed->fresh()->delivery_received_at);

        $event = $this->deliveryBooking('event_in_progress', paid: 2000);
        $event->update(['event_type' => 'wedding']);
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.delivery-received', $event))
            ->assertSessionHas('error');
        $this->assertSame('event_in_progress', $event->fresh()->status);

        $delivery = $this->deliveryBooking('event_in_progress', paid: 5000);
        $this->actingAs($this->admin)->post(route('admin.bookings.delivery-received', $delivery));
        $this->actingAs($this->admin)
            ->post(route('admin.bookings.delivery-received', $delivery))
            ->assertSessionHas('error', 'This delivery has already been marked as received.');
    }

    public function test_other_users_cannot_mark_a_delivery_received(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 5000);

        $stranger = $this->user('client');
        $this->actingAs($stranger)->post(route('bookings.delivery-received', $booking))->assertForbidden();
        $this->actingAs($this->user('staff'))->post(route('admin.bookings.delivery-received', $booking))->assertForbidden();

        $this->assertSame('event_in_progress', $booking->fresh()->status);
    }

    public function test_delivery_cannot_take_the_event_completion_or_return_audit_path(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 5000);

        $this->actingAs($this->admin)
            ->put(route('admin.bookings.update', $booking), $this->formFields($booking) + ['action' => 'mark_event_completed'])
            ->assertSessionHas('error');
        $this->assertSame('event_in_progress', $booking->fresh()->status);

        $this->actingAs($this->admin)->post(route('admin.bookings.delivery-received', $booking));
        $this->actingAs($this->admin)
            ->post(route('admin.return-tracking.start', $booking))
            ->assertSessionHas('error');
        $this->assertFalse(AssetReturn::where('booking_id', $booking->id)->exists());
    }

    public function test_admin_page_flags_the_delivery_and_offers_mark_item_received(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 2000);

        $this->actingAs($this->admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Delivery order · No return audit')
            ->assertSee(route('admin.bookings.delivery-received', $booking), false)
            ->assertSee('Mark Item Received')
            ->assertDontSee('Mark Event Completed')
            ->assertDontSee('Manage Return Audit');
    }

    public function test_client_page_shows_item_received_while_out_for_delivery(): void
    {
        $booking = $this->deliveryBooking('event_in_progress', paid: 2000);

        $this->actingAs($this->clientUser)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Out for Delivery')
            ->assertSee(route('bookings.delivery-received', $booking), false)
            ->assertSee('Item Received');
    }

    private function deliveryBooking(string $status, float $paid = 0): Booking
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'delivery',
            'event_date' => now()->addDays(3)->toDateString(),
            'venue' => 'Block 8, Dagat-Dagatan, Caloocan',
            'status' => $status,
            'final_quoted_price' => 5000,
            'total_quoted' => 5000,
            'confirmed_at' => now(),
        ]);

        $item = InventoryItem::create(['name' => 'Vase ' . uniqid(), 'category' => 'props', 'is_perishable' => false, 'current_stock' => 50, 'unit_cost' => 50, 'min_stock' => 1, 'unit' => 'piece']);
        InventoryTransaction::create(['booking_id' => $booking->id, 'inventory_item_id' => $item->id, 'transaction_type' => 'booking_lock', 'quantity_change' => -2]);
        InventoryTransaction::create(['booking_id' => $booking->id, 'inventory_item_id' => $item->id, 'transaction_type' => 'dispatch', 'quantity_change' => -2]);

        if ($paid > 0) {
            Payment::create([
                'booking_id' => $booking->id,
                'amount' => $paid,
                'amount_paid' => $paid,
                'payment_option' => $paid >= 5000 ? 'full_payment' : 'downpayment',
                'payment_type' => 'gcash',
                'reference_number' => 'REF-' . uniqid(),
                'status' => $paid >= 5000 ? 'fully_paid' : 'downpayment_received',
                'verified_at' => now(),
            ]);
        }

        return $booking;
    }

    private function formFields(Booking $booking): array
    {
        return [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->format('Y-m-d'),
            'venue' => $booking->venue,
            'status' => $booking->status,
        ];
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
