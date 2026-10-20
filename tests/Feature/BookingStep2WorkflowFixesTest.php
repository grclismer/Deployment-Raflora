<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Booking Step 2 defects found during the frontend stabilization:
 *  - a photo analyzed for some of the picked areas was always refused at submission (its analysis
 *    is hashed with the brief narrowed to its own areas, the submission compared the full brief);
 *  - the client form analyzed "Other" event types with different details than it submitted;
 *  - the refusal message (analysis_error) was never shown;
 *  - estimate refreshes and venue searches used up the analysis and submission rate limits.
 */
class BookingStep2WorkflowFixesTest extends TestCase
{
    use RefreshDatabase;

    private const AREAS = ['decor_areas' => ['entrance', 'stage']];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        Cache::flush();
        Storage::fake('local');
        Mail::fake();
    }

    // ------------------------------------------------------------------ Per-photo context check

    public function test_guest_photo_for_one_of_two_areas_is_accepted_at_submission(): void
    {
        $this->fakeGemini();
        $token = $this->postJson(route('guest.bookings.analyze-temp-image'), $this->guestEvent() + self::AREAS + [
            'inspiration_image' => $this->png(),
            'photo_areas' => ['stage'],
        ])->assertOk()->json('analysis_token');

        $this->post(route('guest.booking.store'), $this->guestEvent() + $this->guestContact() + self::AREAS + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
        ])->assertSessionMissing('analysis_error')->assertRedirect();

        $this->assertSame(1, TemporaryGuestBooking::count());
    }

    public function test_client_photo_for_one_of_two_areas_is_accepted_at_submission(): void
    {
        $this->fakeGemini();
        $user = $this->clientUser();
        $token = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), $this->clientEvent() + self::AREAS + [
            'inspiration_image' => $this->png(),
            'photo_areas' => ['stage'],
        ])->assertOk()->json('analysis_token');

        $this->actingAs($user)->post(route('bookings.store'), $this->clientEvent() + self::AREAS + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
        ])->assertSessionMissing('analysis_error')->assertRedirect();

        $this->assertSame(1, Booking::count());
    }

    public function test_only_a_change_to_the_photos_own_area_makes_it_out_of_date(): void
    {
        $this->fakeGemini();
        $brief = self::AREAS + ['decor_area_notes' => ['stage' => 'Keep the couple visible.']];
        $token = $this->postJson(route('guest.bookings.analyze-temp-image'), $this->guestEvent() + $brief + [
            'inspiration_image' => $this->png(),
            'photo_areas' => ['stage'],
        ])->assertOk()->json('analysis_token');
        $submit = fn (array $notes, string $email) => $this->post(route('guest.booking.store'), $this->guestEvent() + array_merge($this->guestContact(), ['guest_email' => $email]) + self::AREAS + [
            'decor_area_notes' => $notes,
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
        ]);

        // The stage photo was analyzed with the stage note: changing it needs a new analysis.
        $submit(['stage' => 'Low and lush.'], 'one@example.com')
            ->assertSessionHas('analysis_error', 'Your event details were modified after your photos were analyzed. Please re-analyze your inspiration photos before submitting.');
        $this->assertSame(0, TemporaryGuestBooking::count());

        // A note for the other area does not concern this photo.
        $submit(['stage' => 'Keep the couple visible.', 'entrance' => 'Full arch.'], 'two@example.com')->assertSessionMissing('analysis_error');
        $this->assertSame(1, TemporaryGuestBooking::count());
        $this->assertSame(['stage' => 'Keep the couple visible.', 'entrance' => 'Full arch.'], TemporaryGuestBooking::first()->design_brief['decor_area_notes']);
    }

    // ------------------------------------------------------------------ "Other" event types

    public function test_client_other_event_type_is_analyzed_and_submitted_with_the_same_details(): void
    {
        $this->fakeGemini();
        $user = $this->clientUser();
        $event = ['event_type' => 'other', 'other_event_type' => 'Product launch'] + $this->clientEvent();
        $token = $this->actingAs($user)->postJson(route('bookings.analyze-temp-image'), $event + ['inspiration_image' => $this->png()])
            ->assertOk()->json('analysis_token');

        // The AI is told the event the client described, not "other".
        Http::assertSent(fn (HttpRequest $request) => str_contains((string) ($request->data()['contents'][0]['parts'][0]['text'] ?? ''), 'Product launch'));

        $this->actingAs($user)->post(route('bookings.store'), $event + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode([$token]),
        ])->assertSessionMissing('analysis_error')->assertRedirect();

        $this->assertSame('Product launch', Booking::firstOrFail()->event_type);
    }

    public function test_client_form_sends_other_event_types_as_they_are_submitted(): void
    {
        $html = $this->actingAs($this->clientUser())->get(route('bookings.create'))->assertOk()->getContent();

        $this->assertStringContainsString("formData.append('other_event_type', value('input[name=\"other_event_type\"]'));", $html);
        $this->assertStringNotContainsString('Other request type:', $html);
    }

    // ------------------------------------------------------------------ The refusal is shown

    public function test_a_refused_guest_submission_shows_why_on_step_two(): void
    {
        $this->post(route('guest.booking.store'), $this->guestEvent() + $this->guestContact() + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['unknown-token']),
        ])->assertRedirect()->assertSessionHas('analysis_error');
        $message = session('analysis_error');

        $this->get(route('guest.booking.create'))
            ->assertSee('id="guest-analysis-error"', false)
            ->assertSee($message)
            ->assertSee('const initialGuestStep = 2;', false);
    }

    public function test_a_refused_client_submission_shows_why_on_step_two(): void
    {
        $user = $this->clientUser();
        $this->actingAs($user)->post(route('bookings.store'), $this->clientEvent() + [
            'booking_type' => 'custom_ai',
            'analysis_tokens' => json_encode(['unknown-token']),
        ])->assertRedirect()->assertSessionHas('analysis_error', 'Some of your photos need to be analyzed again before you submit. Please remove and re-add them.');

        $this->actingAs($user)->get(route('bookings.create'))
            ->assertSee('id="client-analysis-error"', false)
            ->assertSee('Some of your photos need to be analyzed again before you submit.')
            ->assertSee('let currentStep = 2;', false);
        $this->assertSame(0, Booking::count());
    }

    // ------------------------------------------------------------------ Rate limits

    public function test_estimate_refreshes_and_venue_searches_do_not_use_up_the_submission_limit(): void
    {
        Http::fake();
        foreach (range(1, 4) as $i) {
            $this->postJson(route('bookings.analysis-estimate'), ['analysis_tokens' => []])->assertOk();
        }
        $this->assertNotSame(429, $this->getJson(route('location.search', ['q' => 'Tagaytay']))->status());

        // Still the normal validation response, not "Too Many Requests".
        $this->post(route('guest.booking.store'), ['booking_type' => 'preset'])->assertStatus(302);
        foreach (range(1, 5) as $i) {
            $this->postJson(route('guest.bookings.analyze-temp-image'), [])->assertStatus(422);
        }
        // The analysis limit itself still applies.
        $this->postJson(route('guest.bookings.analyze-temp-image'), [])->assertStatus(429);
    }

    public function test_a_client_can_submit_after_refreshing_the_estimate(): void
    {
        $user = $this->clientUser();
        foreach (range(1, 6) as $i) {
            $this->actingAs($user)->postJson(route('bookings.analysis-estimate'), ['analysis_tokens' => []])->assertOk();
        }

        $this->actingAs($user)->post(route('bookings.store'), $this->clientEvent() + ['booking_type' => 'preset'])->assertStatus(302);
    }

    // ------------------------------------------------------------------ Drafts

    public function test_the_guest_draft_is_kept_until_the_request_is_saved(): void
    {
        $create = $this->get(route('guest.booking.create'))->getContent();
        // Submitting no longer clears the draft (a refused submission would lose the guest's work)...
        $this->assertStringNotContainsString("clearGuestDraft();\n                    guestForm.dataset.submitting = 'true';", $create);

        // ...the tracking page does, once the request exists.
        $temp = new TemporaryGuestBooking();
        $temp->fill($this->guestContact() + ['event_type' => 'wedding', 'event_date' => now()->addDays(30)->toDateString(), 'venue' => 'The Glasshouse', 'booking_type' => 'preset', 'expires_at' => now()->addDay()]);
        $raw = $temp->generateToken();
        $temp->save();
        $this->withSession(['success' => 'Booking request saved!'])->get(route('guest.bookings.show', ['token' => $raw]))
            ->assertOk()
            ->assertSee("sessionStorage.removeItem('raflora_guest_booking_draft_v1')", false);
        $this->flushSession();
        $this->get(route('guest.bookings.show', ['token' => $raw]))
            ->assertDontSee("sessionStorage.removeItem('raflora_guest_booking_draft_v1')", false);
    }

    // ------------------------------------------------------------------ Handoff to Admin and Staff

    public function test_area_notes_reach_the_admin_and_staff_booking_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $staff = User::factory()->create(['role' => 'staff']);
        $booking = Booking::create($this->clientEvent() + [
            'design_brief' => ['decor_areas' => ['entrance', 'stage'], 'decor_area_notes' => ['entrance' => 'Full arch with white accents.', 'stage' => 'Keep the couple visible.']],
            'staff_id' => $staff->id,
            'status' => 'approved',
            'total_quoted' => 15000,
        ]);

        foreach ([[$admin, route('admin.bookings.show', $booking)], [$staff, route('staff.events.show', $booking)]] as [$user, $url]) {
            $this->actingAs($user)->get($url)->assertOk()
                ->assertSee('Area preferences')
                ->assertSee('Entrance or arch: &quot;Full arch with white accents.&quot;', false)
                ->assertSee('Stage or backdrop: &quot;Keep the couple visible.&quot;', false);
        }
    }

    // ------------------------------------------------------------------ Helpers

    private function fakeGemini(): void
    {
        $text = fn (array $json) => ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
        $row = fn (string $area) => ['item_name' => 'Backdrop Roses', 'category' => 'flower', 'quantity' => 120, 'unit_type' => 'stem', 'unit_cost_php' => 35, 'area' => 'overall', 'decor_area' => $area, 'is_detected' => false, 'is_recommendation' => true];
        Http::fake(['*' => Http::sequence()
            ->push($text(['is_floral_or_event_related' => true, 'is_clear_usable' => true, 'rejection_reason' => null]))
            ->push($text(['suggested_materials' => [$row('stage')]]))]);
    }

    private function guestEvent(): array
    {
        return [
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue_city' => 'Tagaytay City',
            'venue_specific' => 'The Glasshouse',
            'venue' => 'The Glasshouse, Tagaytay City',
            'guest_count' => 120,
            'table_count' => 12,
        ];
    }

    private function guestContact(): array
    {
        return ['guest_name' => 'Ana Reyes', 'guest_email' => 'ana.reyes@example.com', 'guest_phone' => '09171234567', 'guest_address' => '12 Mabini St, Makati'];
    }

    private function clientEvent(): array
    {
        return [
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(30)->toDateString(),
            'event_time' => '15:00',
            'end_time' => '21:00',
            'venue' => 'The Glasshouse, Tagaytay City',
            'guest_count' => 120,
            'table_count' => 12,
        ];
    }

    private function clientUser(): User
    {
        $user = User::create(['name' => 'Step Two Client', 'email' => 'step2-client@example.com', 'password' => bcrypt('password123'), 'role' => 'client', 'email_verified_at' => now()]);
        Client::firstOrCreate(['email' => $user->email], ['id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Makati City']);

        return $user;
    }

    private function png(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('stage.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
    }
}
