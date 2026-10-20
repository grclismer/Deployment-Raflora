<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * System-wide frontend pass: one confirmation dialog instead of the browser's confirm(), one
 * pager for every list, "Back to Bookings" returning to the same filtered list, a bounded client
 * inbox, and per-area notes edited from the booking form's main screen.
 */
class SystemWideFrontendConsistencyTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------------ Confirmation dialog

    public function test_admin_payment_and_decline_actions_confirm_in_the_app_dialog(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(['status' => 'payment_submitted', 'total_quoted' => 30000, 'final_quoted_price' => 30000]);
        Payment::create(['booking_id' => $booking->id, 'amount' => 15000, 'payment_type' => 'downpayment', 'payment_option' => 'gcash', 'reference_number' => 'GC-1', 'status' => 'pending']);

        $html = $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertOk()->getContent();

        $this->assertStringNotContainsString('return confirm(', $html);
        $this->assertStringContainsString('data-confirm="Verify this payment?"', $html);
        $this->assertStringContainsString('data-confirm="Reject this payment?"', $html);
        $this->assertStringContainsString('id="rafloraConfirmModal"', $html);
        $this->assertStringContainsString("form.hasAttribute('data-confirm')", $html);
    }

    public function test_no_admin_view_uses_the_browsers_confirm_for_form_actions(): void
    {
        foreach (glob(resource_path('views/admin/{,*/}*.blade.php'), GLOB_BRACE) as $view) {
            $this->assertStringNotContainsString('return confirm(', file_get_contents($view), basename($view));
        }
    }

    public function test_confirming_still_runs_the_existing_server_rules(): void
    {
        // The dialog only submits the form: the payment rules decide (here, a booking that is not
        // waiting for payment verification is refused and nothing is recorded).
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking(['status' => 'pending']);
        $payment = Payment::create(['booking_id' => $booking->id, 'amount' => 15000, 'payment_type' => 'downpayment', 'payment_option' => 'gcash', 'reference_number' => 'GC-2', 'status' => 'pending']);

        $this->actingAs($admin)->post(route('admin.payments.verify', $payment))->assertRedirect();

        $this->assertSame('pending', $payment->fresh()->status);
        $this->assertNull($payment->fresh()->verified_at);
    }

    // ------------------------------------------------------------------ Pagination and the way back

    public function test_lists_use_the_shared_pager_and_keep_their_filters(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        foreach (range(1, 20) as $i) {
            $this->booking(['venue' => "Seed Hall {$i}"]);
        }

        $html = $this->actingAs($admin)->get(route('admin.bookings', ['status' => 'pending', 'search' => 'Seed', 'page' => 2]))->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Pagination"', $html);
        $this->assertMatchesRegularExpression('/Showing <span[^>]*>16&ndash;20<\/span> of <span[^>]*>20<\/span>/', $html);
        $this->assertStringContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('status=pending&amp;search=Seed&amp;page=1', $html);
    }

    public function test_back_to_bookings_returns_to_the_same_filtered_list(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $booking = $this->booking();

        $this->actingAs($admin)->get(route('admin.bookings', ['status' => 'pending', 'search' => 'Hall']))->assertOk();
        $html = $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->getContent();
        preg_match('/<a href="([^"]+)"[^>]*>\s*<i[^>]*><\/i>\s*Back to Bookings/', $html, $back);
        $this->assertSame(route('admin.bookings') . '?search=Hall&status=pending', html_entity_decode($back[1] ?? ''));

        // Only a URL stored by the bookings list is used.
        $this->withSession(['admin.bookings.list_url' => 'https://example.com/elsewhere'])
            ->actingAs($admin)->get(route('admin.bookings.show', $booking))
            ->assertSee('href="' . route('admin.bookings') . '"', false)
            ->assertDontSee('example.com/elsewhere');
    }

    // ------------------------------------------------------------------ Client inbox

    public function test_the_client_inbox_shows_the_most_recent_100_and_says_so(): void
    {
        $user = $this->clientUser();
        $booking = $this->booking(['client_id' => $user->id]);
        foreach (range(1, 105) as $i) {
            ClientNotification::create(['user_id' => $user->id, 'booking_id' => $booking->id, 'type' => 'booking_update', 'title' => "Update {$i}", 'message' => "Update number {$i}.", 'is_read' => $i > 3]);
        }

        $this->actingAs($user)->get(route('client.notifications.index'))
            ->assertOk()
            ->assertSeeText("Showing your 100 most recent updates. 5 older updates are still on each booking's Updates page.", false)
            ->assertSee('3 New Updates');
    }

    public function test_a_small_client_inbox_has_no_cap_note(): void
    {
        $user = $this->clientUser();
        ClientNotification::create(['user_id' => $user->id, 'type' => 'booking_update', 'title' => 'Only one', 'message' => 'Hello.', 'is_read' => false]);

        $this->actingAs($user)->get(route('client.notifications.index'))->assertOk()->assertDontSee('data-older-notifications', false);
    }

    // ------------------------------------------------------------------ Area notes on the booking form

    public function test_both_booking_forms_offer_the_area_note_dialog(): void
    {
        $guest = $this->get(route('guest.booking.create'))->assertOk()->getContent();
        $client = $this->actingAs($this->clientUser())->get(route('bookings.create'))->assertOk()->getContent();

        foreach ([$guest, $client] as $html) {
            $this->assertStringContainsString('id="area-note-dialog"', $html);
            $this->assertStringContainsString('maxlength="500"', $html);
            $this->assertStringContainsString('data-action="save-area-note"', $html);
            $this->assertStringContainsString("'edit-area-note'", $html);
            // The note is written to the design brief field that is submitted and validated.
            $this->assertStringContainsString('data-area-note-input="stage"', $html);
        }
    }

    // ------------------------------------------------------------------ Helpers

    private function booking(array $attributes = []): Booking
    {
        return Booking::create($attributes + [
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'Seed Hall, Makati',
            'status' => 'pending',
            'total_quoted' => 0,
        ]);
    }

    private function clientUser(): User
    {
        $user = User::create(['name' => 'Inbox Client', 'email' => 'inbox-client@example.com', 'password' => bcrypt('password123'), 'role' => 'client', 'email_verified_at' => now()]);
        Client::firstOrCreate(['email' => $user->email], ['id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Makati City']);

        return $user;
    }
}
