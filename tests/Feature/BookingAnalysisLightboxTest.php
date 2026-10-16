<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\GeminiVisionService;
use ReflectionMethod;
use Tests\TestCase;

class BookingAnalysisLightboxTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_analysis_view_initializes_lightbox_after_dom_is_ready(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'inspiration_image' => 'images/inspiration.jpg',
            'status' => 'quotation_sent',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'amount_paid' => 100,
            'payment_type' => 'gcash',
            'reference_number' => 'REF-100',
            'status' => 'verified',
        ]);

        $html = view('client.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => [],
            'totalCost' => 2500,
        ])->render();

        $this->assertStringContainsString("window.addEventListener('DOMContentLoaded'", $html);
        $this->assertStringContainsString("data-image-lightbox-trigger", $html);
        $this->assertStringContainsString("data-annotated-image-stage", $html);
        // One shared viewer enlarges the photo and its boxes as one unit, with the legend, zoom and pan.
        $this->assertStringContainsString('id="raflora-annotation-viewer"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('window.RafloraAnnotationViewer = { open: open, close: close }', $html);
        $this->assertStringContainsString('window.RafloraAnnotationViewer.open({', $html);
        $this->assertStringContainsString('data-viewer-legend', $html);
        $this->assertStringContainsString('data-viewer-zoom-in', $html);
        $this->assertStringNotContainsString('imageLightboxModal', $html);
        // Boxes are percent rectangles over the photo, so only the stage shape is refitted.
        $this->assertStringContainsString("window.fitAnnotatedImageStage", $html);
        $this->assertStringContainsString("window.highlightAnnotationLine", $html);
    }

    public function test_guest_analysis_view_initializes_lightbox_after_dom_is_ready(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'inspiration_image' => 'images/inspiration.jpg',
            'status' => 'pending',
            'guest_access_token' => 'guest-token-123',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09170000000',
        ]);

        Payment::create([
            'booking_id' => $booking->id,
            'amount_paid' => 100,
            'payment_type' => 'gcash',
            'reference_number' => 'REF-100',
            'status' => 'verified',
        ]);

        $html = view('guest.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => [],
            'totalCost' => 2500,
            'token' => 'guest-token-123',
        ])->render();

        $this->assertStringContainsString("window.addEventListener('DOMContentLoaded'", $html);
        $this->assertStringContainsString("data-image-lightbox-trigger", $html);
        $this->assertStringContainsString("data-annotated-image-stage", $html);
        // One shared viewer enlarges the photo and its boxes as one unit, with the legend, zoom and pan.
        $this->assertStringContainsString('id="raflora-annotation-viewer"', $html);
        $this->assertStringContainsString('aria-modal="true"', $html);
        $this->assertStringContainsString('window.RafloraAnnotationViewer = { open: open, close: close }', $html);
        $this->assertStringContainsString('window.RafloraAnnotationViewer.open({', $html);
        $this->assertStringContainsString('data-viewer-legend', $html);
        $this->assertStringContainsString('data-viewer-zoom-in', $html);
        $this->assertStringNotContainsString('imageLightboxModal', $html);
        // Boxes are percent rectangles over the photo, so only the stage shape is refitted.
        $this->assertStringContainsString("window.fitAnnotatedImageStage", $html);
        $this->assertStringContainsString("window.highlightAnnotationLine", $html);
    }

    public function test_client_analysis_annotations_require_detected_items_with_valid_locations(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'inspiration_image' => 'images/inspiration.jpg',
            'status' => 'quotation_sent',
        ]);

        $materials = $this->groundingTestMaterials();
        $html = view('client.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => $materials,
            'totalCost' => 2500,
        ])->render();

        // Saved before boxes were requested: these rows only have a point, drawn as a numbered dot.
        $this->assertSame(7, substr_count($html, 'data-annotation-point '));
        $this->assertSame(0, substr_count($html, 'data-annotation-box '));
        foreach ($materials as $material) {
            $this->assertStringContainsString($material['item_name'], $html);
        }
        $this->assertStringContainsString('Ceiling', $html);
        $this->assertStringNotContainsString('>Area<', $html);
        $this->assertStringContainsString('AI Recommendation', $html);
    }

    public function test_guest_analysis_annotations_require_detected_items_with_valid_locations(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'inspiration_image' => 'images/inspiration.jpg',
            'status' => 'pending',
            'guest_access_token' => 'guest-token-grounding',
            'guest_email' => 'grounding@example.com',
            'guest_phone' => '09170000000',
        ]);

        $materials = $this->groundingTestMaterials();
        $html = view('guest.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => $materials,
            'totalCost' => 2500,
            'token' => 'guest-token-grounding',
        ])->render();

        // Saved before boxes were requested: these rows only have a point, drawn as a numbered dot.
        $this->assertSame(7, substr_count($html, 'data-annotation-point '));
        $this->assertSame(0, substr_count($html, 'data-annotation-box '));
        foreach ($materials as $material) {
            $this->assertStringContainsString($material['item_name'], $html);
        }
        $this->assertStringContainsString('Ceiling', $html);
        $this->assertStringNotContainsString('>Area<', $html);
        $this->assertStringContainsString('AI Recommendation', $html);
    }

    public function test_visual_locations_reject_invalid_values_and_clamp_regions_to_image_bounds(): void
    {
        $normalize = new ReflectionMethod(GeminiVisionService::class, 'normalizeVisualLocation');
        $normalize->setAccessible(true);
        $service = new GeminiVisionService();

        $this->assertSame(['x' => 0.8, 'y' => 0.75, 'width' => 0.2, 'height' => 0.25], $normalize->invoke($service, [
            'x' => 0.8, 'y' => 0.75, 'width' => 0.9, 'height' => 0.4,
        ]));
        $this->assertNull($normalize->invoke($service, ['x' => 'not-a-number', 'y' => 0.5]));
        $this->assertNull($normalize->invoke($service, ['x' => 0.5, 'y' => 0.5, 'width' => -0.1]));
        $this->assertNull($normalize->invoke($service, ['x' => INF, 'y' => 0.5]));
    }

    private function groundingTestMaterials(): array
    {
        $materials = [];
        for ($index = 1; $index <= 7; $index++) {
            $materials[] = [
                'item_name' => 'Detected Item ' . $index,
                'category' => 'flower',
                'quantity' => 1,
                'unit_type' => 'piece',
                'unit_cost_php' => 10,
                'area' => 'ceiling',
                'is_detected' => true,
                'is_recommendation' => false,
                'visual_location' => ['x' => $index / 10, 'y' => $index / 10],
            ];
        }

        return array_merge($materials, [
            [
                'item_name' => 'Recommended Item',
                'category' => 'supply',
                'quantity' => 1,
                'unit_type' => 'piece',
                'unit_cost_php' => 10,
                'area' => 'stage',
                'is_detected' => false,
                'is_recommendation' => true,
                'visual_location' => ['x' => 0.2, 'y' => 0.2],
            ],
            [
                'item_name' => 'Detected Without Location',
                'category' => 'flower',
                'quantity' => 1,
                'unit_type' => 'piece',
                'unit_cost_php' => 10,
                'area' => 'stage',
                'is_detected' => true,
                'is_recommendation' => false,
            ],
            [
                'item_name' => 'Detected Invalid Location',
                'category' => 'flower',
                'quantity' => 1,
                'unit_type' => 'piece',
                'unit_cost_php' => 10,
                'area' => 'stage',
                'is_detected' => true,
                'is_recommendation' => false,
                'visual_location' => ['x' => 2, 'y' => -1],
            ],
        ]);
    }
}