<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReturnTrackingModernizedWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $clientUser;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Admin Specialist',
            'email' => 'admin.return.test@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Field Staff',
            'email' => 'staff.return.test@example.com',
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'name' => 'Evelyn Reed',
            'email' => 'evelyn.reed@example.com',
        ]);

        $this->client = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => 'Evelyn Reed',
            'phone' => '09171234567',
            'address' => '456 Blossom Lane, Quezon City',
        ]);
    }

    protected function createDispatchedFixture(float $quantity = 5, string $status = 'event_completed'): array
    {
        $item = InventoryItem::create([
            'name' => 'Heavy Metal Arch',
            'sku' => 'ARCH-001',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 10,
            'min_stock' => 2,
            'unit_cost' => 2500,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(2)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Grand Pavilion',
            'status' => $status,
            'total_quoted' => 15000,
            'confirmed_at' => now()->subDay(),
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 15000,
            'payment_option' => 'full_payment',
            'amount_paid' => 15000,
            'remaining_balance' => 0,
            'status' => 'fully_paid',
            'payment_type' => 'cash',
            'reference_number' => 'VERIF-PAY-' . $booking->id,
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$quantity,
            'transaction_type' => 'dispatch',
            'reason' => 'Admin Dispatch for booking #' . $booking->id,
            'performed_by' => $this->admin->id,
        ]);

        $item->update(['current_stock' => 10 - $quantity]);

        return [$booking, $item];
    }

    /**
     * Scenario A & B: Completed booking creates/opens the correct return record and does not duplicate.
     */
    public function test_completed_booking_creates_or_opens_correct_return_record_without_duplication(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(4, 'event_completed');

        // Viewing the list creates nothing (audit F11); Admin starts the audit explicitly.
        $this->actingAs($this->admin)->get(route('admin.return-tracking'))->assertOk();
        $this->assertSame(0, $booking->fresh()->returns()->count());

        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $response = $this->actingAs($this->admin)->get(route('admin.return-tracking'));
        $response->assertOk();
        $response->assertSee((string) $booking->id);
        $response->assertSee('Review Return');

        $this->assertSame(1, $booking->fresh()->returns()->count());
        $return = $booking->fresh()->returns()->first();
        $this->assertSame('Pending', $return->status);

        // Starting again does not duplicate return records
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $this->assertSame(1, $booking->fresh()->returns()->count());

        // Manage route returns existing return
        $manageResponse = $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $booking));
        $manageResponse->assertOk();
        $this->assertSame(1, $booking->fresh()->returns()->count());
    }

    /**
     * Scenario C: Expected quantities are correct.
     */
    public function test_expected_quantities_are_correctly_derived_from_authoritative_dispatches(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(6, 'event_completed');

        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $response = $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $booking));
        $response->assertOk();
        $response->assertSee('6'); // Dispatched expected quantity
        $response->assertSee($item->name);
    }

    /**
     * The audit screen lists items compactly (search, filters, pages) with each item's full form in
     * a pop-up; every field is still inside the one form, so saving posts the same data as before.
     */
    public function test_audit_screen_keeps_every_field_in_the_form_behind_compact_rows(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(6, 'event_completed');
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $returnItem = $booking->fresh()->returns()->first()->returnItems()->first();

        $html = $this->actingAs($this->admin)->get(route('admin.return-tracking.manage', $booking))->assertOk()->getContent();

        $this->assertStringContainsString('data-return-row="' . $returnItem->id . '"', $html);
        $this->assertStringContainsString('data-return-detail="' . $returnItem->id . '"', $html);
        $this->assertStringContainsString('id="returnItemSearch"', $html);
        $this->assertStringContainsString('Mark remaining as Good', $html);
        $this->assertStringContainsString('How reconciliation works', $html);

        $formStart = strpos($html, 'id="returnAuditForm"');
        $formEnd = strpos($html, '</form>', $formStart);
        foreach (['quantity_good', 'quantity_damaged', 'quantity_lost', 'charge_decision', 'damage_charge', 'notes', 'charge_reason'] as $field) {
            $position = strpos($html, 'name="items[' . $returnItem->id . '][' . $field . ']"');
            $this->assertNotFalse($position, $field . ' is missing.');
            $this->assertTrue($position > $formStart && $position < $formEnd, $field . ' must stay inside the audit form.');
        }
        $this->assertStringNotContainsString('SKU:', $html);
    }

    /**
     * Scenario D: Returned quantity validation works server-side.
     */
    public function test_returned_quantity_validation_works(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(5, 'event_completed');
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        // 1. Negative quantity rejected
        $respNegative = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => -1,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);
        $respNegative->assertSessionHasErrors(['items.' . $returnItem->id . '.quantity_good']);

        // 2. Quantity exceeding dispatched rejected
        $respExceed = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 6,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);
        $respExceed->assertSessionHas('error');
        $this->assertSame(0.0, (float) $returnItem->fresh()->quantity_returned);
    }

    /**
     * Scenario E: Good condition restores eligible inventory correctly.
     */
    public function test_good_condition_restores_eligible_inventory_correctly(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(5, 'event_completed');
        $initialStock = (float) $item->fresh()->current_stock; // 5

        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 5,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
            'notes' => 'All items returned in perfect condition.',
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame($initialStock + 5, (float) $item->fresh()->current_stock); // Restored to 10
        $this->assertSame('Completed', $return->fresh()->status);
        $this->assertSame('completed', $booking->fresh()->status);

        $tx = InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->first();
        $this->assertNotNull($tx);
        $this->assertSame(5.0, (float) $tx->quantity_change);
    }

    /**
     * Scenario F & G: Damaged and Lost conditions record correct inventory result and charges.
     */
    public function test_damaged_and_lost_conditions_record_charges_without_restoring_stock(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(4, 'event_completed');
        $initialStock = (float) $item->fresh()->current_stock; // 6

        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        // 2 damaged (charge 500), 2 lost (charge 1000)
        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 0,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 2,
                    'charge_decision' => 'charge',
                    'damage_charge' => 1500,
                    'charge_reason' => '2 broken frame rods, 2 missing base plates',
                ],
            ],
            'notes' => 'Damaged and lost assets assessed.',
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        // Stock must NOT be restored
        $this->assertSame($initialStock, (float) $item->fresh()->current_stock);
        $this->assertSame(1500.0, (float) $return->fresh()->total_damage_charge);
        $this->assertSame('Completed', $return->fresh()->status);
    }

    /**
     * Scenario H: Failed processing is atomic.
     */
    public function test_failed_processing_is_atomic_and_rolls_back(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(5, 'event_completed');
        $initialStock = (float) $item->fresh()->current_stock; // 5

        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        // First credit 5 good items
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 5,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);
        $this->assertSame(10.0, (float) $item->fresh()->current_stock);

        // Manually exhaust stock so downward correction cannot be fulfilled
        $item->update(['current_stock' => 1]);

        // Attempt downward correction from 5 good to 0 good (requires -5 decrement, but only 1 available)
        $failedResponse = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 0,
                    'quantity_damaged' => 5,
                    'quantity_lost' => 0,
                    'charge_decision' => 'charge',
                    'damage_charge' => 500,
                ],
            ],
        ]);

        $failedResponse->assertSessionHas('error');
        $this->assertSame(1.0, (float) $item->fresh()->current_stock); // Unchanged stock
        $this->assertSame(5.0, (float) $returnItem->fresh()->quantity_good); // Unchanged return item
    }

    /**
     * Scenario I: Reprocessing the same return cannot duplicate inventory or charges.
     */
    public function test_reprocessing_the_same_return_cannot_duplicate_inventory_or_charges(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(3, 'event_completed');
        $initialStock = (float) $item->fresh()->current_stock;

        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        $payload = [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 3,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ];

        // First submission
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), $payload);
        $this->assertSame($initialStock + 3, (float) $item->fresh()->current_stock);

        // Completed return is now archived; second attempt must be rejected
        $repeatResponse = $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), $payload);
        $repeatResponse->assertSessionHas('error', 'This return audit is completed and archived. Completed return records cannot be modified.');
        $this->assertSame($initialStock + 3, (float) $item->fresh()->current_stock);
    }

    /**
     * Scenario J: Final booking state is not incorrectly closed while required return processing remains unresolved.
     */
    public function test_final_booking_state_is_blocked_from_closure_while_return_is_unresolved(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(4, 'event_completed');
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();

        $this->assertSame('Pending', $return->status);

        // Attempting to finalize booking directly to completed without return completion must fail
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $booking), [
            'status' => 'completed',
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
        ]);

        $response->assertSessionHas('error', 'Cannot complete booking: material return audit has not been completed.');
        $this->assertNotSame('completed', $booking->fresh()->status);
    }

    /**
     * Scenario K: Search & filter UI returns expected records without altering business results.
     */
    public function test_search_and_filter_ui_returns_expected_records(): void
    {
        [$bookingA, $itemA] = $this->createDispatchedFixture(3, 'event_completed');

        // Create second booking with different client
        $clientB = Client::create([
            'email' => 'clientb@example.com',
            'full_name' => 'Sophia Chen',
            'phone' => '09189998888',
        ]);
        $bookingB = Booking::create([
            'client_id' => $clientB->id,
            'event_type' => 'corporate',
            'event_date' => now()->addDays(10)->toDateString(),
            'status' => 'event_completed',
            'total_quoted' => 20000,
        ]);
        InventoryTransaction::create([
            'inventory_item_id' => $itemA->id,
            'booking_id' => $bookingB->id,
            'quantity_change' => -2,
            'transaction_type' => 'dispatch',
            'performed_by' => $this->admin->id,
        ]);

        // Start both audits (viewing the list no longer creates them)
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $bookingA));
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $bookingB));

        // 1. Search booking ID of A
        $resSearchA = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['search' => (string) $bookingA->id]));
        $resSearchA->assertOk();
        $resSearchA->assertSee('#' . $bookingA->id);
        $resSearchA->assertDontSee('Sophia Chen');

        // 2. Search client name of B
        $resSearchB = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['search' => 'Sophia']));
        $resSearchB->assertOk();
        $resSearchB->assertSee('Sophia Chen');
        $resSearchB->assertDontSee('Evelyn Reed');

        // 3. Filter status
        $resStatus = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['status' => 'Pending']));
        $resStatus->assertOk();
        $resStatus->assertSee('#' . $bookingA->id);
        $resStatus->assertSee('#' . $bookingB->id);

        // 4. Empty search criteria shows operational empty state
        $resEmpty = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['search' => 'NonExistentXYZ999']));
        $resEmpty->assertOk();
        $resEmpty->assertSee('No matching returns found');
        $resEmpty->assertSee('Clear Filters');
    }

    /**
     * Scenario L: Unauthorized users cannot process returns.
     */
    public function test_unauthorized_users_cannot_process_returns(): void
    {
        [$booking, $item] = $this->createDispatchedFixture(3, 'event_completed');
        $this->actingAs($this->admin)->post(route('admin.return-tracking.start', $booking));
        $return = $booking->fresh()->returns()->first();
        $returnItem = $return->returnItems()->first();

        // Staff user is forbidden
        $responseStaff = $this->actingAs($this->staff)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 3,
                ],
            ],
        ]);
        $responseStaff->assertForbidden();

        // Client user is forbidden
        $responseClient = $this->actingAs($this->clientUser)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 3,
                ],
            ],
        ]);
        $responseClient->assertForbidden();
    }
}
