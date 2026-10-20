<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\StaffChecklistItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class StaffFeedbackAndValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_dashboard_renders_success_flash_message(): void
    {
        $staff = $this->user('staff');

        $response = $this->actingAs($staff)
            ->withSession(['success' => 'Operational task completed successfully.'])
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Operational task completed successfully.');
        $response->assertSee('rf-toast-alert', false);
        $response->assertSee('fa-circle-check', false);
    }

    public function test_staff_dashboard_renders_error_flash_message(): void
    {
        $staff = $this->user('staff');

        $response = $this->actingAs($staff)
            ->withSession(['error' => 'Critical operational task failed.'])
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Critical operational task failed.');
        $response->assertSee('rf-toast-alert', false);
        $response->assertSee('fa-circle-xmark', false);
    }

    public function test_staff_dashboard_renders_warning_and_info_flash_messages(): void
    {
        $staff = $this->user('staff');

        $response = $this->actingAs($staff)
            ->withSession([
                'warning' => 'Event schedule conflict warning.',
                'info' => 'New warehouse dispatch policy effective today.',
            ])
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Event schedule conflict warning.');
        $response->assertSee('New warehouse dispatch policy effective today.');
        $response->assertSee('fa-triangle-exclamation', false);
        $response->assertSee('fa-circle-info', false);
    }

    public function test_staff_dashboard_renders_validation_errors(): void
    {
        $staff = $this->user('staff');

        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag([
            'notes' => 'Operational notes may not exceed 2000 characters.',
        ]));

        $response = $this->actingAs($staff)
            ->withSession(['errors' => $errors])
            ->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('Please fix the following errors:');
        $response->assertSee('Operational notes may not exceed 2000 characters.');
    }

    public function test_staff_event_show_renders_centralized_success_message_without_duplicate(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);

        $response = $this->actingAs($staff)
            ->withSession(['success' => 'Checklist item marked as complete.'])
            ->get(route('staff.events.show', $booking));

        $response->assertOk();
        $response->assertSee('Checklist item marked as complete.');
        
        // Assert only a single instance of the success message exists in the rendered HTML (no view-level duplicate)
        $content = $response->getContent();
        $this->assertEquals(1, substr_count($content, 'Checklist item marked as complete.'));
    }

    public function test_staff_event_show_renders_centralized_error_and_warning_messages(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);

        $response = $this->actingAs($staff)
            ->withSession([
                'error' => 'This event has no confirmed non-perishable materials available for return recording.',
                'warning' => 'Material return deadline is approaching.',
            ])
            ->get(route('staff.events.show', $booking));

        $response->assertOk();
        $response->assertSee('This event has no confirmed non-perishable materials available for return recording.');
        $response->assertSee('Material return deadline is approaching.');
    }

    public function test_staff_event_show_renders_centralized_validation_errors(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);

        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag([
            'observed_stock' => 'The observed stock field is required.',
        ]));

        $response = $this->actingAs($staff)
            ->withSession(['errors' => $errors])
            ->get(route('staff.events.show', $booking));

        $response->assertOk();
        $response->assertSee('Please fix the following errors:');
        $response->assertSee('The observed stock field is required.');

        // Assert only a single instance of the error message appears (no duplicate alert blocks)
        $content = $response->getContent();
        $this->assertEquals(1, substr_count($content, 'The observed stock field is required.'));
    }

    public function test_staff_checklist_update_redirects_and_displays_success_feedback(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $checklist = StaffChecklistItem::where('booking_id', $booking->id)->firstOrFail();

        $response = $this->actingAs($staff)
            ->from(route('staff.events.show', $booking))
            ->put(route('staff.events.checklist.update', [$booking, $checklist]), [
                'is_completed' => 1,
                'notes' => 'Materials prepared and checked.',
            ]);

        $response->assertRedirect(route('staff.events.show', $booking));
        $response->assertSessionHas('success', 'Checklist item updated.');

        $followUp = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $followUp->assertOk();
        $followUp->assertSee('Checklist item updated.');
    }

    public function test_staff_checklist_update_validation_failure_displays_error_feedback(): void
    {
        $staff = $this->user('staff');
        $booking = $this->booking($staff->id);
        $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $checklist = StaffChecklistItem::where('booking_id', $booking->id)->firstOrFail();

        $response = $this->actingAs($staff)
            ->from(route('staff.events.show', $booking))
            ->put(route('staff.events.checklist.update', [$booking, $checklist]), [
                // missing is_completed
                'notes' => 'Invalid update without completion status.',
            ]);

        $response->assertRedirect(route('staff.events.show', $booking));
        $response->assertSessionHasErrors('is_completed');

        $followUp = $this->actingAs($staff)->get(route('staff.events.show', $booking));
        $followUp->assertOk();
        $followUp->assertSee('Please fix the following errors:');
        $followUp->assertSee('The is completed field is required.');
    }

    public function test_unauthenticated_or_client_users_cannot_access_staff_views_to_see_feedback(): void
    {
        // Guests redirected to login
        $this->get(route('staff.dashboard'))
            ->assertRedirect(route('login'));

        // Clients forbidden
        $client = $this->user('client');
        $this->actingAs($client)
            ->get(route('staff.dashboard'))
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

    private function booking(int $staffId): Booking
    {
        return Booking::create([
            'staff_id' => $staffId,
            'event_type' => 'Staff Feedback Event',
            'event_date' => now()->addDays(3)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Grand Ballroom',
            'status' => 'confirmed',
        ]);
    }
}
