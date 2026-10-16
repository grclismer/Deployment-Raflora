<?php

namespace Tests\Feature;

use App\Models\AiAnalysisResult;
use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use App\Support\InspirationPhotos;
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
 * Inspiration photos linked to the decoration areas they are for (App\Support\InspirationPhotos):
 * validated on the server, analyzed for their own areas only, combined as views of one design
 * (never added up), and kept with their photo, area and line IDs through submission, claiming and
 * booking creation.
 */
class InspirationPhotoAreasTest extends TestCase
{
    use RefreshDatabase;

    private const BRIEF = ['decor_areas' => ['entrance', 'stage']];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Cache::flush();
        Storage::fake('local');
        Mail::fake();
    }

    // ------------------------------------------------------------------ Photo analysis

    public function test_a_photo_must_be_linked_to_selected_areas_before_it_is_analyzed(): void
    {
        Http::fake();
        $analyze = fn (array $extra) => $this->postJson(route('guest.bookings.analyze-temp-image'), $extra + [
            'inspiration_image' => UploadedFile::fake()->createWithContent('stage.png', $this->pngBytes()),
            'event_type' => 'wedding',
        ]);

        $analyze(self::BRIEF)->assertStatus(422)->assertJsonValidationErrors('photo_areas');
        $analyze(self::BRIEF + ['photo_areas' => ['aisle']])->assertStatus(422)->assertJsonValidationErrors('photo_areas');
        $analyze(self::BRIEF + ['photo_areas' => ['garden']])->assertStatus(422)->assertJsonValidationErrors('photo_areas.0');
        $analyze(['photo_areas' => ['stage']])->assertStatus(422)->assertJsonValidationErrors('photo_areas');
        $analyze(self::BRIEF + ['photo_areas' => ['stage'], 'inspiration_image' => UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertStatus(422)->assertJsonValidationErrors('inspiration_image');

        Http::assertNothingSent();
        $this->assertSame([], Storage::disk('local')->allFiles('bookings/temp-analysis'));
    }

    public function test_each_photo_is_analyzed_only_for_its_own_areas(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push($this->geminiText(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($this->geminiText(['suggested_materials' => [
                $this->row('Backdrop Roses', 120, 35, 'stage'),
                $this->row('Arch Greenery', 10, 80, 'entrance'),   // not this photo's area: becomes shared, never "entrance"
            ]]))]);

        $response = $this->postJson(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => UploadedFile::fake()->createWithContent('stage.png', $this->pngBytes()),
            'event_type' => 'wedding',
            'photo_areas' => ['stage'],
        ] + self::BRIEF)->assertOk()->assertJsonPath('photo_areas', ['stage']);

        $this->assertSame(['stage', 'shared'], array_column($response->json('analysis.suggested_materials'), 'decor_area'));
        // The photo's panel lists only its own area (plus shared materials), not the other picked area.
        $this->assertSame(['stage', 'shared'], array_column($response->json('analysis.area_breakdown.areas'), 'key'));
        $payload = session('booking_analysis_payloads.' . $response->json('analysis_token'));
        $this->assertSame(['stage'], $payload['photo_areas']);

        Http::assertSent(function (HttpRequest $request) {
            $prompt = (string) (json_decode($request->body(), true)['contents'][0]['parts'][0]['text'] ?? '');

            return str_contains($prompt, 'Areas to decorate')
                && str_contains($prompt, '"stage" (Stage or backdrop)')
                && !str_contains($prompt, '"entrance" (Entrance or arch)');
        });
    }

    // ------------------------------------------------------------------ Combining photos

    public function test_photos_of_the_same_area_are_views_of_one_design_and_never_added_up(): void
    {
        $rows = InspirationPhotos::combine([
            $this->photo(1, ['stage'], [$this->row('White Roses', 120, 35, 'stage'), $this->row('Candles', 6, 40, 'stage', 'prop')]),
            $this->photo(2, ['stage'], [$this->row('White Roses', 80, 30, 'stage'), $this->row('Candles', 6, 40, 'stage', 'prop')]),
        ]);

        $this->assertCount(2, $rows);
        [$roses, $candles] = $rows;
        $this->assertSame(120.0, (float) $roses['quantity']);
        $this->assertSame([1, 2], $roses['image_ids']);
        $this->assertSame(['p1-m1', 'p2-m1'], array_column($roses['source_lines'], 'line_id'));
        $this->assertTrue($roses['needs_review']);
        $this->assertTrue($roses['view_conflict']);
        $this->assertStringContainsString('photo 1: 120 stem vs photo 2: 80 stem', $roses['review_message']);
        $this->assertSame(6.0, (float) $candles['quantity']);
        $this->assertArrayNotHasKey('view_conflict', $candles);
    }

    public function test_photos_of_different_areas_stay_separate_installations(): void
    {
        $rows = InspirationPhotos::combine([
            $this->photo(1, ['stage'], [$this->row('White Roses', 120, 35, 'stage'), $this->row('Floral Foam', 10, 25, 'shared', 'supply')]),
            $this->photo(2, ['entrance'], [$this->row('White Roses', 40, 35, 'entrance'), $this->row('Floral Foam', 4, 25, 'shared', 'supply')]),
        ]);

        $this->assertCount(4, $rows);
        $this->assertSame([120.0, 10.0, 40.0, 4.0], array_map(fn ($row) => (float) $row['quantity'], $rows));
    }

    public function test_photos_linked_through_a_shared_area_count_as_one_design(): void
    {
        $rows = InspirationPhotos::combine([
            $this->photo(1, ['stage', 'entrance'], [$this->row('Floral Foam', 10, 25, 'shared', 'supply')]),
            $this->photo(2, ['stage'], [$this->row('Floral Foam', 10, 25, 'shared', 'supply')]),
        ]);

        $this->assertCount(1, $rows);
        $this->assertSame(10.0, (float) $rows[0]['quantity']);
        $this->assertArrayNotHasKey('view_conflict', $rows[0]);
    }

    public function test_without_picked_areas_every_photo_is_a_view_of_the_same_design(): void
    {
        $rows = InspirationPhotos::combine([
            $this->photo(1, [], [$this->row('Peonies', 20, 120, null)]),
            $this->photo(2, [], [$this->row('Peonies', 20, 120, null), $this->row('Eucalyptus', 10, 40, null, 'foliage')]),
        ]);

        $this->assertSame(['Peonies', 'Eucalyptus'], array_column($rows, 'item_name'));
        $this->assertSame(20.0, (float) $rows[0]['quantity']);
    }

    // ------------------------------------------------------------------ Guest submission and claim

    public function test_photo_areas_and_line_ids_survive_guest_submission_and_claiming(): void
    {
        $session = $this->sessionPayloads([
            'tok-1' => $this->payload('one.jpg', ['stage'], [$this->row('White Roses', 120, 35, 'stage')]),
            'tok-2' => $this->payload('two.jpg', ['stage'], [$this->row('White Roses', 80, 35, 'stage')]),
            'tok-3' => $this->payload('three.jpg', ['entrance'], [$this->row('Arch Greenery', 10, 80, 'entrance', 'foliage')]),
        ]);

        $this->withSession($session)->post(route('guest.booking.store'), $this->guestFields() + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['tok-1', 'tok-2', 'tok-3']),
        ] + self::BRIEF)->assertSessionHasNoErrors()->assertRedirect();

        $temp = TemporaryGuestBooking::firstOrFail();
        $images = $temp->analysis_data['images'];
        $this->assertSame([['stage'], ['stage'], ['entrance']], array_column($images, 'photo_areas'));
        $this->assertSame('p2-m1', $images[1]['suggested_materials'][0]['line_id']);
        $this->assertSame('bookings/inspiration-images/one.jpg', $temp->inspiration_image_path);
        Storage::disk('local')->assertExists('bookings/inspiration-images/three.jpg');
        Storage::disk('local')->assertMissing('bookings/temp-analysis/one.jpg');
        $this->assertCount(2, $temp->analysis_data['suggested_materials']);
        $this->assertSame(120.0, (float) $temp->analysis_data['suggested_materials'][0]['quantity']);

        $token = Str::random(40);
        $temp->update(['claim_token_hash' => hash('sha256', $token)]);
        $user = User::factory()->create(['email' => 'photo.guest@example.com', 'email_verified_at' => now()]);
        $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $token]))->assertRedirect(route('bookings'));

        $booking = Booking::where('guest_email', 'photo.guest@example.com')->firstOrFail();
        $this->assertSame([['stage'], ['stage'], ['entrance']], array_column($booking->ai_analysis_data['images'], 'photo_areas'));
        $this->assertSame(['p1-m1', 'p3-m1'], array_column(AiAnalysisResult::where('booking_id', $booking->id)->firstOrFail()->suggested_materials, 'line_id'));
        // The two views of the stage design make one 120-stem line, not 200 stems.
        $this->assertSame(120.0, (float) BookingItem::where('booking_id', $booking->id)->where('item_name', 'White Roses')->sole()->quantity);
    }

    public function test_guest_submission_never_trusts_browser_supplied_analysis(): void
    {
        $this->post(route('guest.booking.store'), $this->guestFields() + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['forged-token']),
            'analysis_data' => json_encode(['suggested_materials' => [$this->row('Gold Roses', 1000, 9999, 'stage')]]),
        ] + self::BRIEF)->assertRedirect()->assertSessionHas('analysis_error');

        $this->assertSame(0, TemporaryGuestBooking::count());
    }

    public function test_a_photo_linked_to_an_area_that_is_no_longer_selected_is_rejected(): void
    {
        $session = $this->sessionPayloads(['tok-1' => $this->payload('one.jpg', ['aisle'], [$this->row('Aisle Petals', 5, 60, 'aisle')])]);

        $this->withSession($session)->post(route('guest.booking.store'), $this->guestFields() + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['tok-1']),
        ] + self::BRIEF)->assertSessionHasErrors('analysis_tokens');

        $this->assertSame(0, TemporaryGuestBooking::count());
    }

    public function test_more_than_the_photo_limit_is_rejected(): void
    {
        $this->post(route('guest.booking.store'), $this->guestFields() + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['a', 'b', 'c', 'd', 'e', 'f']),
        ])->assertSessionHasErrors('analysis_tokens');
    }

    // ------------------------------------------------------------------ Client submission

    public function test_a_client_can_submit_several_photos_linked_to_areas(): void
    {
        $user = $this->clientUser();
        $session = $this->sessionPayloads([
            'tok-a' => $this->payload('a.jpg', ['stage'], [$this->row('White Roses', 120, 35, 'stage')]),
            'tok-b' => $this->payload('b.jpg', ['entrance'], [$this->row('Arch Greenery', 10, 80, 'entrance', 'foliage')]),
        ]);

        $this->actingAs($user)->withSession($session)->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(40)->toDateString(),
            'event_time' => '15:00',
            'venue' => 'Manila Hotel, Manila',
            'analysis_tokens' => json_encode(['tok-a', 'tok-b']),
        ] + self::BRIEF)->assertSessionHasNoErrors()->assertRedirect();

        $booking = Booking::firstOrFail();
        $this->assertSame('bookings/inspiration-images/a.jpg', $booking->inspiration_image);
        $this->assertSame([['stage'], ['entrance']], array_column($booking->ai_analysis_data['images'], 'photo_areas'));
        $this->assertSame(['p1-m1', 'p2-m1'], array_column($booking->ai_analysis_data['suggested_materials'], 'line_id'));
        $this->assertSame(Booking::DEFAULT_QUOTATION_MARKUP, (float) $booking->multiplier);
        $this->assertSame(2, BookingItem::where('booking_id', $booking->id)->count());
    }

    // ------------------------------------------------------------------ Estimate preview and pages

    public function test_the_review_estimate_combines_this_sessions_photos_without_prices(): void
    {
        $session = $this->sessionPayloads([
            'tok-1' => $this->payload('one.jpg', ['stage'], [$this->row('White Roses', 10, 50, 'stage')]),
            'tok-2' => $this->payload('two.jpg', ['stage'], [$this->row('White Roses', 10, 50, 'stage')]),
        ]);

        $response = $this->withSession($session)->postJson(route('bookings.analysis-estimate'), ['analysis_tokens' => ['tok-1', 'tok-2', 'someone-elses-token']] + self::BRIEF)
            ->assertOk()
            ->assertJsonPath('photo_count', 2)
            ->assertJsonPath('material_count', 1)
            ->assertJsonPath('estimate.groups.1.key', 'stage')
            // One ₱500 line (not two): × 3 = ₱1,500 → ₱1,275 – ₱1,725
            ->assertJsonPath('estimate.groups.1.display', '₱1,275 – ₱1,725');

        foreach (['unit_cost_php', 'estimated_unit_cost_php', 'pricing_summary', 'raw_materials'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $response->getContent());
        }
        Storage::disk('local')->assertExists('bookings/temp-analysis/one.jpg');
    }

    public function test_tracking_pages_show_every_photo_with_its_areas_and_the_area_ranges(): void
    {
        Storage::disk('local')->put('bookings/inspiration-images/one.jpg', $this->pngBytes());
        $analysis = InspirationPhotos::analysisData([
            $this->payload('one.jpg', ['stage'], [$this->row('White Roses', 10, 50, 'stage')]) + ['temp_path' => 'bookings/inspiration-images/one.jpg'],
            $this->payload('two.jpg', ['entrance'], [$this->row('Arch Greenery', 4, 100, 'entrance', 'foliage')]),
        ], self::BRIEF);
        $token = Str::random(40);
        TemporaryGuestBooking::create($this->guestFields() + [
            'booking_type' => 'custom_ai', 'design_brief' => self::BRIEF, 'analysis_data' => $analysis,
            'inspiration_image_path' => 'bookings/inspiration-images/one.jpg',
            'claim_token_hash' => hash('sha256', $token), 'expires_at' => now()->addHours(20),
        ]);

        $html = $this->get(route('guest.bookings.show', $token))->assertOk()
            ->assertSee('Photo <span data-gallery-current>1</span> of 2', false)
            ->assertSee('data-gallery-area="stage"', false)
            ->assertSee('Inspiration photo 2 of 2 — Entrance or arch')
            ->assertSeeInOrder(['AI estimate per area', 'Indicative · not an official quotation', 'Entrance or arch', '₱1,020 – ₱1,380', 'Stage or backdrop', '₱1,275 – ₱1,725'])
            ->assertSee('raflora-annotation-viewer', false)
            ->getContent();

        $this->assertStringNotContainsString('unit_cost_php', $html);
        $this->assertStringNotContainsString('Preparation &amp; Reservation', $html);
    }

    public function test_another_client_cannot_open_a_bookings_extra_photos(): void
    {
        Storage::disk('local')->put('bookings/inspiration-images/one.jpg', $this->pngBytes());
        Storage::disk('local')->put('bookings/inspiration-images/two.jpg', $this->pngBytes());
        $owner = $this->clientUser();
        $booking = Booking::create([
            'client_id' => Client::where('email', $owner->email)->value('id'), 'event_type' => 'wedding', 'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Hall', 'status' => 'pending', 'inspiration_image' => 'bookings/inspiration-images/one.jpg',
            'ai_analysis_data' => ['images' => [['id' => 1, 'image_path' => 'bookings/inspiration-images/one.jpg'], ['id' => 2, 'image_path' => 'bookings/inspiration-images/two.jpg']]],
        ]);
        $other = User::create(['name' => 'Other', 'email' => 'other@example.com', 'password' => bcrypt('password123'), 'role' => 'client', 'email_verified_at' => now()]);

        $this->actingAs($owner)->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'image_index' => 1]))->assertOk();
        $this->actingAs($other)->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'image_index' => 1]))->assertForbidden();
    }

    // ------------------------------------------------------------------ Helpers

    private function photo(int $id, array $areas, array $rows): array
    {
        return ['id' => $id, 'photo_areas' => $areas, 'suggested_materials' => array_map(
            fn (array $row, int $i) => $row + ['image_id' => $id, 'line_id' => 'p' . $id . '-m' . ($i + 1)],
            $rows,
            array_keys($rows)
        )];
    }

    private function payload(string $file, array $photoAreas, array $rows): array
    {
        $path = 'bookings/temp-analysis/' . $file;
        Storage::disk('local')->put($path, $this->pngBytes());

        return [
            'analysis' => ['suggested_materials' => array_map(fn (array $row, int $i) => $row + ['line_id' => 'm' . ($i + 1)], $rows, array_keys($rows))],
            'temp_path' => $path,
            'original_filename' => $file,
            'photo_areas' => $photoAreas,
            'image_hash' => null,
            'raw_response' => null,
        ];
    }

    private function sessionPayloads(array $payloads): array
    {
        return ['booking_analysis_payloads' => $payloads];
    }

    private function guestFields(): array
    {
        return [
            'guest_name' => 'Photo Guest',
            'guest_email' => 'photo.guest@example.com',
            'guest_phone' => '09179876543',
            'guest_address' => '78 Magnolia Lane, Quezon City',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'Tagaytay City',
            'venue_specific' => 'The Glasshouse',
            'venue' => 'The Glasshouse, Tagaytay City',
        ];
    }

    private function row(string $name, ?float $quantity, ?float $unitCost, ?string $decorArea, string $category = 'flower'): array
    {
        return array_filter([
            'item_name' => $name,
            'category' => $category,
            'quantity' => $quantity,
            'unit_type' => $category === 'flower' ? 'stem' : 'piece',
            'unit_cost_php' => $unitCost,
            'area' => 'overall',
            'decor_area' => $decorArea,
            'is_detected' => false,
            'is_recommendation' => true,
        ], fn ($value) => $value !== null);
    }

    private function clientUser(): User
    {
        $user = User::create(['name' => 'Photo Client', 'email' => 'photo-client@example.com', 'password' => bcrypt('password123'), 'role' => 'client', 'email_verified_at' => now()]);
        Client::firstOrCreate(['email' => $user->email], ['id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Test Address']);

        return $user;
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
