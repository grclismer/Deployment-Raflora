<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffAssignedEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_assign_a_booking_to_staff_without_changing_handled_by(): void
    {
        $admin = $this->user('admin');
        $staff = $this->user('staff');
        $booking = $this->booking($admin->id);

        $this->actingAs($admin)
            ->post(route('admin.bookings.assign-staff', $booking), ['staff_id' => $staff->id])
            ->assertRedirect();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'staff_id' => $staff->id,
            'handled_by' => $admin->id,
        ]);
    }

    public function test_staff_sees_only_events_assigned_to_the_authenticated_staff_user(): void
    {
        $staffA = $this->user('staff', 'staff-a@example.com');
        $staffB = $this->user('staff', 'staff-b@example.com');
        $assigned = $this->booking(null, $staffA->id, 'Assigned Event');
        $this->booking(null, $staffB->id, 'Private Event');

        $response = $this->actingAs($staffA)->get(route('staff.dashboard'));

        $response->assertOk()->assertSee('Assigned Event')->assertDontSee('Private Event');
        $this->actingAs($staffA)->get(route('staff.events.show', $assigned))->assertOk()->assertSee('Assigned Event');
    }

    public function test_staff_cannot_open_another_staff_members_event_by_id(): void
    {
        $staffA = $this->user('staff', 'staff-a@example.com');
        $staffB = $this->user('staff', 'staff-b@example.com');
        $booking = $this->booking(null, $staffA->id);

        $this->actingAs($staffB)
            ->get(route('staff.events.show', $booking))
            ->assertNotFound();
    }

    public function test_client_cannot_open_staff_events(): void
    {
        $staff = $this->user('staff');
        $client = $this->user('client');
        $booking = $this->booking(null, $staff->id);

        $this->actingAs($client)
            ->get(route('staff.events.show', $booking))
            ->assertForbidden();
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

    private function booking(?int $handledBy = null, ?int $staffId = null, string $eventType = 'Staff Event'): Booking
    {
        return Booking::create([
            'handled_by' => $handledBy,
            'staff_id' => $staffId,
            'event_type' => $eventType,
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '09:00',
            'venue' => 'Raflora Event Venue',
            'status' => 'confirmed',
        ]);
    }
}
