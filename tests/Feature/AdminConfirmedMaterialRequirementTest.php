<?php

namespace Tests\Feature;

use App\Http\Controllers\BookingController;
use App\Http\Controllers\GuestBookingController;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class AdminConfirmedMaterialRequirementTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_booking_item_is_unconfirmed_by_default(): void
    {
        $item = BookingItem::create([
            'booking_id' => $this->booking()->id,
            'item_name' => 'AI Flower',
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending',
        ]);

        $this->assertNull($item->fresh()->confirmed_at);
    }

    public function test_admin_can_confirm_a_booking_material_and_audit_actor_is_recorded(): void
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        $inventoryItem = $this->inventoryItem();
        $bookingItem = $booking->bookingItems()->create([
            'inventory_item_id' => $inventoryItem->id,
            'item_name' => $inventoryItem->name,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
            'procurement_status' => 'pending',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [
            'booking' => $booking,
            'bookingItem' => $bookingItem,
        ]));

        $response->assertRedirect();
        $freshItem = $bookingItem->fresh();
        $this->assertNotNull($freshItem->confirmed_at);
        $this->assertTrue($freshItem->is_ai_suggested);
        $this->assertSame('pending', $freshItem->procurement_status);
        $this->assertDatabaseHas('booking_items', ['id' => $bookingItem->id]);
        $this->assertTrue($booking->fresh()->inventoryItems()->whereKey($inventoryItem->id)->exists());
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $admin->id,
            'action' => 'material_confirmed',
            'entity_id' => $booking->id,
        ]);
    }

    public function test_staff_cannot_confirm_material_requirements(): void
    {
        $booking = $this->booking();
        $item = $booking->bookingItems()->create(['item_name' => 'AI Flower', 'quantity' => 1, 'is_ai_suggested' => true]);

        $this->actingAs($this->user('staff'))
            ->post(route('admin.bookings.items.confirm', [$booking, $item]))
            ->assertForbidden();

        $this->assertNull($item->fresh()->confirmed_at);
    }

    public function test_client_and_guest_cannot_confirm_material_requirements(): void
    {
        $booking = $this->booking();
        $item = $booking->bookingItems()->create(['item_name' => 'AI Flower', 'quantity' => 1, 'is_ai_suggested' => true]);

        $this->actingAs($this->user('client'))
            ->post(route('admin.bookings.items.confirm', [$booking, $item]))
            ->assertForbidden();

        auth()->logout();
        $this->post(route('admin.bookings.items.confirm', [$booking, $item]))
            ->assertRedirect(route('login'));

        $this->assertNull($item->fresh()->confirmed_at);
    }

    public function test_admin_cannot_confirm_item_from_another_booking(): void
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        $otherBooking = $this->booking();
        $item = $otherBooking->bookingItems()->create(['item_name' => 'Other Flower', 'quantity' => 1, 'is_ai_suggested' => true]);

        $this->actingAs($admin)
            ->post(route('admin.bookings.items.confirm', ['booking' => $booking, 'bookingItem' => $item]))
            ->assertNotFound();

        $this->assertNull($item->fresh()->confirmed_at);
    }

    public function test_unconfirmed_material_blocks_quotation_submission(): void
    {
        $admin = $this->user('admin');
        $booking = $this->booking();
        $booking->bookingItems()->create(['item_name' => 'AI Flower', 'quantity' => 1, 'is_ai_suggested' => true]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'send_quotation',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Confirm every material requirement before sending the quotation.');
        $this->assertSame('pending', $booking->fresh()->status);
    }

    public function test_unmatched_ai_material_remains_a_booking_suggestion_without_catalog_creation(): void
    {
        $booking = $this->booking();
        $this->invokePersistence(new BookingController(), $booking);

        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'item_name' => 'Unmatched AI Material',
            'inventory_item_id' => null,
            'is_ai_suggested' => 1,
        ]);
        $this->assertCount(0, $booking->fresh()->inventoryItems);
    }

    public function test_admin_substitution_persists_identity_resets_confirmation_and_can_be_reconfirmed(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original, $substitute] = $this->substitutionFixture();

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'save',
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $substitute->name,
                'inventory_item_id' => $substitute->id,
                'quantity' => 2,
                'unit_price' => 1,
                'is_ai_suggested' => '1',
            ]],
        ]);

        $response->assertRedirect();
        $bookingItem->refresh();
        $this->assertSame($substitute->id, $bookingItem->inventory_item_id);
        $this->assertSame($substitute->name, $bookingItem->item_name);
        $this->assertSame(75.0, (float) $bookingItem->quoted_unit_price);
        $this->assertNull($bookingItem->confirmed_at);
        $this->assertTrue($bookingItem->is_ai_suggested);
        $this->assertSame('pending', $bookingItem->procurement_status);
        $this->assertTrue($booking->fresh()->inventoryItems()->whereKey($substitute->id)->exists());
        $this->assertFalse($booking->fresh()->inventoryItems()->whereKey($original->id)->exists());
        $this->assertDatabaseHas('audit_logs', ['action' => 'material_substituted', 'entity_id' => $booking->id]);

        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [$booking, $bookingItem]))->assertRedirect();
        $this->assertNotNull($bookingItem->fresh()->confirmed_at);
    }

    public function test_confirmed_substitute_drives_quote_and_inventory_deduction(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original, $substitute] = $this->substitutionFixture();

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'save',
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $substitute->name,
                'inventory_item_id' => $substitute->id,
                'quantity' => 2,
                'unit_price' => 1,
                'is_ai_suggested' => '1',
            ]],
        ]);
        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [$booking, $bookingItem]));

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'send_note',
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $substitute->name,
                'inventory_item_id' => $substitute->id,
                'quantity' => 2,
                'unit_price' => 75,
                'is_ai_suggested' => '1',
            ]],
        ]);

        $this->assertSame(150.0, (float) $booking->fresh()->raw_materials_sum);

        $this->recordVerifiedPayment($booking);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'preparation_start_date' => now()->toDateString(),
            'venue' => $booking->venue,
            'status' => 'downpayment_received',
            'action' => 'save',
        ]);

        $this->assertSame(10.0, (float) $substitute->fresh()->current_stock);
        $this->assertSame(2.0, (float) $substitute->fresh()->reserved_stock);
        $this->assertSame(10.0, (float) $original->fresh()->current_stock);
    }

    public function test_preconfirmation_statuses_do_not_physically_lock_confirmed_material(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original] = $this->substitutionFixture();
        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [$booking, $bookingItem]));
        $initialStock = (float) $original->fresh()->current_stock;

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'payment_pending',
            'action' => 'save',
        ]);

        $this->assertSame($initialStock, (float) $original->fresh()->current_stock);
        $this->assertDatabaseMissing('inventory_transactions', ['booking_id' => $booking->id, 'transaction_type' => 'booking_lock']);
    }

    public function test_confirmed_lock_happens_once_and_event_start_does_not_deduct_again(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original] = $this->substitutionFixture();
        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [$booking, $bookingItem]));

        $this->recordVerifiedPayment($booking);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'preparation_start_date' => now()->toDateString(),
            'venue' => $booking->venue,
            'status' => 'confirmed',
            'action' => 'save',
        ]);
        $lockedStock = (float) $original->fresh()->current_stock;

        $this->actingAs($admin)->post(route('admin.bookings.dispatch', $booking), [
            'reason' => 'Dispatch',
            'items' => [['inventory_item_id' => $original->id, 'quantity' => 2]],
        ]);

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'event_in_progress',
            'action' => 'mark_event_in_progress',
        ]);

        $this->assertSame(1, $booking->inventoryTransactions()->where('transaction_type', 'booking_lock')->count());
        $this->assertSame(0, $booking->inventoryTransactions()->where('transaction_type', 'event_started')->count());
    }

    public function test_confirmed_decline_releases_once_and_preconfirmation_decline_does_not_restore_stock(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original] = $this->substitutionFixture();
        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [$booking, $bookingItem]));
        $this->recordVerifiedPayment($booking);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'preparation_start_date' => now()->toDateString(),
            'venue' => $booking->venue,
            'status' => 'confirmed',
            'action' => 'save',
        ]);
        $lockedStock = (float) $original->fresh()->reserved_stock;

        $this->actingAs($admin)->post(route('admin.bookings.decline', $booking));
        $this->assertSame(0.0, (float) $original->fresh()->reserved_stock);
        $this->assertSame(1, $booking->inventoryTransactions()->where('transaction_type', 'booking_release')->count());

        $this->actingAs($admin)->post(route('admin.bookings.decline', $booking));
        $this->assertSame(0.0, (float) $original->fresh()->reserved_stock);
        $this->assertSame(1, $booking->inventoryTransactions()->where('transaction_type', 'booking_release')->count());

        $preconfirmation = $this->booking();
        $preItem = $preconfirmation->bookingItems()->create([
            'inventory_item_id' => $original->id,
            'item_name' => $original->name,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
        ]);
        $this->actingAs($admin)->post(route('admin.bookings.decline', $preconfirmation));
        $this->assertSame(10.0, (float) $original->fresh()->current_stock);
        $this->assertSame(0, $preconfirmation->inventoryTransactions()->count());
        $this->assertGreaterThan((float) $original->fresh()->reserved_stock, $lockedStock);
    }

    public function test_confirmed_cancellation_through_booking_update_releases_actual_lock(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original] = $this->substitutionFixture();
        $this->actingAs($admin)->post(route('admin.bookings.items.confirm', [$booking, $bookingItem]));
        $this->recordVerifiedPayment($booking);
        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'preparation_start_date' => now()->toDateString(),
            'venue' => $booking->venue,
            'status' => 'confirmed',
            'action' => 'save',
        ]);

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'cancelled',
            'action' => 'save',
        ]);

        $this->assertSame(0.0, (float) $original->fresh()->reserved_stock);
        $this->assertSame(1, $booking->inventoryTransactions()->where('transaction_type', 'booking_release')->count());
    }

    public function test_invalid_and_cross_booking_substitutions_are_rejected(): void
    {
        $admin = $this->user('admin');
        [$booking, $bookingItem, $original] = $this->substitutionFixture();
        $invalid = $this->inventoryItem('Undesignated Item', 60);

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'save',
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $invalid->name,
                'inventory_item_id' => $invalid->id,
                'quantity' => 2,
                'unit_price' => 60,
            ]],
        ])->assertSessionHas('error');
        $this->assertSame($original->id, $bookingItem->fresh()->inventory_item_id);

        $otherBooking = $this->booking();
        $otherItem = $otherBooking->bookingItems()->create([
            'inventory_item_id' => $original->id,
            'item_name' => $original->name,
            'quantity' => 1,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
            'confirmed_at' => now(),
        ]);

        $this->actingAs($admin)->put(route('admin.bookings.update', $booking), [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'save',
            'items' => [[
                'booking_item_id' => $otherItem->id,
                'item_name' => $substituteName = 'Unauthorized',
                'inventory_item_id' => $invalid->id,
                'quantity' => 1,
                'unit_price' => 1,
            ]],
        ])->assertSessionHas('error');
        $this->assertSame($original->id, $bookingItem->fresh()->inventory_item_id);
    }

    public function test_staff_client_and_guest_cannot_substitute(): void
    {
        [$booking, $bookingItem, $original, $substitute] = $this->substitutionFixture();
        $payload = [
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'status' => 'pending',
            'action' => 'save',
            'items' => [[
                'booking_item_id' => $bookingItem->id,
                'item_name' => $substitute->name,
                'inventory_item_id' => $substitute->id,
                'quantity' => 2,
                'unit_price' => 75,
            ]],
        ];

        $this->actingAs($this->user('staff'))->put(route('admin.bookings.update', $booking), $payload)->assertForbidden();
        $this->actingAs($this->user('client'))->put(route('admin.bookings.update', $booking), $payload)->assertForbidden();
        auth()->logout();
        $this->put(route('admin.bookings.update', $booking), $payload)->assertRedirect(route('login'));
        $this->assertSame($original->id, $bookingItem->fresh()->inventory_item_id);
    }

    private function substitutionFixture(): array
    {
        $booking = $this->booking();
        $original = $this->inventoryItem('Original Material', 50, 10);
        $substitute = $this->inventoryItem('Designated Substitute', 75, 10);
        $original->substitutes()->attach($substitute->id);
        $bookingItem = $booking->bookingItems()->create([
            'inventory_item_id' => $original->id,
            'item_name' => $original->name,
            'quantity' => 2,
            'quoted_unit_price' => 50,
            'is_ai_suggested' => true,
            'confirmed_at' => now(),
            'procurement_status' => 'pending',
        ]);

        return [$booking, $bookingItem, $original, $substitute];
    }

    public function test_guest_unmatched_ai_material_remains_a_booking_suggestion_without_catalog_creation(): void
    {
        $booking = $this->booking();
        $this->invokePersistence(new GuestBookingController(), $booking);

        $this->assertDatabaseCount('inventory_items', 0);
        $this->assertDatabaseHas('booking_items', [
            'booking_id' => $booking->id,
            'item_name' => 'Unmatched AI Material',
            'inventory_item_id' => null,
            'is_ai_suggested' => 1,
        ]);
        $this->assertCount(0, $booking->fresh()->inventoryItems);
    }

    private function invokePersistence(object $controller, Booking $booking): void
    {
        $method = new ReflectionMethod($controller, 'persistAiSuggestedMaterials');
        $method->setAccessible(true);
        $method->invoke($controller, $booking, [[
            'item_name' => 'Unmatched AI Material',
            'quantity' => 2,
            'unit_cost_php' => 75,
            'unit_type' => 'piece',
            'category' => 'prop',
        ]]);
    }

    private function booking(): Booking
    {
        return Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'venue' => 'The Garden Hall',
            'status' => 'pending',
            'total_quoted' => 0,
        ]);
    }

    private function inventoryItem(string $name = 'White Roses', float $unitCost = 50, float $stock = 10, bool $isPerishable = false): InventoryItem
    {
        return InventoryItem::create([
            'name' => $name,
            'category' => $isPerishable ? 'flowers' : 'props',
            'is_perishable' => $isPerishable,
            'current_stock' => $stock,
            'unit_cost' => $unitCost,
            'min_stock' => 1,
            'unit' => $isPerishable ? 'stem' : 'piece',
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role) . ' User',
            'email' => $role . '-' . uniqid() . '@example.com',
            'password' => bcrypt('password123'),
            'role' => $role,
        ]);
    }
}
