<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ReturnItemEvidence;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MixedConditionMaterialReturnTest extends TestCase
{
    use RefreshDatabase;

    private function baseFixture(float $dispatchedQty = 10): array
    {
        $admin = User::create([
            'name' => 'Admin Test User',
            'email' => uniqid('admin-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);

        $staff = User::create([
            'name' => 'Staff Test User',
            'email' => uniqid('staff-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'staff',
            'email_verified_at' => now(),
        ]);

        $client = User::create([
            'name' => 'Client Test User',
            'email' => uniqid('client-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $clientRecord = \App\Models\Client::create([
            'user_id' => $client->id,
            'full_name' => 'Client Test User',
            'email' => $client->email,
            'phone' => '09123456789',
            'address' => 'Test Address',
        ]);

        $booking = Booking::create([
            'client_id' => $clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Grand Pavilion',
            'status' => 'event_completed',
            'total_quoted' => 1500,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Banquet Chairs',
            'category' => 'furniture',
            'is_perishable' => false,
            'current_stock' => 0,
            'min_stock' => 5,
            'unit_cost' => 150,
            'unit' => 'piece',
        ]);

        $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => $dispatchedQty,
            'quoted_unit_price' => 150,
            'is_ai_suggested' => false,
            'confirmed_at' => now(),
        ]);

        // Deduct stock upon dispatch
        InventoryTransaction::create([
            'inventory_item_id' => $inventoryItem->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$dispatchedQty,
            'transaction_type' => 'dispatch',
            'reason' => 'Event Dispatch',
            'performed_by' => $admin->id,
        ]);

        // Open the return record, as ending the event does
        app(\App\Services\ReturnRecordService::class)->openFor($booking);
        $return = $booking->fresh()->returns()->firstOrFail();
        $returnItem = $return->returnItems()->firstOrFail();

        return [$admin, $staff, $client, $booking->fresh(), $inventoryItem->fresh(), $return, $returnItem];
    }

    /**
     * 1. 10 dispatched, 8 good, 2 damaged.
     */
    public function test_10_dispatched_8_good_2_damaged_restores_only_good_and_completes(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                    'notes' => '8 in great shape, 2 with minor leg cracks.',
                ],
            ],
            'notes' => 'Admin assessment completed.',
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_stock);

        $returnItem->refresh();
        $this->assertSame(8.0, (float) $returnItem->quantity_good);
        $this->assertSame(2.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(0.0, (float) $returnItem->quantity_lost);
        $this->assertSame(10.0, (float) $returnItem->quantity_returned);
        $this->assertSame('mixed', $returnItem->condition);
        $this->assertSame('Completed', $return->fresh()->status);

        $tx = InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->first();
        $this->assertNotNull($tx);
        $this->assertSame(8.0, (float) $tx->quantity_change);
    }

    /**
     * 2. 10 dispatched, 8 good, 2 lost.
     */
    public function test_10_dispatched_8_good_2_lost_restores_only_good_and_completes(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 2,
                    'charge_decision' => 'no_charge',
                    'notes' => '2 chairs left behind at venue.',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_stock);

        $returnItem->refresh();
        $this->assertSame(8.0, (float) $returnItem->quantity_good);
        $this->assertSame(0.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(2.0, (float) $returnItem->quantity_lost);
        $this->assertSame(8.0, (float) $returnItem->quantity_returned);
        $this->assertSame(10.0, (float) $returnItem->accounted_quantity);
        $this->assertSame('mixed', $returnItem->condition);
        $this->assertSame('Completed', $return->fresh()->status);
    }

    /**
     * 3. All 10 good.
     */
    public function test_all_10_good_restores_full_stock_and_completes(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);

        $returnItem->refresh();
        $this->assertSame(10.0, (float) $returnItem->quantity_good);
        $this->assertSame(0.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(0.0, (float) $returnItem->quantity_lost);
        $this->assertSame('good', $returnItem->condition);
        $this->assertSame('Completed', $return->fresh()->status);
    }

    /**
     * 4. All 10 damaged.
     */
    public function test_all_10_damaged_restores_zero_stock_and_records_damage_charge(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 0,
                    'quantity_damaged' => 10,
                    'quantity_lost' => 0,
                    'charge_decision' => 'charge',
                    'damage_charge' => 750,
                    'charge_reason' => 'All chairs water soaked and broken.',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame(0.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(0, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());

        $returnItem->refresh();
        $this->assertSame(0.0, (float) $returnItem->quantity_good);
        $this->assertSame(10.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(750.0, (float) $returnItem->damage_charge);
        $this->assertSame('damaged', $returnItem->condition);
        $this->assertSame('Completed', $return->fresh()->status);
    }

    /**
     * 5. Partial return with outstanding quantity.
     */
    public function test_partial_return_with_outstanding_quantity_leaves_return_partially_returned(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 6,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame(6.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame('Partially Returned', $return->fresh()->status);
    }

    /**
     * 6. Negative quantity rejection.
     */
    public function test_negative_quantity_is_rejected(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => -5,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);

        $response->assertSessionHasErrors();
        $this->assertSame(0.0, (float) $inventoryItem->fresh()->current_stock);
    }

    /**
     * 7. Quantity total exceeding dispatch rejection.
     */
    public function test_quantity_total_exceeding_dispatch_is_rejected(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 3, // Total 11 > 10
                    'quantity_lost' => 0,
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertSame(0.0, (float) $inventoryItem->fresh()->current_stock);
    }

    /**
     * 8. Return completion after all quantities are accounted for.
     */
    public function test_return_completion_after_all_quantities_are_accounted_for(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 5,
                    'quantity_damaged' => 3,
                    'quantity_lost' => 2,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame('Completed', $return->fresh()->status);
        $this->assertSame(10.0, (float) $returnItem->fresh()->accounted_quantity);
    }

    /**
     * 9. Inventory restoration occurs only for good quantities.
     */
    public function test_inventory_restoration_occurs_only_for_good_quantities(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 7,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 1,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);

        $this->assertSame(7.0, (float) $inventoryItem->fresh()->current_stock);
        $returnTx = InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->get();
        $this->assertCount(1, $returnTx);
        $this->assertSame(7.0, (float) $returnTx->first()->quantity_change);
    }

    /**
     * 10. Repeated Admin submission does not duplicate inventory changes.
     */
    public function test_repeated_admin_submission_does_not_duplicate_inventory_changes(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $payload = [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ];

        // First submission
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), $payload);
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(1, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());

        // Repeated submission
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), $payload);
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(1, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());
    }

    /**
     * 11. Editing a previous assessment correctly reverses or adjusts the prior stock effect.
     */
    public function test_editing_previous_assessment_correctly_adjusts_prior_stock_effect(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // First assessment: 8 good, 2 damaged
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_stock);

        // Edit assessment: Re-assessed as 6 good, 4 damaged (stock decreases by 2)
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 6,
                    'quantity_damaged' => 4,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);
        $this->assertSame(6.0, (float) $inventoryItem->fresh()->current_stock);

        // Damage adjustment transaction created
        $adjTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('transaction_type', 'damage')
            ->latest()
            ->first();
        $this->assertNotNull($adjTx);
        $this->assertSame(-2.0, (float) $adjTx->quantity_change);
    }

    /**
     * 12. Damage charge decision remains consistent with assessed quantities.
     */
    public function test_damage_charge_decision_remains_consistent_with_assessed_quantities(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // 12a: Damaged items assessed with manual charge
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 0,
                    'charge_decision' => 'charge',
                    'damage_charge' => 300,
                    'charge_reason' => 'Broken frame',
                ],
            ],
        ]);
        $returnItem->refresh();
        $this->assertSame(300.0, (float) $returnItem->damage_charge);
        $this->assertSame('charge', $returnItem->charge_decision);

        // 12b: Re-assessing to 10 good (0 damaged/lost) resets charge to 0 and no_charge
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);
        $returnItem->refresh();
        $this->assertSame(0.0, (float) $returnItem->damage_charge);
        $this->assertSame('no_charge', $returnItem->charge_decision);
    }

    /**
     * 13. Staff cannot perform Admin inventory reconciliation.
     */
    public function test_staff_cannot_perform_admin_inventory_reconciliation(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $response = $this->actingAs($staff)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                ],
            ],
        ]);

        $response->assertForbidden();
        $this->assertSame(0.0, (float) $inventoryItem->fresh()->current_stock);
    }

    /**
     * 14. Existing return records remain compatible with legacy single condition submission.
     */
    public function test_existing_return_records_remain_compatible(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // Submitting legacy payload with quantity_returned and condition
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 10,
                    'condition' => 'good',
                    'damage_charge' => 0,
                ],
            ],
        ]);

        $returnItem->refresh();
        $this->assertSame(10.0, (float) $returnItem->quantity_good);
        $this->assertSame(0.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(10.0, (float) $returnItem->quantity_returned);
        $this->assertSame('good', $returnItem->condition);
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame('Completed', $return->fresh()->status);
    }

    /**
     * 15. Zero-hardware return completion remains functional.
     */
    public function test_zero_hardware_return_completion_remains_functional(): void
    {
        $admin = User::create([
            'name' => 'Admin User',
            'email' => uniqid('admin-zero-') . '@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Garden',
            'status' => 'event_completed',
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending',
            'total_damage_charge' => 0,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'notes' => 'Zero-hardware event verified.',
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame('Completed', $return->fresh()->status);
    }

    /**
     * 16. Existing return evidence remains accessible.
     */
    public function test_existing_return_evidence_remains_accessible(): void
    {
        Storage::fake('private');
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        $file = UploadedFile::fake()->create('evidence.jpg', 100, 'image/jpeg');
        $storedPath = $file->store('evidence', 'private');

        $evidence = ReturnItemEvidence::create([
            'return_item_id' => $returnItem->id,
            'file_path' => $storedPath,
            'file_name' => 'evidence.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 100,
            'uploaded_by' => $staff->id,
        ]);

        $this->assertDatabaseHas('return_item_evidences', [
            'id' => $evidence->id,
            'return_item_id' => $returnItem->id,
            'file_path' => $storedPath,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.return-tracking.show', $return));
        $response->assertOk();
        $response->assertSee(route('secure.evidence.show', $evidence));
    }

    /**
     * 17. Quantity semantics: Staff and Admin displays remain consistent for physical vs accounted quantities.
     */
    public function test_quantity_semantics_staff_and_admin_display_remain_consistent(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);
        $booking->update(['staff_id' => $staff->id]);

        // Staff submits return: 8 good, 0 damaged, 2 lost
        $staffResponse = $this->actingAs($staff)->put(route('staff.events.return.update', $booking), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 2,
                ],
            ],
        ]);
        $staffResponse->assertSessionHas('success');

        $returnItem->refresh();
        $this->assertSame(8.0, (float) $returnItem->quantity_returned);
        $this->assertSame(2.0, (float) $returnItem->quantity_lost);
        $this->assertSame(10.0, (float) $returnItem->accounted_quantity);

        // Staff page renders physically returned quantity: 8
        $staffPage = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $staffPage->assertOk();
        $staffPage->assertSee('Returned physically: <strong class="text-slate-700">8</strong>', false);

        // Admin assesses the return: 8 good, 0 damaged, 2 lost
        $adminResponse = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 2,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);
        $adminResponse->assertRedirect(route('admin.return-tracking'));

        $returnItem->refresh();
        // Crucial: quantity_returned remains physical return 8, NOT overwritten to 10
        $this->assertSame(8.0, (float) $returnItem->quantity_returned);
        $this->assertSame(10.0, (float) $returnItem->accounted_quantity);

        // Staff page remains consistent post-Admin assessment
        $staffPageAfter = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $staffPageAfter->assertOk();
        $staffPageAfter->assertSee('Returned physically: <strong class="text-slate-700">8</strong>', false);
    }

    /**
     * 18. Legacy compatibility: Historical good return with zero split fields does not duplicate stock credit.
     */
    public function test_historical_good_return_with_zero_split_fields_does_not_receive_duplicate_stock_credit(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // Simulate historical record: quantity_returned = 10, condition = 'good', but split columns are 0
        $returnItem->update([
            'quantity_returned' => 10,
            'quantity_good' => 0,
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
            'condition' => 'good',
        ]);

        // Prior stock is already restored to 10 from legacy event
        $inventoryItem->update(['current_stock' => 10]);

        // Admin performs assessment with new split fields: 10 good
        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));

        // Stock must remain 10.0, NOT double-credited to 20.0!
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);
        $this->assertSame(0, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'return')->count());
    }

    /**
     * 19. Inventory correction: Valid downward correction succeeds when stock is sufficient.
     */
    public function test_downward_inventory_correction_succeeds_when_stock_is_sufficient(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // First assessment: 10 good -> stock restored to 10
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);

        // Later correction: Admin changes to 8 good, 2 damaged -> requires -2 stock
        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 8,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.return-tracking'));
        $this->assertSame(8.0, (float) $inventoryItem->fresh()->current_stock);

        $damageTx = InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'damage')->first();
        $this->assertNotNull($damageTx);
        $this->assertSame(-2.0, (float) $damageTx->quantity_change);
    }

    /**
     * 20. Inventory correction: Insufficient stock rejects correction and rolls back atomically.
     */
    public function test_downward_inventory_correction_is_rejected_when_stock_is_insufficient_and_rolls_back_atomically(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // First assessment: 10 good -> stock restored to 10
        $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 10,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);
        $this->assertSame(10.0, (float) $inventoryItem->fresh()->current_stock);

        // 9 items are rented/dispatched to another event -> current_stock drops to 1
        $inventoryItem->update(['current_stock' => 1]);

        // Admin attempts downward adjustment to 6 good, 4 damaged (requires -4 stock, but only 1 available!)
        $response = $this->actingAs($admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 6,
                    'quantity_damaged' => 4,
                    'quantity_lost' => 0,
                    'charge_decision' => 'no_charge',
                ],
            ],
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('Insufficient available inventory stock', session('error'));

        // Inventory stock must remain untouched at 1.0 (NEVER negative!)
        $this->assertSame(1.0, (float) $inventoryItem->fresh()->current_stock);

        // ReturnItem was rolled back atomically: retains quantity_good = 10
        $returnItem->refresh();
        $this->assertSame(10.0, (float) $returnItem->quantity_good);
        $this->assertSame(0.0, (float) $returnItem->quantity_damaged);
        $this->assertSame(10.0, (float) $returnItem->quantity_returned);

        // No damage transaction created
        $this->assertSame(0, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'damage')->count());
    }

    /**
     * 21. Zero hardware staff empty state message.
     */
    public function test_zero_hardware_empty_state_shows_accurate_message_on_staff_event_page(): void
    {
        [$admin, $staff, $client, $booking, $inventoryItem, $return, $returnItem] = $this->baseFixture(10);

        // Create a new booking with zero hardware
        $zeroBooking = Booking::create([
            'client_id' => $booking->client_id,
            'staff_id' => $staff->id,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(2)->toDateString(),
            'venue' => 'Private Room',
            'status' => 'event_completed',
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $zeroBooking));
        $response->assertOk();
        $response->assertSee('No non-perishable returnable hardware materials were dispatched for this event.');
        $response->assertDontSee('Return recording becomes available when this event reaches the return workflow.');
    }
}
