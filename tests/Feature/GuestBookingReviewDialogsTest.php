<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guest booking Step 4: the AI Analysis Summary and Contact Information open in dialogs from the
 * review sidebar instead of sitting at the bottom of the page. The AI section is the existing one,
 * moved into its dialog; the Contact dialog borrows the Step 3 fields, so the form keeps one set of inputs.
 */
class GuestBookingReviewDialogsTest extends TestCase
{
    use RefreshDatabase;

    private function page(): string
    {
        return $this->get(route('guest.booking.create'))->assertOk()->getContent();
    }

    private function between(string $html, string $from, string $to): string
    {
        $start = strpos($html, $from);
        $end = strpos($html, $to, $start ?: 0);

        return ($start === false || $end === false) ? '' : substr($html, $start, $end - $start);
    }

    public function test_sidebar_offers_both_dialogs_below_before_you_submit(): void
    {
        $html = $this->page();
        $sidebar = $this->between($html, 'id="review-card-summary"', 'Ready to Submit?');

        $this->assertStringContainsString('Before You Submit', $sidebar);
        $this->assertStringContainsString('data-review-dialog-open="review-ai-summary-modal"', $sidebar);
        $this->assertStringContainsString('View AI Analysis Summary', $sidebar);
        $this->assertStringContainsString('data-review-dialog-open="review-contact-modal"', $sidebar);
        $this->assertStringContainsString('Review Contact Information', $sidebar);
        $this->assertLessThan(strpos($sidebar, 'View AI Analysis Summary'), strpos($sidebar, 'Before You Submit'));
    }

    public function test_ai_summary_is_the_existing_section_inside_its_dialog(): void
    {
        $html = $this->page();
        $dialog = $this->between($html, 'id="review-ai-summary-modal"', 'id="review-contact-modal"');

        $this->assertSame(1, substr_count($html, 'id="review-ai-materials-section"'));
        foreach (['review-ai-materials-section', 'review-images-count-text', 'review-materials-count-text', 'review-ai-estimate', 'review-area-breakdown', 'review-materials-list'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $dialog, $id);
        }
        $this->assertStringContainsString('role="dialog"', $dialog);
    }

    public function test_contact_section_is_no_longer_rendered_at_the_bottom_and_fields_stay_single(): void
    {
        $html = $this->page();

        // The bottom card is now only a hidden holder for the values the review script writes.
        $this->assertMatchesRegularExpression('/<div id="review-card-contact" class="hidden" aria-hidden="true">/', $html);
        $this->assertStringNotContainsString("toggleReviewCard('review-card-contact')", $html);

        // One set of contact inputs, inside the form, with a dialog slot that receives them while open.
        foreach (['guest_name', 'guest_email', 'guest_phone', 'guest_address'] as $name) {
            $this->assertSame(1, substr_count($html, 'name="' . $name . '"'), $name);
        }
        $this->assertStringContainsString('id="guest-contact-fields"', $html);
        $this->assertStringContainsString('data-contact-fields-slot', $this->between($html, 'id="review-contact-modal"', 'id="booking-area-details-modal"'));
        $this->assertLessThan(strpos($html, '</form>'), strpos($html, 'id="review-contact-modal"'));
    }

    public function test_step_4_keeps_its_other_cards_and_actions(): void
    {
        $html = $this->page();

        foreach (['review-card-event', 'review-card-images', 'review-card-summary', 'nav-step-4'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $html, $id);
        }
    }

    // The booking method label sits in the Inspiration Images / Selected Package card; the separate card is gone.
    public function test_booking_method_label_is_inside_the_images_or_package_card(): void
    {
        $html = $this->page();
        $card = $this->between($html, 'id="review-card-images"', 'id="review-card-images-body"');

        $this->assertStringNotContainsString('id="review-card-method"', $html);
        $this->assertSame(1, substr_count($html, 'id="review-method-title"'));
        $this->assertStringContainsString('id="review-media-title"', $card);
        $this->assertStringContainsString('id="review-method-title"', $card);
        $this->assertStringContainsString('onclick="showGuestStep(2)"', $card);
        $this->assertStringContainsString('Submit Booking Request', $html);
        $this->assertStringContainsString('Booking Summary', $html);
    }
}
