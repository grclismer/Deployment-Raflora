<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Client booking Step 3 (Review & Submit): the AI Analysis Summary and the Client Profile open in
 * dialogs from cards under Request Summary, as on the guest review, instead of sitting at the bottom
 * of the page. The AI section is the existing one, moved; the profile stays read-only.
 */
class ClientBookingReviewDialogsTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now(), 'name' => 'Corazon Aquino', 'mobile_number' => '09171234567', 'address' => 'Manila']);
        Client::firstOrCreate(['email' => $user->email], ['user_id' => $user->id, 'full_name' => $user->name, 'phone' => '09171234567', 'address' => 'Manila']);

        return $this->actingAs($user)->get(route('bookings.create'))->assertOk()->getContent();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $end = $start === false ? false : strpos($html, $to, $start);

        return ($start === false || $end === false) ? '' : substr($html, $start, $end - $start);
    }

    public function test_cards_under_request_summary_open_both_dialogs(): void
    {
        $html = $this->page();
        $sidebar = $this->between($html, 'id="client-review-card-summary"', '</form>');

        $this->assertStringContainsString('data-review-dialog-open="client-review-ai-summary-modal"', $sidebar);
        $this->assertStringContainsString('View AI Analysis Summary', $sidebar);
        $this->assertStringContainsString('data-review-dialog-open="client-review-contact-modal"', $sidebar);
        $this->assertStringContainsString('Review Client Profile', $sidebar);
        $this->assertStringContainsString('Corazon Aquino · 09171234567', $sidebar);
    }

    public function test_sections_live_in_their_dialogs_and_not_in_the_review_column(): void
    {
        $html = $this->page();
        $column = $this->between($html, 'id="booking-step-3"', 'id="client-review-card-summary"');
        $aiDialog = $this->between($html, 'id="client-review-ai-summary-modal"', 'id="client-review-contact-modal"');
        $contactDialog = $this->between($html, 'id="client-review-contact-modal"', '<!-- Package Details Modal -->');

        $this->assertStringNotContainsString('id="client-review-ai-materials-section"', $column);
        $this->assertStringNotContainsString('id="client-review-contact-name"', $column);

        $this->assertSame(1, substr_count($html, 'id="client-review-ai-materials-section"'));
        foreach (['client-review-ai-materials-section', 'client-review-images-count-text', 'client-review-materials-count-text', 'client-review-ai-estimate', 'client-review-area-breakdown', 'client-review-estimate-status'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $aiDialog, $id);
        }

        $this->assertSame(1, substr_count($html, 'id="client-review-contact-name"'));
        foreach (['Client Profile', 'Authenticated', 'Corazon Aquino', '09171234567', 'Manila', 'linked to your client account'] as $text) {
            $this->assertStringContainsString($text, $contactDialog, $text);
        }
        $this->assertStringNotContainsString('<input', $contactDialog);
    }

    public function test_review_keeps_its_other_cards_and_submit_actions(): void
    {
        $html = $this->page();

        foreach (['client-review-card-event', 'client-review-card-method', 'client-review-card-images', 'client-review-card-summary'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $id);
        }
        $form = $this->between($html, 'id="client-booking-form"', '</form>');
        $this->assertStringContainsString('id="clientSubmitBtn"', $form);
        $this->assertStringContainsString('Back to Design', $form);
    }
}
