<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\StaffChecklistItem;
use App\Models\User;
use App\Services\InventoryDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase2b9AdminPreparationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $clientUser;
    protected Client $clientRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::create([
            'name' => 'Admin Officer',
            'email' => 'admin-officer-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $this->staff = User::create([
            'name' => 'Field Staff',
            'email' => 'staff-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $this->clientUser = User::create([
            'name' => 'Client Customer',
            'email' => 'client-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $this->clientRecord = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '09171234567',
            'address' => '789 Grand Avenue, Makati',
        ]);
    }

    protected function createBooking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'client_id' => $this->clientRecord->id,
            'staff_id' => $this->staff->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(7)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Manila Grand Pavilion',
            'status' => 'confirmed',
            'confirmed_at' => now(),
            'total_quoted' => 15000.00,
            'remaining_balance' => 0.00,
        ], $attributes));
    }

    protected function createReusableItem(string $name, float $stock = 50, float $cost = 100.00): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => 'props',
            'is_perishable' => false,
            'current_stock' => $stock,
            'unit_cost' => $cost,
            'unit' => 'piece',
            'min_stock' => 5,
        ]);
    }

    protected function reserveItemForBooking(Booking $booking, InventoryItem $item, int $quantity): BookingItem
    {
        $bookingItem = BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => $quantity,
            'quoted_unit_price' => $item->unit_cost,
            'confirmed_at' => now(),
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$quantity,
            'transaction_type' => 'booking_lock',
            'reason' => 'Reservation Lock #' . $booking->id,
            'performed_by' => $this->admin->id,
        ]);

        return $bookingItem;
    }

    /*
    |--------------------------------------------------------------------------
    | Part A: Staff Preparation Checklist Visibility Tests
    |--------------------------------------------------------------------------
    */

    public function test_admin_can_view_checklist_items_belonging_to_the_booking(): void
    {
        $booking = $this->createBooking();

        StaffChecklistItem::create([
            'booking_id' => $booking->id,
            'key' => 'prep-materials',
            'title' => 'Prepare confirmed event materials',
            'is_completed' => false,
            'notes' => 'Checked arch pieces and vases',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Staff Preparation Checklist');
        $response->assertSee('Prepare confirmed event materials');
        $response->assertSee('Checked arch pieces and vases');
        $response->assertSee('Pending completion');
        $response->assertSee('Preparation Incomplete');
        $response->assertSee('Read-only');
    }

    public function test_completed_and_incomplete_states_are_displayed_correctly_with_completion_timestamps(): void
    {
        $booking = $this->createBooking();

        $completedTime = now()->subMinutes(30);

        StaffChecklistItem::create([
            'booking_id' => $booking->id,
            'key' => 'review-details',
            'title' => 'Review event details and venue access',
            'is_completed' => true,
            'completed_by' => $this->staff->id,
            'completed_at' => $completedTime,
            'notes' => 'Venue permit secured',
        ]);

        StaffChecklistItem::create([
            'booking_id' => $booking->id,
            'key' => 'prep-flowers',
            'title' => 'Prepare floral centerpieces',
            'is_completed' => false,
            'notes' => 'Waiting for fresh flower delivery',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Review event details and venue access');
        $response->assertSee('Completed');
        $response->assertSee($completedTime->format('M d, Y h:i A'));
        $response->assertSee('by ' . $this->staff->name);
        $response->assertSee('Venue permit secured');

        $response->assertSee('Prepare floral centerpieces');
        $response->assertSee('Pending completion');
        $response->assertSee('1/2 complete');
        $response->assertSee('Preparation Incomplete');
    }

    public function test_all_checklist_items_completed_shows_preparation_complete_status(): void
    {
        $booking = $this->createBooking();

        StaffChecklistItem::create([
            'booking_id' => $booking->id,
            'key' => 'step-1',
            'title' => 'First Task',
            'is_completed' => true,
            'completed_by' => $this->staff->id,
            'completed_at' => now(),
        ]);

        StaffChecklistItem::create([
            'booking_id' => $booking->id,
            'key' => 'step-2',
            'title' => 'Second Task',
            'is_completed' => true,
            'completed_by' => $this->staff->id,
            'completed_at' => now(),
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('Preparation Complete');
        $response->assertSee('2/2 complete');
    }

    public function test_empty_checklist_state_is_handled_cleanly(): void
    {
        $booking = $this->createBooking();

        $response = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));

        $response->assertOk();
        $response->assertSee('No preparation checklist items recorded');
        $response->assertSee('Checklist items will appear once operational staff accesses this assigned event.');
    }

    public function test_checklist_records_from_another_booking_are_not_exposed(): void
    {
        $bookingA = $this->createBooking(['venue' => 'Venue A']);
        $bookingB = $this->createBooking(['venue' => 'Venue B']);

        StaffChecklistItem::create([
            'booking_id' => $bookingA->id,
            'key' => 'task-a',
            'title' => 'Clean Gold Arch for Booking A',
            'is_completed' => false,
        ]);

        StaffChecklistItem::create([
            'booking_id' => $bookingB->id,
            'key' => 'task-b',
            'title' => 'Check Wooden Crates for Booking B',
            'is_completed' => false,
        ]);

        $responseA = $this->actingAs($this->admin)->get(route('admin.bookings.show', $bookingA));
        $responseA->assertOk();
        $responseA->assertSee('Clean Gold Arch for Booking A');
        $responseA->assertDontSee('Check Wooden Crates for Booking B');

        $responseB = $this->actingAs($this->admin)->get(route('admin.bookings.show', $bookingB));
        $responseB->assertOk();
        $responseB->assertSee('Check Wooden Crates for Booking B');
        $responseB->assertDontSee('Clean Gold Arch for Booking A');
    }

    public function test_staff_and_clients_cannot_access_the_admin_booking_screen(): void
    {
        $booking = $this->createBooking();

        // Guest redirected to login
        $this->get(route('admin.bookings.show', $booking))
            ->assertRedirect(route('login'));

        // Client forbidden (403)
        $this->actingAs($this->clientUser)
            ->get(route('admin.bookings.show', $booking))
            ->assertForbidden();

        // Staff forbidden (403)
        $this->actingAs($this->staff)
            ->get(route('admin.bookings.show', $booking))
            ->assertForbidden();
    }

    public function test_existing_staff_checklist_updates_continue_to_work(): void
    {
        $booking = $this->createBooking();

        $checklist = StaffChecklistItem::create([
            'booking_id' => $booking->id,
            'key' => 'review-details',
            'title' => 'Review event details and venue access',
            'is_completed' => false,
        ]);

        $response = $this->actingAs($this->staff)->put(
            route('staff.events.checklist.update', ['booking' => $booking->id, 'checklist' => $checklist->id]),
            [
                'is_completed' => 1,
                'notes' => 'Confirmed access at 8:00 AM with building security.',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Checklist item updated.');

        $this->assertDatabaseHas('staff_checklist_items', [
            'id' => $checklist->id,
            'is_completed' => 1,
            'completed_by' => $this->staff->id,
            'notes' => 'Confirmed access at 8:00 AM with building security.',
        ]);

        $this->assertNotNull($checklist->fresh()->completed_at);
    }

    /*
    |--------------------------------------------------------------------------
    | Part B: Admin Material Dispatch Action Tests
    |--------------------------------------------------------------------------
    */

    public function test_authorized_admin_can_dispatch_when_existing_prerequisites_are_satisfied(): void
    {
        $booking = $this->createBooking();
        $item = $this->createReusableItem('Candelabra Brass', 40);
        $this->reserveItemForBooking($booking, $item, 10);

        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $item->id, 'quantity' => 10],
                ],
                'reason' => 'Admin authorized field dispatch',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Items dispatched successfully.');

        // Current stock deducted by 10 (40 -> 30)
        $this->assertEquals(30, (float) $item->fresh()->current_stock);

        // Inventory transaction created
        $this->assertDatabaseHas('inventory_transactions', [
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'transaction_type' => 'dispatch',
            'quantity_change' => -10,
            'performed_by' => $this->admin->id,
            'reason' => 'Admin authorized field dispatch',
        ]);
    }

    public function test_partial_dispatch_remains_valid_and_leaves_remaining_reservation_intact(): void
    {
        $booking = $this->createBooking();
        $item = $this->createReusableItem('Velvet Drapes', 20);
        $this->reserveItemForBooking($booking, $item, 8);

        // Step 1: Partial dispatch of 3 units
        $response1 = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $item->id, 'quantity' => 3],
                ],
                'reason' => 'Early truck dispatch',
            ]
        );

        $response1->assertRedirect();
        $response1->assertSessionHas('success');
        $this->assertEquals(17, (float) $item->fresh()->current_stock); // 20 - 3

        // Verify Admin review view displays 3 dispatched and 5 outstanding
        $viewResponse = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Velvet Drapes');
        $viewResponse->assertSee('8'); // Locked
        $viewResponse->assertSee('3'); // Dispatched
        $viewResponse->assertSee('5'); // Outstanding

        // Step 2: Dispatch the remaining 5 units
        $response2 = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $item->id, 'quantity' => 5],
                ],
                'reason' => 'Second truck dispatch',
            ]
        );

        $response2->assertRedirect();
        $response2->assertSessionHas('success');
        $this->assertEquals(12, (float) $item->fresh()->current_stock); // 17 - 5

        // View now shows fully dispatched badge
        $viewResponse2 = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));
        $viewResponse2->assertOk();
        $viewResponse2->assertSee('Fully Dispatched');
    }

    public function test_dispatch_exceeding_outstanding_reservation_is_rejected_safely(): void
    {
        $booking = $this->createBooking();
        $item = $this->createReusableItem('Glass Table Runner', 50);
        $this->reserveItemForBooking($booking, $item, 5);

        // Attempt to dispatch 7 units when only 5 are reserved
        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $item->id, 'quantity' => 7],
                ],
                'reason' => 'Excessive dispatch attempt',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertMatchesRegularExpression('/only 5( \S+)? are reserved for this booking/', session('error'));

        // Current stock untouched
        $this->assertEquals(50, (float) $item->fresh()->current_stock);
        $this->assertDatabaseMissing('inventory_transactions', [
            'booking_id' => $booking->id,
            'transaction_type' => 'dispatch',
        ]);
    }

    public function test_dispatch_exceeding_physical_stock_is_rejected_safely(): void
    {
        $booking = $this->createBooking();
        $item = $this->createReusableItem('Ceramic Urn', 3);
        $this->reserveItemForBooking($booking, $item, 5);

        // Attempt to dispatch 5 units when only 3 are physically in stock
        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $item->id, 'quantity' => 5],
                ],
                'reason' => 'Stock-exceeding dispatch attempt',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('in stock. Receive stock in Inventory Management, or dispatch fewer.', session('error'));

        // Current stock untouched
        $this->assertEquals(3, (float) $item->fresh()->current_stock);
    }

    public function test_unauthorized_users_cannot_dispatch_via_admin_route(): void
    {
        $booking = $this->createBooking();
        $item = $this->createReusableItem('Iron Arch', 10);
        $this->reserveItemForBooking($booking, $item, 2);

        $payload = [
            'items' => [
                ['inventory_item_id' => $item->id, 'quantity' => 2],
            ],
            'reason' => 'Unauthorized attempt',
        ];

        // Guest redirected to login
        $this->post(route('admin.bookings.dispatch', $booking), $payload)
            ->assertRedirect(route('login'));

        // Client forbidden
        $this->actingAs($this->clientUser)
            ->post(route('admin.bookings.dispatch', $booking), $payload)
            ->assertForbidden();

        // Staff forbidden from admin route
        $this->actingAs($this->staff)
            ->post(route('admin.bookings.dispatch', $booking), $payload)
            ->assertForbidden();

        // Physical stock remains unchanged
        $this->assertEquals(10, (float) $item->fresh()->current_stock);
    }

    public function test_invalid_booking_states_cannot_bypass_existing_dispatch_rules(): void
    {
        $invalidStatuses = ['completed', 'event_completed', 'cancelled', 'declined', 'pending_return'];

        foreach ($invalidStatuses as $status) {
            $booking = $this->createBooking(['status' => $status]);
            $item = $this->createReusableItem('Pedestal Stand ' . $status, 20);
            $this->reserveItemForBooking($booking, $item, 4);

            $response = $this->actingAs($this->admin)->post(
                route('admin.bookings.dispatch', $booking),
                [
                    'items' => [
                        ['inventory_item_id' => $item->id, 'quantity' => 4],
                    ],
                    'reason' => 'Invalid status dispatch',
                ]
            );

            $response->assertRedirect();
            $response->assertSessionHas('error');
            $this->assertStringContainsString("Booking #{$booking->id} cannot dispatch items while it is {$booking->fresh()->status_display_label}.", session('error'));
            $this->assertEquals(20, (float) $item->fresh()->current_stock);
        }
    }

    public function test_booking_without_confirmed_at_rejects_dispatch_safely(): void
    {
        $booking = $this->createBooking([
            'confirmed_at' => null,
            'status' => 'pending',
        ]);
        $item = $this->createReusableItem('Gold Charger Plates', 30);
        $this->reserveItemForBooking($booking, $item, 5);

        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $item->id, 'quantity' => 5],
                ],
                'reason' => 'Unconfirmed booking dispatch attempt',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');
        $this->assertStringContainsString('confirmation date is UNCLEAR', session('error'));
        $this->assertEquals(30, (float) $item->fresh()->current_stock);
    }

    public function test_failed_dispatch_does_not_leave_partial_inventory_mutations(): void
    {
        $booking = $this->createBooking();
        $itemValid = $this->createReusableItem('Valid Item', 20);
        $itemExceeding = $this->createReusableItem('Exceeding Item', 5);

        $this->reserveItemForBooking($booking, $itemValid, 10);
        $this->reserveItemForBooking($booking, $itemExceeding, 5);

        // Request dispatch with Item 1 valid (5 units), but Item 2 exceeding (99 units)
        $response = $this->actingAs($this->admin)->post(
            route('admin.bookings.dispatch', $booking),
            [
                'items' => [
                    ['inventory_item_id' => $itemValid->id, 'quantity' => 5],
                    ['inventory_item_id' => $itemExceeding->id, 'quantity' => 99],
                ],
                'reason' => 'Atomic multi-item test',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHas('error');

        // Due to DB::transaction, Item 1 current stock MUST NOT be deducted
        $this->assertEquals(20, (float) $itemValid->fresh()->current_stock);
        $this->assertEquals(5, (float) $itemExceeding->fresh()->current_stock);

        $this->assertDatabaseMissing('inventory_transactions', [
            'booking_id' => $booking->id,
            'transaction_type' => 'dispatch',
        ]);
    }

    public function test_admin_booking_view_renders_dispatch_controls_when_eligible_and_closed_when_completed(): void
    {
        $booking = $this->createBooking(['status' => 'confirmed']);
        $item = $this->createReusableItem('Chandelier Crystal', 15);
        $this->reserveItemForBooking($booking, $item, 6);

        // When booking is confirmed and has locked items
        $viewEligible = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));
        $viewEligible->assertOk();
        $viewEligible->assertSee('Inventory Dispatch &amp; Tracking', false);
        $viewEligible->assertSee('Reserved Materials Ready for Dispatch');
        $viewEligible->assertSee('Chandelier Crystal');
        $viewEligible->assertSee(route('admin.bookings.dispatch', $booking));
        $viewEligible->assertSee('Dispatch');

        // When booking transitions to completed
        $booking->update(['status' => 'completed']);
        $viewCompleted = $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking));
        $viewCompleted->assertOk();
        $viewCompleted->assertSee('Dispatch Closed (Completed)');
        $viewCompleted->assertSee('Dispatch unavailable');
    }
}
