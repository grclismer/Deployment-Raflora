<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Payment;
use App\Models\ReturnItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminReturnTrackingAccountabilityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;
    protected User $inspector;
    protected User $clientUser;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
            'name' => 'Raflora Admin',
            'email' => 'admin.accountability@example.com',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Juan Reyes',
            'email' => 'juan.reyes@example.com',
        ]);

        $this->inspector = User::factory()->create([
            'role' => 'admin',
            'name' => 'Diego Martinez',
            'email' => 'diego.martinez@example.com',
        ]);

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'name' => 'Maria Clara Santos',
            'email' => 'clara.santos@demo.com',
        ]);

        $this->client = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => 'Maria Clara Santos',
            'phone' => '09175558899',
            'address' => '123 Heritage Way, Vigan City',
        ]);
    }

    protected function createReturnFixture(float $dispatchedQty = 5): array
    {
        $item = InventoryItem::create([
            'name' => 'Glass Vase - Large',
            'sku' => 'VASE-001',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 20,
            'min_stock' => 2,
            'unit_cost' => 1200,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(2)->toDateString(),
            'event_time' => '14:30',
            'venue' => 'Grand Ballroom',
            'status' => 'event_completed',
            'total_quoted' => 10000,
            'confirmed_at' => now()->subDay(),
            'staff_id' => $this->staff->id,
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'amount' => 10000,
            'payment_option' => 'full_payment',
            'amount_paid' => 10000,
            'remaining_balance' => 0,
            'status' => 'fully_paid',
            'payment_type' => 'cash',
            'reference_number' => 'PAY-ACC-' . $booking->id,
            'verified_at' => now(),
            'verified_by' => $this->admin->id,
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -$dispatchedQty,
            'transaction_type' => 'dispatch',
            'reason' => 'Dispatched for booking #' . $booking->id,
            'performed_by' => $this->admin->id,
        ]);

        $item->update(['current_stock' => 20 - $dispatchedQty]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'assigned_staff_id' => null,
            'inspected_by' => null,
            'status' => 'Pending',
            'total_damage_charge' => 0,
            'approval_status' => 'not_required',
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $item->id,
            'quantity_returned' => 0,
            'quantity_good' => 0,
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
            'condition' => 'pending',
            'damage_charge' => 0,
            'charge_decision' => 'pending',
        ]);

        return [$booking, $return, $item, $returnItem];
    }

    /**
     * Test 1-5: Data persistence, null states, foreign keys
     */
    public function test_staff_and_inspector_assignment_persists_correctly(): void
    {
        /** @var AssetReturn $return */
        [, $return] = $this->createReturnFixture(5);

        // Initially unassigned
        $this->assertNull($return->assigned_staff_id);
        $this->assertNull($return->inspected_by);
        $this->assertNull($return->approved_by);
        $this->assertSame('not_required', $return->approval_status);

        // Admin assigns staff and inspector
        $response = $this->actingAs($this->admin)->put(route('admin.return-tracking.assign', $return), [
            'assigned_staff_id' => $this->staff->id,
            'inspector_id' => $this->inspector->id,
        ]);

        $response->assertSessionHas('success');
        $return->refresh();

        $this->assertSame($this->staff->id, $return->assigned_staff_id);
        $this->assertSame($this->staff->name, $return->assignedStaff->name);
        $this->assertSame($this->inspector->id, $return->inspected_by);
        $this->assertSame($this->inspector->name, $return->inspector->name);

        // Unassigning sets back to null
        $this->actingAs($this->admin)->put(route('admin.return-tracking.assign', $return), [
            'assigned_staff_id' => null,
            'inspector_id' => null,
        ]);

        $return->refresh();
        $this->assertNull($return->assigned_staff_id);
        $this->assertNull($return->inspected_by);
    }

    /**
     * Test 6-10: Authorization enforcement for assignments and approval
     */
    public function test_admin_can_assign_but_staff_and_guests_are_forbidden(): void
    {
        /** @var AssetReturn $return */
        [, $return] = $this->createReturnFixture(5);

        // Staff attempting to assign staff/inspector is rejected (403 forbidden or route redirect)
        $staffResponse = $this->actingAs($this->staff)->put(route('admin.return-tracking.assign', $return), [
            'assigned_staff_id' => $this->staff->id,
        ]);
        $staffResponse->assertForbidden();

        // Client attempting to assign is rejected
        $clientResponse = $this->actingAs($this->clientUser)->put(route('admin.return-tracking.assign', $return), [
            'assigned_staff_id' => $this->staff->id,
        ]);
        $clientResponse->assertForbidden();

        // Staff cannot approve adjudication
        $staffApproveResponse = $this->actingAs($this->staff)->put(route('admin.return-tracking.approve', $return), [
            'decision' => 'approved',
        ]);
        $staffApproveResponse->assertForbidden();

        // Assigning non-staff/non-admin user is rejected
        $invalidAssign = $this->actingAs($this->admin)->put(route('admin.return-tracking.assign', $return), [
            'assigned_staff_id' => $this->clientUser->id,
        ]);
        $invalidAssign->assertSessionHas('error');
    }

    /**
     * Test 11-12: Staff records return, inspector submits inspection, audits recorded
     */
    public function test_staff_records_return_and_inspector_submits_inspection(): void
    {
        /** @var Booking $booking */
        /** @var AssetReturn $return */
        /** @var ReturnItem $returnItem */
        [$booking, $return, , $returnItem] = $this->createReturnFixture(5);

        // Staff records returned physical quantities
        $staffResponse = $this->actingAs($this->staff)->put(route('staff.events.return.update', $booking), [
            'items' => [
                $returnItem->id => [
                    'quantity_good' => 4,
                    'quantity_damaged' => 1,
                    'quantity_lost' => 0,
                    'notes' => '1 vase has a chip on rim',
                ],
            ],
            'notes' => 'Received from venue staff',
        ]);
        $staffResponse->assertSessionHas('success');

        $return->refresh();
        $returnItem->refresh();
        $this->assertSame('Partially Returned', $return->status);
        $this->assertSame($this->staff->id, $return->assigned_staff_id);
        $this->assertSame(4.0, (float) $returnItem->quantity_good);
        $this->assertSame(1.0, (float) $returnItem->quantity_damaged);

        // Audit log created for staff record
        $staffAudit = AuditLog::where('action', 'return_quantities_recorded')
            ->where('entity_id', $return->id)
            ->first();
        $this->assertNotNull($staffAudit);
        $this->assertSame($this->staff->id, $staffAudit->user_id);

        // Inspector records condition observation with required photographic evidence
        $inspectorResponse = $this->actingAs($this->inspector)->put(route('staff.events.return.condition', $booking), [
            'items' => [
                $returnItem->id => [
                    'condition' => 'damaged',
                    'notes' => 'Chipped base requires replacement',
                    'evidence' => [\Illuminate\Http\UploadedFile::fake()->create('chipped_vase.jpg', 100, 'image/jpeg')],
                ],
            ],
        ]);
        $inspectorResponse->assertSessionHas('success');

        $return->refresh();
        // Since damaged items exist, approval_status becomes 'pending'
        $this->assertSame('pending', $return->approval_status);
        $this->assertSame($this->inspector->id, $return->inspected_by);

        // Audit log created for inspection
        $inspAudit = AuditLog::where('action', 'return_inspection_submitted')
            ->where('entity_id', $return->id)
            ->first();
        $this->assertNotNull($inspAudit);
        $this->assertSame($this->inspector->id, $inspAudit->user_id);
    }

    /**
     * Test 13-16: Good-only vs damaged/lost returns approval rules and adjudication
     */
    public function test_good_only_return_does_not_require_approval_while_damaged_return_requires_adjudication(): void
    {
        /** @var Booking $booking */
        /** @var AssetReturn $return */
        /** @var ReturnItem $returnItem */
        [$booking, $return, $item, $returnItem] = $this->createReturnFixture(5);

        // Reconcile as all good
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 5,
                    'quantity_good' => 5,
                    'quantity_damaged' => 0,
                    'quantity_lost' => 0,
                    'condition' => 'good',
                ],
            ],
        ]);

        $return->refresh();
        $this->assertSame('Completed', $return->status);
        $this->assertSame('not_required', $return->approval_status);

        // Now test damaged return requiring adjudication
        [, $returnDamaged, , $returnItemDamaged] = $this->createReturnFixture(4);

        // Inspector/staff logs damaged
        $returnDamaged->update([
            'approval_status' => 'pending',
            'total_damage_charge' => 500,
        ]);
        $returnItemDamaged->update([
            'quantity_good' => 3,
            'quantity_damaged' => 1,
            'condition' => 'damaged',
            'damage_charge' => 500,
            'charge_decision' => 'pending',
        ]);

        // Admin approves adjudication
        $approveResponse = $this->actingAs($this->admin)->put(route('admin.return-tracking.approve', $returnDamaged), [
            'decision' => 'approved',
            'reason' => 'Approved replacement fee of ₱500',
        ]);
        $approveResponse->assertSessionHas('success');

        $returnDamaged->refresh();
        $returnItemDamaged->refresh();
        $this->assertSame('approved', $returnDamaged->approval_status);
        $this->assertSame($this->admin->id, $returnDamaged->approved_by);
        $this->assertNotNull($returnDamaged->approved_at);
        $this->assertSame('charge', $returnItemDamaged->charge_decision);

        // Audit log created for approval
        $approveAudit = AuditLog::where('action', 'return_damage_approved')
            ->where('entity_id', $returnDamaged->id)
            ->first();
        $this->assertNotNull($approveAudit);
        $this->assertSame($this->admin->id, $approveAudit->user_id);
    }

    /**
     * Test 17-21: Inventory reconciliation correctness and atomicity
     */
    public function test_inventory_reconciliation_only_restores_good_items_and_records_audit(): void
    {
        /** @var Booking $booking */
        /** @var AssetReturn $return */
        /** @var InventoryItem $item */
        /** @var ReturnItem $returnItem */
        [$booking, $return, $item, $returnItem] = $this->createReturnFixture(10);
        $stockBefore = (float) $item->fresh()->current_stock; // 10

        // Reconcile: 7 good, 2 damaged, 1 lost
        $this->actingAs($this->admin)->put(route('admin.return-tracking.update', $return), [
            'items' => [
                $returnItem->id => [
                    'quantity_returned' => 9, // 7 good + 2 damaged physically returned
                    'quantity_good' => 7,
                    'quantity_damaged' => 2,
                    'quantity_lost' => 1,
                    'condition' => 'mixed',
                    'charge_decision' => 'charge',
                    'damage_charge' => 300,
                ],
            ],
        ]);

        $item->refresh();
        $return->refresh();

        // Exactly 7 units of good items should be added back to stock
        $this->assertSame($stockBefore + 7.0, (float) $item->current_stock);
        $this->assertSame('Completed', $return->status);
        $this->assertSame(300.0, (float) $return->total_damage_charge);

        // Check audit log for reconciliation
        $reconcileAudit = AuditLog::where('action', 'return_reconciled')
            ->where('entity_id', $return->id)
            ->first();
        $this->assertNotNull($reconcileAudit);
    }

    /**
     * Test 22-25: Action History records assignment, inspection, approval, and reconciliation events
     */
    public function test_action_history_timeline_captures_all_stages(): void
    {
        /** @var AssetReturn $return */
        [, $return] = $this->createReturnFixture(5);

        // 1. Assignment event
        $this->actingAs($this->admin)->put(route('admin.return-tracking.assign', $return), [
            'assigned_staff_id' => $this->staff->id,
            'inspector_id' => $this->inspector->id,
        ]);

        $staffAssignedLog = AuditLog::where('action', 'return_staff_assigned')->where('entity_id', $return->id)->first();
        $inspAssignedLog = AuditLog::where('action', 'return_inspector_assigned')->where('entity_id', $return->id)->first();
        $this->assertNotNull($staffAssignedLog);
        $this->assertNotNull($inspAssignedLog);
        $this->assertStringContainsString($this->staff->name, $staffAssignedLog->details);
        $this->assertStringContainsString($this->inspector->name, $inspAssignedLog->details);

        // 2. Approval event (a reported loss awaiting Admin's decision)
        $return->update(['approval_status' => 'pending']);
        $return->returnItems()->first()->update(['quantity_lost' => 1, 'condition' => 'lost', 'charge_decision' => 'pending']);
        $this->actingAs($this->admin)->put(route('admin.return-tracking.approve', $return), [
            'decision' => 'approved',
            'reason' => 'Loss verified by venue management',
        ]);

        $approvalLog = AuditLog::where('action', 'return_damage_approved')->where('entity_id', $return->id)->first();
        $this->assertNotNull($approvalLog);
        $this->assertStringContainsString('Loss verified', $approvalLog->details);
    }

    /**
     * Test 26-33: UI layout, accountability columns, search, filters, drawer rendering
     */
    public function test_return_list_ui_renders_accountability_columns_filters_and_drawer(): void
    {
        /** @var AssetReturn $return */
        /** @var Booking $booking */
        [$booking, $return] = $this->createReturnFixture(5);

        // Assign staff and inspector
        $return->update([
            'assigned_staff_id' => $this->staff->id,
            'inspected_by' => $this->inspector->id,
            'approval_status' => 'pending',
        ]);

        // Visit Return Tracking page
        $response = $this->actingAs($this->admin)->get(route('admin.return-tracking'));
        $response->assertOk();

        // 1. Header & Summary metrics
        $response->assertSee('OPERATIONS');
        $response->assertSee('Return Tracking');
        $response->assertSee('Track post-event asset returns, condition assessments, staff responsibility, and required damage/loss decisions.');
        $response->assertSee('Total Returns');
        $response->assertSee('All event returns');
        $response->assertSee('Pending Inspection');
        $response->assertSee('Awaiting inspection');
        $response->assertSee('Needs Approval');
        $response->assertSee('Damage/loss decision pending');
        $response->assertSee('Completed');
        $response->assertSee('Returns fully reconciled');

        // 2. Operational Table Columns
        $response->assertSee('Return');
        $response->assertSee('Client / Booking');
        $response->assertSee('Event');
        $response->assertSee('Status');
        $response->assertSee('Staff');
        $response->assertSee('Inspector');
        $response->assertSee('Approval');
        $response->assertSee('Action');

        // 3. Row contents
        $response->assertSee($return->reference);
        $response->assertSee((string) $booking->id);
        $response->assertSee('Maria Clara Santos');
        $response->assertSee('Juan Reyes');
        $response->assertSee('Diego Martinez');
        $response->assertSee('View');

        // 4. Drawer template rendered with tabs & operational guidance
        $response->assertSee('return-drawer-template-' . $return->id);
        $response->assertSee('Overview');
        $response->assertSee('Items (1)');
        $response->assertSee('Staff & Accountability', false);
        $response->assertSee('History');
        $response->assertSee('Inventory Reconciliation Guide');
        $response->assertSee('Open Return Audit →');

        // 5. Search test: search booking ID
        $searchResponse = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['search' => (string) $booking->id]));
        $searchResponse->assertOk();
        $searchResponse->assertSee($return->reference);

        // Search test: search by RT reference
        $searchRtResponse = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['search' => $return->reference]));
        $searchRtResponse->assertOk();
        $searchRtResponse->assertSee($return->reference);

        // 6. Filter by staff
        $filterStaff = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['staff' => $this->staff->id]));
        $filterStaff->assertOk();
        $filterStaff->assertSee($return->reference);

        // 7. Filter by inspector
        $filterInspector = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['inspector' => $this->inspector->id]));
        $filterInspector->assertOk();
        $filterInspector->assertSee($return->reference);

        // 8. Filter by approval status
        $filterApproval = $this->actingAs($this->admin)->get(route('admin.return-tracking', ['approval' => 'pending']));
        $filterApproval->assertOk();
        $filterApproval->assertSee($return->reference);
    }
}
