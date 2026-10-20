<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\StaffChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffChecklistTest extends TestCase
{
    use RefreshDatabase;

    public function test_assigned_event_creates_and_displays_preparation_checklist(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking));

        $response->assertOk()->assertSee('Event preparation checklist');
        $this->assertDatabaseCount('staff_checklist_items', 3);
        $this->assertDatabaseHas('staff_checklist_items', [
            'booking_id' => $booking->id,
            'key' => 'prepare-confirmed-materials',
            'is_completed' => false,
        ]);
    }

    public function test_staff_can_complete_checklist_item_and_persist_notes(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $checklist = StaffChecklistItem::where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($staff)
            ->put(route('staff.events.checklist.update', [$booking, $checklist]), [
                'is_completed' => 1,
                'notes' => 'Venue access confirmed.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('staff_checklist_items', [
            'id' => $checklist->id,
            'booking_id' => $booking->id,
            'is_completed' => true,
            'notes' => 'Venue access confirmed.',
            'completed_by' => $staff->id,
        ]);
    }

    public function test_staff_cannot_update_another_staff_members_checklist(): void
    {
        $staffA = $this->user('staff', 'checklist-a@example.com');
        $staffB = $this->user('staff', 'checklist-b@example.com');
        $booking = $this->booking($staffA->id);
        $this->actingAs($staffA)->get(route('staff.events.show', $booking));
        $checklist = StaffChecklistItem::where('booking_id', $booking->id)->firstOrFail();

        $this->actingAs($staffB)
            ->put(route('staff.events.checklist.update', [$booking, $checklist]), [
                'is_completed' => 1,
                'notes' => 'Unauthorized update',
            ])
            ->assertNotFound();

        $this->assertFalse((bool) $checklist->fresh()->is_completed);
    }

    public function test_checklist_id_from_another_booking_is_rejected(): void
    {
        $staff = $this->user('staff');
        $firstBooking = $this->booking($staff->id);
        $secondBooking = $this->booking($staff->id);
        $this->actingAs($staff)->get(route('staff.events.show', $firstBooking));
        $this->actingAs($staff)->get(route('staff.events.show', $secondBooking));
        $foreignChecklist = StaffChecklistItem::where('booking_id', $firstBooking->id)->firstOrFail();

        $this->actingAs($staff)
            ->put(route('staff.events.checklist.update', [$secondBooking, $foreignChecklist]), [
                'is_completed' => 1,
                'notes' => 'Wrong booking',
            ])
            ->assertNotFound();
    }

    private function user(string $role, ?string $email = null): User
    {
        return User::factory()->create([
            'name' => ucfirst($role) . ' User',
            'email' => $email ?? ($role . '-' . uniqid() . '@example.com'),
            'password' => 'password',
            'role' => $role,
        ]);
    }

    private function booking(int $staffId): Booking
    {
        return Booking::create([
            'staff_id' => $staffId,
            'event_type' => 'Staff Event',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '09:00',
            'venue' => 'Raflora Event Venue',
            'status' => 'confirmed',
        ]);
    }
}
