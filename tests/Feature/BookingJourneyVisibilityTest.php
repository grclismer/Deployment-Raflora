<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Who sees which journey stages (components.booking-workflow over BookingWorkflowService, which
 * stays the source of truth): guests and unconfirmed clients never see the Staff stages;
 * confirmed clients see them in client wording; Staff and Admin keep their own views.
 */
class BookingJourneyVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_client_sees_staff_stages_only_after_confirmation_and_in_client_wording(): void
    {
        [$user, $booking] = $this->clientBooking('quotation_sent');

        $this->actingAs($user)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('Stage 5 of 8 · Quotation')
            ->assertDontSee('data-workflow-phase="staff"', false);

        $booking->update(['status' => 'in_preparation']);

        $this->actingAs($user)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('data-workflow-phase="staff"', false)
            ->assertSee('Stage 9 of 14 · Preparation &amp; Reservation', false)
            ->assertSee('Raflora is preparing the materials for your event.')
            ->assertDontSee('no reusable materials require reservation');
    }

    public function test_admin_keeps_the_full_operational_detail(): void
    {
        [, $booking] = $this->clientBooking('in_preparation');
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()
            ->assertSee('data-workflow-phase="staff"', false)
            ->assertSee('Preparation in progress — no reusable materials require reservation.');
    }

    public function test_the_client_page_shows_one_status_not_three(): void
    {
        [$user, $booking] = $this->clientBooking('pending');

        $html = $this->actingAs($user)->get(route('bookings.show', $booking))->assertOk()->getContent();

        // The status & action section is the one prominent status; the progress tracker's
        // "Now" card and the header status badge no longer repeat it.
        $this->assertStringNotContainsString('aria-label="Booking status:', $html);
        $this->assertStringNotContainsString('Now · Client Workflow', $html);
        $this->assertStringNotContainsString('Post-Event Workflow', $html);
    }

    /** @return array{0: User, 1: Booking} */
    private function clientBooking(string $status): array
    {
        $user = User::create(['name' => 'Journey Client', 'email' => 'journey@example.com', 'password' => bcrypt('password123'), 'role' => 'client', 'email_verified_at' => now()]);
        $client = Client::firstOrCreate(['email' => $user->email], ['id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Test']);
        $booking = Booking::create([
            'client_id' => $client->id, 'event_type' => 'wedding', 'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Hall', 'status' => $status, 'final_quoted_price' => 10000,
        ]);

        return [$user, $booking];
    }
}
