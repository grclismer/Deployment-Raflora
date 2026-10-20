<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Services\GeminiVisionService;
use App\Support\EventDesignBrief;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Event details and the optional design brief reach the Smart AI analysis, are validated and
 * stored with the booking, and are shown to Admin and Staff. The prompt keeps the photo as the
 * main evidence and never lets the budget change prices.
 */
class EventDesignBriefTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Cache::flush();
    }

    // ------------------------------------------------------------------ Normalisation

    public function test_brief_input_is_cleaned_and_empty_input_stores_nothing(): void
    {
        $brief = EventDesignBrief::fromInput([
            'photo_usage' => 'style',
            'decor_areas' => ['aisle', 'not-an-area', 'centerpieces', 'aisle'],
            'theme' => 'garden',
            'theme_other' => 'ignored unless theme is other',
            'color_palette' => [' Blush pink ', 'blush PINK', '', 'Ivory', 'Sage', 'Gold'],
            'preferred_flowers' => '  Peonies ',
            'avoid_flowers' => '',
            'flower_material' => 'plastic',
            'budget_range' => '30k_60k',
        ]);

        $this->assertSame([
            'photo_usage' => 'style',
            'decor_areas' => ['centerpieces', 'aisle'],
            'theme' => 'garden',
            'color_palette' => ['Blush pink', 'Ivory', 'Sage'],
            'preferred_flowers' => 'Peonies',
            'budget_range' => '30k_60k',
        ], $brief);

        $this->assertSame('Vintage Filipiniana', EventDesignBrief::fromInput(['theme' => 'other', 'theme_other' => 'Vintage Filipiniana'])['theme_other']);
        $this->assertNull(EventDesignBrief::fromInput(['color_palette' => ['', ' '], 'decor_areas' => []]));
    }

    // ------------------------------------------------------------------ Prompt

    public function test_prompt_includes_the_brief_and_the_rules_for_using_it(): void
    {
        Http::fake(['*' => Http::response($this->geminiText(['suggested_materials' => [$this->roseRow()]]))]);

        (new GeminiVisionService())->analyzeImageFromPath(
            $this->tempImage(), null, 'wedding', '15:00', 'Manila Hotel', 'Tables: 12.', '2026-12-12', '21:00',
            designBrief: [
                'photo_usage' => 'exact',
                'decor_areas' => ['centerpieces', 'cake_table'],
                'theme' => 'rustic',
                'color_palette' => ['Blush pink', 'Ivory'],
                'avoid_flowers' => 'Lilies',
                'flower_material' => 'artificial',
                'budget_range' => '15k_30k',
            ],
            venueSetting: 'outdoor'
        );

        $prompt = $this->sentPrompt();
        foreach ([
            '- Venue Setting: Outdoor',
            '- Client Use of photo: Copy it exactly',
            '- Client Areas to decorate: Table centerpieces, Cake table',
            '- Client Theme: Rustic',
            '- Client Color palette: Blush pink, Ivory',
            '- Client Flowers to avoid: Lilies',
            '- Client Flower material: Artificial flowers',
            '- Client Budget range: ₱15,000 – ₱30,000',
            'CLIENT DESIGN BRIEF RULES',
            'the photo wins over the client theme',
            'one centerpiece per table',
            'must never appear in recommendation rows',
            'artificial/silk flower market rates',
            'heat- and wind-tolerant',
            'Budget never changes prices or quantities',
        ] as $expected) {
            $this->assertStringContainsString($expected, $prompt);
        }
        $this->assertStringNotContainsString('Same style', $prompt);
    }

    public function test_prompt_is_unchanged_when_no_brief_is_given(): void
    {
        Http::fake(['*' => Http::response($this->geminiText(['suggested_materials' => [$this->roseRow()]]))]);

        (new GeminiVisionService())->analyzeImageFromPath($this->tempImage(), 'Pastel roses', 'wedding');

        $prompt = $this->sentPrompt();
        $this->assertStringContainsString('- Special Requests: Pastel roses', $prompt);
        $this->assertStringNotContainsString('CLIENT DESIGN BRIEF RULES', $prompt);
        $this->assertStringNotContainsString('Venue Setting', $prompt);
    }

    public function test_client_photo_analysis_sends_the_brief_and_reanalyzes_when_it_changes(): void
    {
        Storage::fake('local');
        $user = $this->clientUser();
        Http::fake(['*' => Http::sequence()
            ->push($this->geminiText(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($this->geminiText(['suggested_materials' => [$this->roseRow()]]))
            ->push($this->geminiText(['suggested_materials' => [$this->roseRow()]])),
        ]);
        $request = fn (string $budget) => [
            'inspiration_image' => UploadedFile::fake()->createWithContent('arch.png', $this->pngBytes()),
            'event_type' => 'wedding',
            'event_date' => '2026-12-12',
            'end_time' => '21:00',
            'venue_setting' => 'both',
            'table_count' => 10,
            'decor_areas' => ['centerpieces', 'entrance'],
            // This photo is for both picked areas.
            'photo_areas' => ['centerpieces', 'entrance'],
            'color_palette' => ['Sage green'],
            'budget_range' => $budget,
        ];

        $first = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), $request('15k_30k'))->assertOk();
        $this->assertSame(['decor_areas' => ['entrance', 'centerpieces'], 'color_palette' => ['Sage green'], 'budget_range' => '15k_30k'], $first->json('analysis.analysis_meta.context.design_brief'));
        $this->assertSame('both', $first->json('analysis.analysis_meta.context.venue_setting'));

        // A different brief must not reuse the cached analysis of the same photo.
        $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), $request('60k_100k'))
            ->assertOk()
            ->assertJsonPath('analysis.analysis_meta.source', 'gemini');

        Http::assertSentCount(3);
        Http::assertSent(fn (HttpRequest $r) => str_contains($this->promptOf($r), '- Venue Setting: Indoor & outdoor')
            && str_contains($this->promptOf($r), '- Client Areas to decorate: Entrance or arch, Table centerpieces')
            && str_contains($this->promptOf($r), '- Client Budget range: ₱60,000 – ₱100,000'));
    }

    // ------------------------------------------------------------------ Validation

    public function test_invalid_brief_values_are_rejected(): void
    {
        $user = $this->clientUser();
        $package = Package::create(['title' => 'Brief Package', 'price' => 5000, 'is_active' => true]);

        $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'venue' => 'Manila Hotel, Manila',
            'venue_setting' => 'rooftop',
            'budget_range' => 'a-million',
            'color_palette' => ['Red', 'Blue', 'Green', 'Gold'],
            'decor_areas' => ['ceiling'],
            'end_time' => '9pm',
        ])->assertSessionHasErrors(['venue_setting', 'budget_range', 'color_palette', 'decor_areas.0', 'end_time']);

        $this->assertSame(0, Booking::count());
    }

    // ------------------------------------------------------------------ Storage

    public function test_preset_booking_keeps_event_details_but_not_the_ai_brief(): void
    {
        $user = $this->clientUser();
        $package = Package::create(['title' => 'Brief Package', 'price' => 5000, 'is_active' => true]);

        $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'preset',
            'package_id' => $package->id,
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(14)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue' => 'Manila Hotel, Manila',
            'venue_setting' => 'indoor',
            'theme' => 'elegant',
        ])->assertSessionHasNoErrors();

        $booking = Booking::firstOrFail();
        $this->assertSame('21:00', substr((string) $booking->end_time, 0, 5));
        $this->assertSame('indoor', $booking->venue_setting);
        $this->assertNull($booking->design_brief);
    }

    public function test_guest_ai_booking_stores_the_brief_and_claiming_copies_it(): void
    {
        Mail::fake();
        $analysis = ['suggested_materials' => [$this->roseRow()]];

        // The analysis comes from this session's photo analysis, never from the browser.
        $this->withSession(['booking_analysis_payloads' => ['token-1' => ['analysis' => $analysis]]])->post(route('guest.booking.store'), [
            'guest_name' => 'Samantha Cruz',
            'guest_email' => 'samantha.cruz@example.com',
            'guest_phone' => '09179876543',
            'guest_address' => '78 Magnolia Lane, Quezon City',
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['token-1']),
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'Tagaytay City',
            'venue_specific' => 'The Glasshouse Tagaytay',
            'venue' => 'The Glasshouse Tagaytay, Tagaytay City',
            'venue_setting' => 'outdoor',
            'photo_usage' => 'colors',
            'theme' => 'other',
            'theme_other' => 'Vintage Filipiniana',
            'avoid_flowers' => 'Lilies',
            'flower_material' => 'mixed',
        ])->assertSessionHasNoErrors();

        $temp = TemporaryGuestBooking::where('guest_email', 'samantha.cruz@example.com')->firstOrFail();
        $expectedBrief = ['photo_usage' => 'colors', 'theme' => 'other', 'theme_other' => 'Vintage Filipiniana', 'avoid_flowers' => 'Lilies', 'flower_material' => 'mixed'];
        $this->assertSame($expectedBrief, $temp->design_brief);
        $this->assertSame('outdoor', $temp->venue_setting);
        $this->assertSame('21:00', substr((string) $temp->end_time, 0, 5));

        $token = Str::random(40);
        $temp->update(['claim_token_hash' => hash('sha256', $token)]);
        $user = User::factory()->create(['email' => 'samantha.cruz@example.com', 'email_verified_at' => now()]);
        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]))->assertRedirect(route('bookings'));

        $booking = Booking::where('guest_email', 'samantha.cruz@example.com')->firstOrFail();
        $this->assertSame($expectedBrief, $booking->design_brief);
        $this->assertSame('outdoor', $booking->venue_setting);
        $this->assertSame('21:00', substr((string) $booking->end_time, 0, 5));
    }

    // ------------------------------------------------------------------ Pages

    public function test_booking_forms_offer_the_new_fields_with_event_details_first_on_the_client_form(): void
    {
        $this->get(route('guest.booking.create'))
            ->assertOk()
            ->assertSee('data-design-brief', false)
            ->assertSee('name="venue_setting"', false)
            ->assertSee('name="budget_range"', false)
            ->assertSee('name="decor_areas[]"', false);

        $this->actingAs($this->clientUser())->get(route('bookings.create'))
            ->assertOk()
            ->assertSeeInOrder(['Step 1', 'Event Details', 'Step 2', 'Booking Method &amp; Design'], false)
            ->assertSeeInOrder(['id="booking-step-1"', 'name="event_date"', 'name="end_time"', 'name="venue_setting"', 'id="next-step-btn"', 'id="booking-step-2"', 'data-design-brief', 'id="inspiration_image_input"', 'id="clientSubmitBtn"'], false);
    }

    public function test_admin_and_staff_see_what_the_ai_was_told(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(10)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue' => 'Manila Hotel, Manila',
            'venue_setting' => 'both',
            'design_brief' => ['photo_usage' => 'style', 'decor_areas' => ['stage', 'aisle'], 'avoid_flowers' => 'Lilies', 'budget_range' => 'over_100k'],
            'staff_id' => $staff->id,
            'status' => 'approved',
            'total_quoted' => 15000,
        ]);

        foreach ([[$admin, route('admin.bookings.show', $booking)], [$staff, route('staff.events.show', $booking)]] as [$user, $url]) {
            $this->actingAs($user)->get($url)
                ->assertOk()
                ->assertSee('Client design brief')
                ->assertSee('Indoor &amp; outdoor', false)
                ->assertSee('Same style')
                ->assertSee('Stage or backdrop, Aisle')
                ->assertSee('Lilies')
                ->assertSee('Over ₱100,000');
        }

        $this->actingAs($admin)->get(route('admin.bookings.show', $booking))->assertSee('3:00 PM – 9:00 PM');
    }

    // ------------------------------------------------------------------ Helpers

    private function clientUser(): User
    {
        $user = User::create([
            'name' => 'Brief Client',
            'email' => 'brief-client@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);
        Client::firstOrCreate(['email' => $user->email], ['id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Test Address']);

        return $user;
    }

    private function sentPrompt(): string
    {
        $prompt = '';
        Http::assertSent(function (HttpRequest $request) use (&$prompt) {
            $prompt = $this->promptOf($request);

            return $prompt !== '';
        });

        return $prompt;
    }

    private function promptOf(HttpRequest $request): string
    {
        return (string) (json_decode($request->body(), true)['contents'][0]['parts'][0]['text'] ?? '');
    }

    private function tempImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'brief') . '.png';
        file_put_contents($path, $this->pngBytes());

        return $path;
    }

    private function roseRow(): array
    {
        return [
            'item_name' => 'White Roses', 'category' => 'flower', 'quantity' => 20, 'unit_type' => 'stem',
            'unit_cost_php' => 35, 'area' => 'tables', 'is_detected' => true, 'is_recommendation' => false,
        ];
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
