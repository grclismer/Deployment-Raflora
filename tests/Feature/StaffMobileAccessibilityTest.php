<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use App\Models\ReturnItem;
use App\Models\ReturnItemEvidence;
use App\Models\StaffChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StaffMobileAccessibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_checklist_checkbox_has_accessible_id_and_label_association(): void
    {
        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        $checklists = StaffChecklistItem::where('booking_id', $booking->id)->get();
        $this->assertNotEmpty($checklists);

        foreach ($checklists as $item) {
            $response->assertSee('id="checklist-check-' . $item->id . '"', false);
            $response->assertSee('for="checklist-check-' . $item->id . '"', false);
            $response->assertSee('for="checklist-notes-' . $item->id . '"', false);
            $response->assertSee('aria-label="Checklist notes for ' . $item->title . '"', false);
        }
    }

    public function test_completed_checklist_item_displays_accessible_visual_distinction(): void
    {
        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);

        $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $item = StaffChecklistItem::where('booking_id', $booking->id)->firstOrFail();
        $item->update(['is_completed' => true]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        // Check for visual distinction classes: line-through, border-emerald-200, completed badge
        $response->assertSee('border-emerald-200 bg-emerald-50/30', false);
        $response->assertSee('line-through text-slate-500', false);
        $response->assertSee('Completed');
    }

    public function test_dispatch_inputs_have_material_specific_accessible_names_and_responsive_presentation(): void
    {
        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);

        $invItem = InventoryItem::create([
            'name' => 'Arch Stand Deluxe',
            'category' => 'hardware',
            'unit' => 'pcs',
            'current_stock' => 10,
            'is_perishable' => false,
        ]);

        BookingItem::create([
            'booking_id' => $booking->id,
            'inventory_item_id' => $invItem->id,
            'item_name' => 'Arch Stand Deluxe',
            'item_type' => 'custom',
            'quantity' => 2,
            'price' => 500,
            'confirmed_at' => now(),
        ]);

        // Lock inventory for booking
        InventoryTransaction::create([
            'inventory_item_id' => $invItem->id,
            'booking_id' => $booking->id,
            'transaction_type' => 'booking_lock',
            'quantity_change' => -2,
            'remaining_stock' => 8,
            'reason' => 'Booking lock',
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        // Assert accessible names for both mobile and desktop views
        $response->assertSee('aria-label="Dispatch quantity for Arch Stand Deluxe"', false);
        $response->assertSee('for="dispatch-qty-mobile-' . $invItem->id . '"', false);
        $response->assertSee('for="dispatch-qty-desktop-' . $invItem->id . '"', false);

        // Assert mobile responsive cards are present and table does not force min-w-[520px]
        $response->assertSee('sm:hidden', false);
        $response->assertDontSee('min-w-[520px]');
    }

    public function test_return_count_inputs_have_accessible_labels_and_touch_friendly_height(): void
    {
        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);
        $booking->update(['status' => 'pending_return']);

        $invItem = InventoryItem::create([
            'name' => 'Candelabra Stand',
            'category' => 'hardware',
            'unit' => 'pcs',
            'current_stock' => 10,
            'is_perishable' => false,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending Return',
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $invItem->id,
            'quantity_returned' => 0,
            'quantity_good' => 0,
            'quantity_damaged' => 0,
            'quantity_lost' => 0,
            'condition' => 'pending',
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        // Check labels and aria-labels for split counts
        $response->assertSee('aria-label="Quantity good for Candelabra Stand"', false);
        $response->assertSee('aria-label="Quantity damaged for Candelabra Stand"', false);
        $response->assertSee('aria-label="Quantity lost for Candelabra Stand"', false);
        $response->assertSee('aria-label="Observation note for Candelabra Stand"', false);

        // Check touch target classes (min-h-[40px])
        $response->assertSee('min-h-[40px]', false);
    }

    public function test_condition_dropdown_contains_mixed_option_and_accepts_mixed_condition(): void
    {
        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);
        $booking->update(['status' => 'pending_return']);

        $invItem = InventoryItem::create([
            'name' => 'Table Centerpiece Stand',
            'category' => 'hardware',
            'unit' => 'pcs',
            'current_stock' => 10,
            'is_perishable' => false,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending Return',
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $invItem->id,
            'quantity_returned' => 4,
            'quantity_good' => 2,
            'quantity_damaged' => 2,
            'quantity_lost' => 0,
            'condition' => 'pending',
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        // Verify the option value="mixed" exists in the dropdown
        $response->assertSee('<option value="mixed"', false);

        // Submit condition update with mixed and evidence photo
        $postResponse = $this->actingAs($staff)->put(route('staff.events.return.condition', $booking), [
            'items' => [
                $returnItem->id => [
                    'condition' => 'mixed',
                    'notes' => '2 items with scratched coating, 2 in excellent condition.',
                    'evidence' => [UploadedFile::fake()->create('scratch.jpg', 10, 'image/jpeg')],
                ],
            ],
        ]);

        $postResponse->assertSessionHas('success');
        $returnItem->refresh();
        $this->assertSame('mixed', $returnItem->condition);
        $this->assertSame('2 items with scratched coating, 2 in excellent condition.', $returnItem->notes);
    }

    public function test_client_side_evidence_preview_markup_and_script_are_present(): void
    {
        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);
        $booking->update(['status' => 'pending_return']);

        $invItem = InventoryItem::create([
            'name' => 'Ceremony Arch',
            'category' => 'hardware',
            'unit' => 'pcs',
            'current_stock' => 5,
            'is_perishable' => false,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending Return',
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $invItem->id,
            'quantity_returned' => 1,
            'quantity_good' => 0,
            'quantity_damaged' => 1,
            'quantity_lost' => 0,
            'condition' => 'damaged',
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        $response->assertSee('id="new-evidence-previews-' . $returnItem->id . '"', false);
        $response->assertSee('previewEvidencePhotos(this, ' . $returnItem->id . ')', false);
        $response->assertSee('removeSelectedEvidenceFile', false);
    }

    public function test_previously_uploaded_evidence_renders_secure_thumbnail_links(): void
    {
        Storage::fake('local');

        $staff = $this->createStaffUser();
        $booking = $this->createBooking($staff->id);
        $booking->update(['status' => 'pending_return']);

        $invItem = InventoryItem::create([
            'name' => 'Floral Column',
            'category' => 'hardware',
            'unit' => 'pcs',
            'current_stock' => 5,
            'is_perishable' => false,
        ]);

        $return = AssetReturn::create([
            'booking_id' => $booking->id,
            'status' => 'Pending Return',
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $return->id,
            'inventory_item_id' => $invItem->id,
            'quantity_returned' => 1,
            'quantity_good' => 0,
            'quantity_damaged' => 1,
            'quantity_lost' => 0,
            'condition' => 'damaged',
        ]);

        $fakeFile = UploadedFile::fake()->create('broken_column.jpg', 10, 'image/jpeg');
        $storedPath = $fakeFile->store('return_evidence', 'local');

        $evidence = ReturnItemEvidence::create([
            'return_item_id' => $returnItem->id,
            'file_path' => $storedPath,
            'file_name' => 'broken_column.jpg',
            'size' => 1024,
            'mime_type' => 'image/jpeg',
            'uploaded_by' => $staff->id,
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $response->assertOk();

        // Check that thumbnail and secure show route are rendered
        $secureUrl = route('secure.evidence.show', $evidence);
        $response->assertSee($secureUrl, false);
        $response->assertSee('Uploaded Evidence (1)', false);
        $response->assertSee('broken_column.jpg', false);

        // Test authorized access by assigned staff
        $fileResponse = $this->actingAs($staff)->get($secureUrl);
        $fileResponse->assertOk();

        // Test unauthorized access by another staff member
        $otherStaff = $this->createStaffUser('other-staff@example.com');
        $unauthorizedResponse = $this->actingAs($otherStaff)->get($secureUrl);
        $unauthorizedResponse->assertForbidden();
    }

    private function createStaffUser(?string $email = null): User
    {
        return User::factory()->create([
            'name' => 'Staff Member',
            'email' => $email ?? ('staff-' . uniqid() . '@example.com'),
            'password' => 'password',
            'role' => 'staff',
        ]);
    }

    private function createBooking(int $staffId): Booking
    {
        return Booking::create([
            'staff_id' => $staffId,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Skyline Ballroom',
            'status' => 'confirmed',
        ]);
    }
}
