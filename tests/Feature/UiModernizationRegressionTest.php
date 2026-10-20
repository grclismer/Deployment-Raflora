<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Defects found while extending the mobile/desktop modernization to the remaining pages.
 */
class UiModernizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create([
            'name' => 'Rafaela Flores',
            'email' => 'admin@raflora.test',
            'password' => bcrypt('password123'),
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function clientUser(): array
    {
        $user = User::create([
            'name' => 'Elena Santos',
            'first_name' => 'Elena',
            'last_name' => 'Santos',
            'email' => 'elena@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
        $client = Client::create(['full_name' => 'Elena Santos', 'email' => $user->email, 'phone' => '09171234567']);

        return [$user, $client];
    }

    private function booking(Client $client, string $status, float $quote = 0): Booking
    {
        return Booking::create([
            'client_id' => $client->id,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'Manila Hotel',
            'status' => $status,
            'final_quoted_price' => $quote,
            'total_quoted' => $quote,
        ]);
    }

    public function test_admin_email_change_page_renders_with_the_current_email(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->get(route('admin.email-change.show'))
            ->assertOk()
            ->assertSee('Current Operational Email:')
            ->assertSee('admin@raflora.test');
    }

    public function test_return_tracking_shows_the_client_full_name(): void
    {
        [, $client] = $this->clientUser();
        $booking = $this->booking($client, 'pending_return', 30000);
        AssetReturn::create(['booking_id' => $booking->id, 'status' => 'Pending', 'total_damage_charge' => 0]);

        $this->actingAs($this->admin())
            ->get(route('admin.return-tracking'))
            ->assertOk()
            ->assertSee('Elena Santos')
            ->assertDontSee('>N/A<', false);
    }

    public function test_client_notifications_lead_with_the_notification_title(): void
    {
        [$user, $client] = $this->clientUser();
        $booking = $this->booking($client, 'quotation_sent', 18500);
        ClientNotification::create([
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'type' => 'quotation_sent',
            'title' => 'Official Quotation Ready',
            'message' => 'Your official quotation is ready for review.',
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->get(route('client.notifications.index'))
            ->assertOk()
            ->assertSee('Official Quotation Ready')
            ->assertSee('Booking #' . $booking->id);
    }

    public function test_client_notification_modal_does_not_inject_message_text_as_html(): void
    {
        [$user] = $this->clientUser();

        $html = $this->actingAs($user)->get(route('client.notifications.index'))->assertOk()->getContent();

        $this->assertStringNotContainsString('details.custom_note.replace', $html);
        $this->assertStringContainsString('noteText.textContent = details.custom_note', $html);
    }

    public function test_account_settings_page_is_a_working_profile_form_without_dead_links(): void
    {
        [$user] = $this->clientUser();

        $response = $this->actingAs($user)->get(route('account-settings'));

        $response->assertOk()
            ->assertSee('action="' . route('account-settings.update') . '"', false)
            ->assertSee('name="first_name"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="new_password"', false)
            ->assertSee('Log Out')
            ->assertDontSee('href="#"', false);
    }

    public function test_account_settings_form_saves_profile_changes(): void
    {
        [$user] = $this->clientUser();

        $this->actingAs($user)
            ->post(route('account-settings.update'), [
                'first_name' => 'Elena',
                'last_name' => 'Santos-Reyes',
                'email' => 'elena@example.com',
                'mobile_number' => '09179876543',
                'address' => 'Makati City',
            ])
            ->assertRedirect(route('account-settings'));

        $user->refresh();
        $this->assertSame('Elena Santos-Reyes', $user->name);
        $this->assertSame('09179876543', $user->mobile_number);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_reports_revenue_series_uses_a_cartesian_axis(): void
    {
        // Chart.js infers a scale's axis from its id; an id starting with "r" ("revenue")
        // becomes a radial axis unless declared, which silently hides the revenue line.
        $this->actingAs($this->admin())
            ->get(route('admin.reports'))
            ->assertOk()
            ->assertSee("revenue: { type: 'linear', axis: 'y', position: 'left'", false)
            ->assertSee('chart.js@4.4.1', false);
    }

    public function test_booking_history_does_not_show_a_zero_quote_for_unquoted_bookings(): void
    {
        [$user, $client] = $this->clientUser();
        $this->booking($client, 'pending');

        $this->actingAs($user)
            ->get(route('booking-history'))
            ->assertOk()
            ->assertSee('Not yet quoted')
            ->assertDontSee('Quote: ₱0.00');
    }
}
