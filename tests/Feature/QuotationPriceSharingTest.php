<?php

namespace Tests\Feature;

use App\Mail\GuestBookingNotificationMail;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\Quotation;
use App\Models\User;
use App\Services\QuotationIssuanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Raflora prices are Admin's until a quotation is issued. Guests and clients see a price only
 * from the issued (or accepted) quotation; working prices and Gemini's estimates never reach
 * them, and Staff sees no prices at all. Public catalog package prices stay public.
 */
class QuotationPriceSharingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_pages_show_no_price_before_a_quotation_is_issued(): void
    {
        [$user, $client] = $this->clientUser();
        $booking = $this->booking(['client_id' => $client->id, 'final_quoted_price' => 9000, 'total_quoted' => 9000]);

        $this->actingAs($user)->get(route('bookings.analysis', $booking))
            ->assertOk()->assertDontSee('9,000.00')->assertSee('Not yet issued');
        $this->actingAs($user)->get(route('booking-history'))
            ->assertOk()->assertDontSee('9,000.00')->assertSee('Not yet quoted');
        $this->actingAs($user)->get(route('client.booking.updates', $booking))
            ->assertOk()->assertDontSee('9,000.00')->assertSee('Not yet quoted');
    }

    public function test_client_sees_the_issued_quotation_not_later_working_edits(): void
    {
        [$user, $client] = $this->clientUser();
        $booking = $this->booking(['client_id' => $client->id, 'status' => 'quotation_sent', 'final_quoted_price' => 12000]);
        Quotation::create(['booking_id' => $booking->id, 'status' => Quotation::STATUS_ISSUED, 'version' => 1, 'final_quoted_price' => 12000]);

        // Admin reworks prices after issuing but has not re-issued yet.
        $booking->update(['final_quoted_price' => 15500, 'total_quoted' => 15500]);

        $this->actingAs($user)->get(route('bookings.analysis', $booking))
            ->assertOk()->assertSee('₱12,000.00')->assertDontSee('15,500.00');
        $this->actingAs($user)->get(route('booking-history'))
            ->assertOk()->assertSee('Quote: ₱12,000.00')->assertDontSee('15,500.00');
        $this->actingAs($user)->get(route('client.booking.updates', $booking))
            ->assertOk()->assertSee('₱12,000.00')->assertDontSee('15,500.00');
    }

    public function test_package_booking_shows_only_the_public_catalog_price_before_issuance(): void
    {
        [$user, $client] = $this->clientUser();
        $package = \App\Models\Package::create(['title' => 'Gold Package', 'price' => 15000]);
        $booking = $this->booking(['client_id' => $client->id, 'package_id' => $package->id, 'final_quoted_price' => 17250]);

        $this->actingAs($user)->get(route('bookings.analysis', $booking))
            ->assertOk()->assertSee('Catalog Package Price')->assertSee('₱15,000.00')->assertDontSee('17,250.00');
    }

    public function test_a_quotation_that_fails_to_issue_adds_no_price_line_for_the_client(): void
    {
        [$user, $client] = $this->clientUser();
        // No confirmed materials, so issuance is refused.
        $booking = $this->booking(['client_id' => $client->id, 'final_quoted_price' => 15000, 'total_quoted' => 15000]);

        $this->actingAs($this->admin())->put(route('admin.bookings.update', $booking), $this->adminForm($booking, [
            'status' => 'pending',
            'admin_note' => 'Still reviewing materials.',
            'final_quoted_price' => 18000,
            'action' => 'send_quotation',
        ]))->assertRedirect()->assertSessionHas('error');

        $this->assertSame(0, $booking->quotations()->count());

        $notes = (string) $booking->fresh()->admin_notes;
        $this->assertStringNotContainsString('Price changes', $notes);
        $this->assertStringNotContainsString('18,000', $notes);
        $this->assertStringNotContainsString('15,000', $notes);
    }

    public function test_reissued_quotation_note_compares_against_the_previously_issued_price(): void
    {
        [$user, $client] = $this->clientUser();
        $booking = $this->booking(['client_id' => $client->id]);
        $item = $this->confirmedItem($booking, 600); // 10 × ₱600 × 3 = ₱18,000
        app(QuotationIssuanceService::class)->issue($booking, $this->admin()->id);
        $booking->update(['status' => 'quotation_sent', 'final_quoted_price' => 99999]); // unissued working figure
        $item->update(['quoted_unit_price' => 700]); // re-issued at 10 × ₱700 × 3 = ₱21,000

        $this->actingAs($this->admin())->put(route('admin.bookings.update', $booking), $this->adminForm($booking, [
            'status' => 'quotation_sent',
            'admin_note' => 'Revised for the new rose price.',
            'action' => 'reissue_quotation',
        ]))->assertRedirect()->assertSessionMissing('error');

        $notes = (string) $booking->fresh()->admin_notes;
        $this->assertStringContainsString('Quote updated: ₱18,000.00 → ₱21,000.00', $notes);
        $this->assertStringNotContainsString('99,999', $notes);
    }

    public function test_guest_is_emailed_issued_prices_only(): void
    {
        Mail::fake();
        $booking = $this->booking(['client_id' => null, 'guest_name' => 'Guest', 'guest_email' => 'guest@example.com', 'final_quoted_price' => 15000]);
        $this->confirmedItem($booking, 600);

        // A working-price edit sends nothing to the guest.
        $booking->update(['final_quoted_price' => 16500, 'total_quoted' => 16500]);
        Mail::assertNotSent(GuestBookingNotificationMail::class, fn ($mail) => $mail->context !== 'confirmation');

        app(QuotationIssuanceService::class)->issue($booking, $this->admin()->id);

        Mail::assertSent(GuestBookingNotificationMail::class, function ($mail) {
            if ($mail->context !== 'quote_updated') {
                return false;
            }
            $html = $mail->render();

            return str_contains($html, '18,000.00') && !str_contains($html, '16,500.00') && !str_contains($html, '15,000.00');
        });
    }

    public function test_ai_analysis_reaches_the_client_browser_without_prices(): void
    {
        Storage::fake('local');
        $this->fakeGemini();

        $response = $this->actingAs($this->clientUser()[0])->postJson(route('bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNoPrices($response->json());
        // The session payload keeps Gemini's price for Admin's review (stored as ai_recommended_price).
        $payload = session('booking_analysis_payloads.' . $response->json('analysis_token'));
        $this->assertEquals(35, $payload['analysis']['suggested_materials'][0]['unit_cost_php']);
    }

    public function test_ai_analysis_reaches_the_guest_browser_without_prices(): void
    {
        Storage::fake('local');
        $this->fakeGemini();

        $response = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertNoPrices($response->json());
    }

    public function test_staff_event_page_shows_no_prices(): void
    {
        $staff = User::factory()->create(['role' => 'staff', 'email' => 'staff-prices@example.com']);
        $booking = $this->booking(['staff_id' => $staff->id, 'status' => 'confirmed', 'final_quoted_price' => 18000]);
        $this->confirmedItem($booking, 600);
        Quotation::create(['booking_id' => $booking->id, 'status' => Quotation::STATUS_ACCEPTED, 'version' => 1, 'final_quoted_price' => 18000]);

        $this->actingAs($staff)->get(route('staff.events.show', $booking))
            ->assertOk()->assertDontSee('₱')->assertDontSee('18,000')->assertDontSee('600.00');
    }

    // ------------------------------------------------------------------ Helpers

    private function assertNoPrices(array $json): void
    {
        foreach (['raw_materials_total', 'estimated_grand_total', 'markup_multiplier', 'itemized_breakdown'] as $key) {
            $this->assertArrayNotHasKey($key, $json);
        }
        $this->assertArrayNotHasKey('pricing_summary', $json['analysis']);
        $this->assertArrayNotHasKey('unit_cost_php', $json['analysis']['suggested_materials'][0]);
        $this->assertArrayNotHasKey('estimated_unit_cost_php', $json['analysis']['suggested_materials'][0]);
        $this->assertStringNotContainsString('unit_cost', $json['analysis_data']);
        $this->assertStringNotContainsString('pricing_summary', $json['analysis_data']);
    }

    private function clientUser(): array
    {
        $user = User::factory()->create(['role' => 'client', 'email' => 'client-' . uniqid() . '@example.com']);
        $client = Client::create(['user_id' => $user->id, 'full_name' => 'Price Client', 'email' => $user->email, 'phone' => '09170000000', 'address' => 'Manila']);

        return [$user, $client];
    }

    private function admin(): User
    {
        return User::firstOrCreate(['email' => 'admin-prices@example.com'], ['name' => 'Admin', 'password' => bcrypt('password123'), 'role' => 'admin']);
    }

    private function booking(array $attributes = []): Booking
    {
        return Booking::create(array_merge([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'The Garden Hall',
            'status' => 'pending',
        ], $attributes));
    }

    private function confirmedItem(Booking $booking, float $unitPrice): BookingItem
    {
        return BookingItem::create([
            'booking_id' => $booking->id,
            'item_name' => 'White Roses',
            'quantity' => 10,
            'quoted_unit_price' => $unitPrice,
            'confirmed_at' => now(),
        ]);
    }

    private function adminForm(Booking $booking, array $fields): array
    {
        return array_merge([
            '_method' => 'PUT',
            'event_type' => $booking->event_type,
            'event_date' => $booking->event_date->toDateString(),
            'venue' => $booking->venue,
            'special_requests' => null,
        ], $fields);
    }

    private function fakeGemini(): void
    {
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Cache::flush();
        Http::fake(['*' => Http::sequence()
            ->push($this->geminiText(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($this->geminiText(['suggested_materials' => [[
                'item_name' => 'White Roses', 'category' => 'flower', 'quantity' => 20, 'unit_type' => 'stem',
                'unit_cost_php' => 35, 'area' => 'tables', 'is_detected' => true, 'is_recommendation' => false,
            ]]])),
        ]);
    }

    private function geminiText(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
    }

    private function pngBytes(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');
    }
}
