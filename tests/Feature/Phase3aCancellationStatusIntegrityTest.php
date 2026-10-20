<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Phase3aCancellationStatusIntegrityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $clientUser;
    protected Client $clientRecord;
    protected User $otherClientUser;
    protected Client $otherClientRecord;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => 'admin', 'name' => 'Admin Officer']);
        $this->clientUser = User::factory()->create(['role' => 'client', 'name' => 'Client One']);
        $this->clientRecord = Client::create([
            'user_id' => $this->clientUser->id,
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '09171234567',
            'address' => '123 Test Street, Manila',
        ]);

        $this->otherClientUser = User::factory()->create(['role' => 'client', 'name' => 'Client Two']);
        $this->otherClientRecord = Client::create([
            'user_id' => $this->otherClientUser->id,
            'email' => $this->otherClientUser->email,
            'full_name' => $this->otherClientUser->name,
            'phone' => '09187654321',
            'address' => '456 Other Street, QC',
        ]);
    }

    public function test_cancellation_request_captures_actual_prior_status(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'confirmed',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Sky Garden',
            'total_quoted' => 15000,
        ]);

        $response = $this->actingAs($this->clientUser)->post(route('bookings.request-cancellation', $booking), [
            'cancellation_reason' => 'Family emergency requires date cancellation',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('cancellation_requested', $booking->status);
        $this->assertSame('confirmed', $booking->pre_cancellation_status);
        $this->assertSame('Family emergency requires date cancellation', $booking->cancellation_reason);

        // Verify message created
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $booking->id,
            'sender_type' => 'client',
            'message' => 'Requested cancellation: Family emergency requires date cancellation',
        ]);

        // Verify audit log created
        $this->assertDatabaseHas('audit_logs', [
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
            'module' => 'booking',
            'action' => 'cancellation_requested',
        ]);
    }

    public function test_eligible_quotation_stage_cancellation_can_be_submitted(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'quotation_sent',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Grand Pavilion',
            'total_quoted' => 8000,
        ]);

        $response = $this->actingAs($this->clientUser)->post(route('bookings.request-cancellation', $booking), [
            'cancellation_reason' => 'Decided on a different venue package',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('cancellation_requested', $booking->status);
        $this->assertSame('quotation_sent', $booking->pre_cancellation_status);
    }

    public function test_denied_quotation_stage_cancellation_transitions_to_change_requested(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => 'quotation_sent',
            'cancellation_reason' => 'Budget constraints',
            'event_type' => 'debut',
            'event_date' => now()->addDays(25)->toDateString(),
            'venue' => 'Manila Hall',
            'total_quoted' => 12000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'deny',
            'admin_note' => 'We can adjust the flower package to meet your budget.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('change_requested', $booking->status);
        $this->assertNull($booking->pre_cancellation_status);
        $this->assertNull($booking->cancellation_reason);

        // Client notification generated
        $this->assertDatabaseHas('client_notifications', [
            'booking_id' => $booking->id,
            'title' => 'Cancellation Denied',
        ]);

        // Admin note recorded in booking messages
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $booking->id,
            'sender_type' => 'admin',
            'message' => 'We can adjust the flower package to meet your budget.',
        ]);
    }

    public function test_denied_confirmed_booking_restores_confirmed(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => 'confirmed',
            'cancellation_reason' => 'Changed mind',
            'event_type' => 'corporate',
            'event_date' => now()->addDays(40)->toDateString(),
            'venue' => 'Plaza Center',
            'confirmed_at' => now()->subDays(2),
            'total_quoted' => 30000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'deny',
            'admin_note' => 'Preparations already contracted, proceeding as scheduled.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('confirmed', $booking->status);
        $this->assertNull($booking->pre_cancellation_status);
        $this->assertNull($booking->cancellation_reason);
    }

    public function test_denied_in_preparation_booking_restores_in_preparation(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => 'in_preparation',
            'cancellation_reason' => 'Late cancellation request',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'Sunset Beach Club',
            'confirmed_at' => now()->subDays(10),
            'preparation_status' => 'in_preparation',
            'total_quoted' => 50000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'deny',
            'admin_note' => 'Materials already assembled.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('in_preparation', $booking->status);
        $this->assertNull($booking->pre_cancellation_status);
        $this->assertNull($booking->cancellation_reason);
    }

    public function test_ineligible_or_terminal_booking_cancellation_is_rejected(): void
    {
        $terminalStatuses = ['event_in_progress', 'completed', 'cancelled', 'declined', 'cancellation_requested'];

        foreach ($terminalStatuses as $badStatus) {
            $booking = Booking::create([
                'client_id' => $this->clientRecord->id,
                'status' => $badStatus,
                'event_type' => 'wedding',
                'event_date' => now()->addDays(10)->toDateString(),
                'venue' => 'Manila Hotel',
                'total_quoted' => 20000,
            ]);

            $response = $this->actingAs($this->clientUser)->post(route('bookings.request-cancellation', $booking), [
                'cancellation_reason' => 'Attempting cancellation in bad state',
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('error');
            $this->assertSame($badStatus, $booking->fresh()->status);
        }
    }

    public function test_admin_decision_on_booking_not_awaiting_review_is_rejected(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'confirmed',
            'event_type' => 'anniversary',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Grand Ballroom',
            'total_quoted' => 18000,
        ]);

        // Attempt denial on non-pending booking
        $responseDeny = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'deny',
            'admin_note' => 'Invalid attempt',
        ]);
        $responseDeny->assertRedirect();
        $responseDeny->assertSessionHas('error', 'Booking is not awaiting cancellation review.');
        $this->assertSame('confirmed', $booking->fresh()->status);

        // Attempt approval on non-pending booking
        $responseApprove = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'approve',
            'admin_note' => 'Invalid attempt',
        ]);
        $responseApprove->assertRedirect();
        $responseApprove->assertSessionHas('error', 'Booking is not awaiting cancellation review.');
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_approved_cancellation_releases_valid_active_inventory_reservation_exactly_once(): void
    {
        $arch = InventoryItem::create([
            'name' => 'Wrought Iron Arch',
            'category' => 'hardware',
            'is_perishable' => false,
            'current_stock' => 5,
            'unit_cost' => 3500,
            'min_stock' => 1,
            'unit' => 'piece',
        ]);

        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'confirmed',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'Garden Estate',
            'confirmed_at' => now()->subDay(),
            'total_quoted' => 12000,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $arch->id,
            'item_name' => $arch->name,
            'quantity' => 2,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);

        // Reservation transaction (lock)
        InventoryTransaction::create([
            'inventory_item_id' => $arch->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'booking_lock',
            'performed_by' => $this->admin->id,
            'reason' => 'Confirmed booking reservation',
        ]);

        // Verify net available is reduced from 5 to 3
        $this->assertSame(3.0, (float) $arch->fresh()->net_available);

        // Client requests cancellation
        $this->actingAs($this->clientUser)->post(route('bookings.request-cancellation', $booking), [
            'cancellation_reason' => 'Event venue cancelled by landlord',
        ]);

        $booking->refresh();
        $this->assertSame('cancellation_requested', $booking->status);
        $this->assertSame('confirmed', $booking->pre_cancellation_status);

        // Admin approves cancellation
        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'approve',
            'admin_note' => 'Cancellation approved with reservation release',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);

        // Active reservation released back: net available is restored to 5
        $this->assertSame(5.0, (float) $arch->fresh()->net_available);

        // Verify booking_release transaction was created
        $releaseTx = InventoryTransaction::where('booking_id', $booking->id)
            ->where('transaction_type', 'booking_release')
            ->first();
        $this->assertNotNull($releaseTx);
        $this->assertSame(2.0, (float) $releaseTx->quantity_change);
    }

    public function test_repeated_approval_does_not_duplicate_inventory_release(): void
    {
        $item = InventoryItem::create([
            'name' => 'Vintage Chandelier',
            'category' => 'lighting',
            'is_perishable' => false,
            'current_stock' => 4,
            'unit_cost' => 6000,
            'min_stock' => 1,
            'unit' => 'unit',
        ]);

        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'confirmed',
            'event_type' => 'gala',
            'event_date' => now()->addDays(14)->toDateString(),
            'venue' => 'Grand Hall',
            'confirmed_at' => now()->subDays(2),
            'total_quoted' => 20000,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $item->id,
            'item_name' => $item->name,
            'quantity' => 2,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);

        InventoryTransaction::create([
            'inventory_item_id' => $item->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'booking_lock',
            'performed_by' => $this->admin->id,
            'reason' => 'Lock for gala',
        ]);

        $this->assertSame(2.0, (float) $item->fresh()->net_available);

        // Transition to cancellation_requested
        $booking->update([
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => 'confirmed',
        ]);

        // First approval succeeds and releases stock
        $first = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'approve',
        ]);
        $first->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $this->assertSame(4.0, (float) $item->fresh()->net_available);
        $this->assertSame(1, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'booking_release')->count());

        // Second approval attempt on already cancelled booking is rejected
        $second = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'approve',
        ]);
        $second->assertSessionHas('error', 'Booking is not awaiting cancellation review.');

        // Verify release was not duplicated and available stock remains exactly 4
        $this->assertSame(1, InventoryTransaction::where('booking_id', $booking->id)->where('transaction_type', 'booking_release')->count());
        $this->assertSame(4.0, (float) $item->fresh()->net_available);
    }

    public function test_dispatched_materials_remain_subject_to_return_recovery_accountability(): void
    {
        $speaker = InventoryItem::create([
            'name' => 'Portable PA Soundbox',
            'category' => 'electronics',
            'is_perishable' => false,
            'current_stock' => 10,
            'unit_cost' => 8000,
            'min_stock' => 1,
            'unit' => 'unit',
        ]);

        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => 'in_preparation',
            'cancellation_reason' => 'Client emergency post-dispatch',
            'event_type' => 'seminar',
            'event_date' => now()->addDays(2)->toDateString(),
            'venue' => 'Convention Hall',
            'confirmed_at' => now()->subDays(5),
            'total_quoted' => 16000,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $speaker->id,
            'item_name' => $speaker->name,
            'quantity' => 2,
            'confirmed_at' => now(),
            'procurement_status' => 'confirmed',
        ]);

        // Initial booking reservation lock
        InventoryTransaction::create([
            'inventory_item_id' => $speaker->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'booking_lock',
            'performed_by' => $this->admin->id,
            'reason' => 'Preparation reservation lock',
        ]);

        // Dispatched 2 units (physical stock decreased by 2)
        $speaker->decrement('current_stock', 2);
        InventoryTransaction::create([
            'inventory_item_id' => $speaker->id,
            'booking_id' => $booking->id,
            'quantity_change' => -2,
            'transaction_type' => 'dispatch',
            'performed_by' => $this->admin->id,
            'reason' => 'Dispatched to hall',
        ]);

        $this->assertSame(8.0, (float) $speaker->fresh()->current_stock);

        // Admin approves cancellation
        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'approve',
            'admin_note' => 'Approve cancellation and initiate material recovery',
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame('cancelled', $booking->status);

        // Physical stock remains at 8 (not automatically restocked without physical check-in)
        $this->assertSame(8.0, (float) $speaker->fresh()->current_stock);

        // Return record is ensured for recovery tracking
        $return = AssetReturn::where('booking_id', $booking->id)->first();
        $this->assertNotNull($return);
        $this->assertSame('Pending', $return->status);
    }

    public function test_unauthorized_client_and_admin_actions_are_rejected(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'quotation_sent',
            'event_type' => 'birthday',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Private Villa',
            'total_quoted' => 10000,
        ]);

        // Client Two attempts to cancel Client One's booking -> 403 Forbidden
        $unauthorizedResponse = $this->actingAs($this->otherClientUser)->post(route('bookings.request-cancellation', $booking), [
            'cancellation_reason' => 'Malicious cancellation attempt',
        ]);
        $unauthorizedResponse->assertForbidden();
        $this->assertSame('quotation_sent', $booking->fresh()->status);

        // Client attempts to access Admin decision route -> 403 Forbidden or redirect
        $clientDecisionResponse = $this->actingAs($this->clientUser)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'deny',
        ]);
        $clientDecisionResponse->assertForbidden();
    }

    public function test_denial_with_missing_or_invalid_prior_status_does_not_fabricate_confirmed(): void
    {
        // Booking with no quotations and no pre_cancellation_status
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => null,
            'cancellation_reason' => 'Corrupted state without prior tracking',
            'event_type' => 'dinner',
            'event_date' => now()->addDays(12)->toDateString(),
            'venue' => 'Restaurant Deck',
            'total_quoted' => 5000,
        ]);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking), [
            'action' => 'deny',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Cannot process cancellation denial: prior booking status is missing or invalid.');

        // Booking status remains untouched and is NOT fabricated to 'confirmed'
        $booking->refresh();
        $this->assertSame('cancellation_requested', $booking->status);
    }

    public function test_cancellation_action_and_modal_visible_for_eligible_statuses(): void
    {
        $eligibleStatuses = ['quotation_sent', 'confirmed', 'in_preparation', 'pending'];

        foreach ($eligibleStatuses as $status) {
            $booking = Booking::create([
                'client_id' => $this->clientRecord->id,
                'status' => $status,
                'event_type' => 'wedding',
                'event_date' => now()->addDays(25)->toDateString(),
                'venue' => 'Grand Ballroom',
                'total_quoted' => 20000,
            ]);

            $response = $this->actingAs($this->clientUser)->get(route('bookings.show', $booking));
            $response->assertOk();
            $response->assertSee('Request Cancellation');
            $response->assertSee('id="cancellationModal"', false);
            $response->assertSee(route('bookings.request-cancellation', $booking->id), false);
            $response->assertSee('name="cancellation_reason"', false);
        }
    }

    public function test_cancellation_action_and_modal_hidden_for_ineligible_statuses(): void
    {
        $ineligibleStatuses = ['cancellation_requested', 'cancelled', 'completed', 'event_in_progress'];

        foreach ($ineligibleStatuses as $status) {
            $booking = Booking::create([
                'client_id' => $this->clientRecord->id,
                'status' => $status,
                'event_type' => 'corporate',
                'event_date' => now()->addDays(10)->toDateString(),
                'venue' => 'Conference Hall',
                'total_quoted' => 12000,
            ]);

            $response = $this->actingAs($this->clientUser)->get(route('bookings.show', $booking));
            $response->assertOk();
            $response->assertDontSee('id="cancellationModal"', false);
            $response->assertDontSee(route('bookings.request-cancellation', $booking->id), false);

            if ($status === 'cancellation_requested') {
                $response->assertSee('Your cancellation request has been submitted to the admin for review.');
            }
        }
    }

    public function test_confirmed_client_can_submit_cancellation_from_view_form(): void
    {
        $booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'status' => 'confirmed',
            'event_type' => 'anniversary',
            'event_date' => now()->addDays(18)->toDateString(),
            'venue' => 'Sunset Deck',
            'total_quoted' => 15000,
        ]);

        // Verify view shows cancellation trigger
        $viewResponse = $this->actingAs($this->clientUser)->get(route('bookings.show', $booking));
        $viewResponse->assertOk();
        $viewResponse->assertSee('Request Cancellation');

        // Submit cancellation through form route
        $submitResponse = $this->actingAs($this->clientUser)->post(route('bookings.request-cancellation', $booking), [
            'cancellation_reason' => 'Client requested cancellation due to scheduling conflict',
        ]);
        $submitResponse->assertRedirect();
        $submitResponse->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('cancellation_requested', $booking->status);
        $this->assertSame('confirmed', $booking->pre_cancellation_status);

        // Verify after submission, cancellation button and modal are hidden
        $afterResponse = $this->actingAs($this->clientUser)->get(route('bookings.show', $booking));
        $afterResponse->assertOk();
        $afterResponse->assertDontSee('id="cancellationModal"', false);
        $afterResponse->assertSee('Your cancellation request has been submitted to the admin for review.');
    }
}
