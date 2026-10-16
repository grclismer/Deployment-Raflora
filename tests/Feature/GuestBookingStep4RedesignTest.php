<?php

namespace Tests\Feature;

use App\Models\Package;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GuestBookingStep4RedesignTest extends TestCase
{
    use RefreshDatabase;

    private function createPackage(): Package
    {
        return Package::create([
            'title' => 'Curated Wedding Package',
            'description' => 'A wonderful set of floral decor',
            'price' => 15000.00,
            'included_items' => ['Stage Backdrop', 'Bridal Bouquet'],
            'is_active' => true,
        ]);
    }

    public function test_guest_booking_step_4_renders_compact_summary_cards_and_modal(): void
    {
        $this->createPackage();

        $response = $this->get(route('guest.booking.create'));
        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Page title and Step 4 header
        $this->assertStringContainsString('id="booking-page-title"', $html);
        $this->assertStringContainsString('Review your booking request', $html);
        $this->assertStringContainsString('NO PAYMENT IS REQUIRED AT THIS STAGE', $html);

        // 2. Compact review cards
        $this->assertStringContainsString('id="review-card-event"', $html);
        $this->assertStringContainsString('id="review-card-method"', $html);
        $this->assertStringContainsString('id="review-card-images"', $html);
        $this->assertStringContainsString('id="review-ai-materials-section"', $html);
        $this->assertStringContainsString('id="review-card-contact"', $html);

        // 3. Mobile accordion summary elements
        $this->assertStringContainsString('id="review-event-mobile-summary"', $html);
        $this->assertStringContainsString('id="review-method-mobile-summary"', $html);
        $this->assertStringContainsString('id="review-media-mobile-summary"', $html);
        $this->assertStringContainsString('id="review-contact-mobile-summary"', $html);
        $this->assertStringContainsString('toggleReviewCard', $html);

        // 4. Area Details Modal elements
        $this->assertStringContainsString('id="booking-area-details-modal"', $html);
        $this->assertStringContainsString('id="area-modal-title"', $html);
        $this->assertStringContainsString('id="area-modal-desc"', $html);
        $this->assertStringContainsString('id="area-modal-note-display"', $html);
        $this->assertStringContainsString('id="area-modal-note-editor"', $html);
        $this->assertStringContainsString('id="area-modal-note-input"', $html);
        $this->assertStringContainsString('id="area-modal-edit-note-btn"', $html);
        $this->assertStringContainsString('id="area-modal-save-note-btn"', $html);
        $this->assertStringContainsString('id="area-modal-cancel-note-btn"', $html);
        $this->assertStringContainsString('id="area-modal-estimate-price"', $html);
        $this->assertStringContainsString('id="area-modal-partial-badge"', $html);
        $this->assertStringContainsString('id="area-modal-table-body"', $html);
        $this->assertStringContainsString('id="area-modal-cards-list"', $html);
        $this->assertStringContainsString('id="area-modal-toggle-more-btn"', $html);

        // 5. Modal JavaScript functions
        $this->assertStringContainsString('openAreaDetailsModal', $html);
        $this->assertStringContainsString('closeAreaDetailsModal', $html);
        $this->assertStringContainsString('RafloraStageBackdropModal', $html);
    }
}
