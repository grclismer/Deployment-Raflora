<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Services\GeminiVisionService;
use App\Support\ImageOrientation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Annotations on the inspiration photo come from Gemini's own bounding boxes (the AI never draws
 * on the photo), are validated by the server, kept in the frame people see (including turned
 * phone photos), and drawn as percent boxes only for detected items on the photo they were found in.
 */
class AiAnnotationAccuracyTest extends TestCase
{
    use RefreshDatabase;

    /** 4x2 JPEG (left half red, right half blue) without EXIF data. */
    private const JPEG_4X2 = '/9j/4AAQSkZJRgABAQAAAQABAAD/4gHYSUNDX1BST0ZJTEUAAQEAAAHIAAAAAAQwAABtbnRyUkdCIFhZWiAH4AABAAEAAAAAAABhY3NwAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAQAA9tYAAQAAAADTLQAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAlkZXNjAAAA8AAAACRyWFlaAAABFAAAABRnWFlaAAABKAAAABRiWFlaAAABPAAAABR3dHB0AAABUAAAABRyVFJDAAABZAAAAChnVFJDAAABZAAAAChiVFJDAAABZAAAAChjcHJ0AAABjAAAADxtbHVjAAAAAAAAAAEAAAAMZW5VUwAAAAgAAAAcAHMAUgBHAEJYWVogAAAAAAAAb6IAADj1AAADkFhZWiAAAAAAAABimQAAt4UAABjaWFlaIAAAAAAAACSgAAAPhAAAts9YWVogAAAAAAAA9tYAAQAAAADTLXBhcmEAAAAAAAQAAAACZmYAAPKnAAANWQAAE9AAAApbAAAAAAAAAABtbHVjAAAAAAAAAAEAAAAMZW5VUwAAACAAAAAcAEcAbwBvAGcAbABlACAASQBuAGMALgAgADIAMAAxADb/2wBDABALDA4MChAODQ4SERATGCgaGBYWGDEjJR0oOjM9PDkzODdASFxOQERXRTc4UG1RV19iZ2hnPk1xeXBkeFxlZ2P/2wBDARESEhgVGC8aGi9jQjhCY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2NjY2P/wAARCAACAAQDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAb/xAAZEAEAAgMAAAAAAAAAAAAAAAAAAgQzgbH/xAAVAQEBAAAAAAAAAAAAAAAAAAABBv/EABoRAAAHAAAAAAAAAAAAAAAAAAABAgQzcbH/2gAMAwEAAhEDEQA/AI+znlrgCoawIosA5mXZ6P/Z';

    private array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------------------ Known image, end to end

    /**
     * Locks in the whole coordinate path with a known photo: a 4x2 JPEG whose stored left half is
     * red, saved by a phone with EXIF Orientation 6 (shown turned a quarter clockwise, 2 wide and
     * 4 tall, red on top). Gemini answers for whichever frame it was actually sent, the box
     * passes through orientation, validation and the overlay, and must land on the top half of
     * the photo as people see it: left 0%, top 0%, width 100%, height 50%.
     */
    public function test_known_photo_box_lands_on_the_same_pixels_people_see(): void
    {
        $sentSize = null;
        Http::fake(function (HttpRequest $request) use (&$sentSize) {
            $bytes = base64_decode(json_decode($request->body(), true)['contents'][0]['parts'][1]['inline_data']['data'] ?? '');
            $sentSize = array_slice(getimagesizefromstring($bytes) ?: [], 0, 2);
            // The red half in the frame Gemini received: the left half of the stored 4x2 pixels,
            // or the top half when the server turned the pixels upright (GD available).
            $red = $sentSize === [4, 2] ? [0, 0, 1000, 500] : [0, 0, 500, 1000];

            return Http::response($this->geminiText(['suggested_materials' => [
                $this->row('Red Roses', ['boxes' => [['box_2d' => $red, 'confidence' => 0.95]]]),
            ]]));
        });

        $photo = $this->jpegFile(6);
        $service = new GeminiVisionService();
        $result = $service->analyzeImageFromPath($photo, null, 'wedding');
        $this->assertSame(6, $result['image_metadata']['exif_orientation']);
        $this->assertContains($sentSize, [[4, 2], [2, 4]]);
        if ($sentSize === [4, 2]) {
            // Stored pixels were sent with the turn tag cleared, so Gemini saw them unturned.
            $this->assertSame(6, $result['image_metadata']['box_orientation']);
        }

        $analysis = $service->normalizeAiAnalysis($result['analysis']);
        $this->assertSame([0.0, 0.0, 500.0, 1000.0], $analysis['suggested_materials'][0]['box_2d']);

        [$width, $height] = ImageOrientation::displaySize($photo);
        $this->assertSame([2, 4], [$width, $height]);

        $html = view('components.annotated-image', [
            'previewImage' => 'https://example.test/photo.jpg',
            'imageAspectRatio' => "{$width} / {$height}",
            'imageWidth' => $width,
            'imageHeight' => $height,
            'overlayItems' => collect($service->normalizeAreaAnalysis($analysis))->flatMap(fn ($group) => $group['items'])->values(),
        ])->render();

        $this->assertStringContainsString('aspect-ratio: 2 / 4;', $html);
        $this->assertStringContainsString('left: 0%; top: 0%; width: 100%; height: 50%;', $html);
        $this->assertSame(1, substr_count($html, 'data-annotation-box '));
    }

    public function test_a_box_converts_to_percentages_of_the_whole_photo(): void
    {
        $service = new GeminiVisionService();

        // [ymin, xmin, ymax, xmax] on 0-1000: y before x, both from the top-left corner.
        $this->assertSame(['x' => 0.2, 'y' => 0.1, 'width' => 0.4, 'height' => 0.4], $service->boxToRegion([100, 200, 500, 600]));
        $this->assertSame(['x' => 0.25, 'y' => 0.5, 'width' => 0.5, 'height' => 0.25], $service->boxToRegion([500, 250, 750, 750]));

        $html = $this->renderStage($service, [$this->row('White Roses', ['boxes' => [['box_2d' => [500, 250, 750, 750]]]])], 800, 400);
        $this->assertStringContainsString('left: 25%; top: 50%; width: 50%; height: 25%;', $html);
        $this->assertStringContainsString('aspect-ratio: 800 / 400;', $html);
        // No cropping: the photo is shown whole and the stage takes its exact shape.
        $this->assertMatchesRegularExpression('/<img [^>]*class="block h-auto w-full object-contain/', $html);
        $this->assertStringNotContainsString('object-cover', $html);
    }

    // ------------------------------------------------------------------ Server-side validation

    public function test_boxes_slightly_past_the_edge_are_clamped_and_unusable_ones_dropped(): void
    {
        $service = new GeminiVisionService();

        $this->assertSame([0.0, 100.0, 400.0, 1000.0], $service->normalizeBox([-40, 100, 400, 1060]));
        $this->assertSame([100.0, 200.0, 500.0, 600.0], $service->normalizeBox([100, 200, 500, 600]));

        foreach ([
            'pixel scale' => [120, 340, 2400, 1800],
            'entirely below the image' => [1000, 0, 1050, 100],
            'entirely left of the image' => [100, -90, 300, 0],
            'edges reversed' => [500, 200, 100, 600],
            'zero size' => [300, 300, 300, 600],
            'no size after clamping' => [999, 100, 1080, 600],
            '0-1 shares' => [0.1, 0.2, 0.5, 0.6],
            'three values' => [100, 200, 500],
            'named keys' => ['ymin' => 100, 'xmin' => 200, 'ymax' => 500, 'xmax' => 600],
            'text' => [100, 'left', 500, 600],
            'infinite' => [100, 200, INF, 600],
        ] as $case => $box) {
            $this->assertNull($service->normalizeBox($box), $case);
        }

        $analysis = $service->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('Peonies', ['boxes' => [['box_2d' => [500, 200, 100, 600]]]]),
        ]]);
        $this->assertSame([], $analysis['suggested_materials'][0]['boxes']);
        $this->assertNull($analysis['suggested_materials'][0]['visual_location']);

        $groups = $service->normalizeAreaAnalysis($analysis);
        $this->assertTrue($groups[0]['items'][0]['needs_review']);
    }

    public function test_only_detected_rows_keep_boxes_and_each_row_has_a_line_id(): void
    {
        $analysis = (new GeminiVisionService())->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', ['boxes' => [['box_2d' => [100, 200, 300, 400], 'confidence' => 0.9], ['box_2d' => [100, 600, 300, 800], 'confidence' => 0.92]]]),
            // Orphans: a recommendation's boxes are dropped.
            $this->row('Floral Foam', ['boxes' => [['box_2d' => [0, 0, 500, 500]]], 'is_detected' => false, 'is_recommendation' => true]),
            // Older single-box answers still work.
            $this->row('Eucalyptus', ['box_2d' => [500, 0, 1000, 250]]),
        ]]);

        [$roses, $foam, $eucalyptus] = $analysis['suggested_materials'];
        $this->assertSame(['m1', 'm2', 'm3'], array_column($analysis['suggested_materials'], 'line_id'));
        $this->assertCount(2, $roses['boxes']);
        $this->assertSame([100.0, 200.0, 300.0, 400.0], $roses['box_2d']);
        $this->assertSame(['x' => 0.2, 'y' => 0.1, 'width' => 0.2, 'height' => 0.2], $roses['visual_location']);
        $this->assertSame([], $foam['boxes']);
        $this->assertNull($foam['box_2d']);
        $this->assertNull($foam['visual_location']);
        $this->assertSame([[ 'box_2d' => [500.0, 0.0, 1000.0, 250.0], 'confidence' => null, 'possible' => false ]], $eucalyptus['boxes']);
    }

    public function test_one_box_per_cluster_without_duplicates_and_at_most_twelve(): void
    {
        $service = new GeminiVisionService();
        $clusters = [];
        for ($i = 0; $i < 15; $i++) {
            $clusters[] = ['box_2d' => [100 + ($i * 50), 100, 140 + ($i * 50), 200], 'confidence' => 0.9];
        }
        // The same cluster boxed twice.
        array_splice($clusters, 1, 0, [['box_2d' => [101, 101, 141, 199], 'confidence' => 0.9]]);

        $row = $service->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', ['boxes' => $clusters, 'quantity' => 180, 'quantity_basis' => '15 tables × 12 stems = 180 stems']),
        ]])['suggested_materials'][0];

        $this->assertCount(GeminiVisionService::MAX_BOXES_PER_ROW, $row['boxes']);
        $this->assertSame([150.0, 100.0, 190.0, 200.0], $row['boxes'][1]['box_2d']);
        // Quantities come from the counting, never from the number of boxes.
        $this->assertSame(180.0, $row['quantity']);
        $this->assertFalse($row['needs_review']);
    }

    public function test_low_confidence_boxes_are_possible_or_hidden_and_flagged_for_review(): void
    {
        $service = new GeminiVisionService();
        [$clear, $possible, $unsure, $unsureRow] = $service->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', ['confidence' => 0.95, 'boxes' => [['box_2d' => [100, 100, 300, 300], 'confidence' => 0.9]]]),
            $this->row('Lisianthus', ['confidence' => 0.95, 'boxes' => [['box_2d' => [100, 100, 300, 300], 'confidence' => 0.62]]]),
            $this->row('Waxflower', ['confidence' => 0.95, 'boxes' => [['box_2d' => [100, 100, 300, 300], 'confidence' => 0.3], ['box_2d' => [500, 500, 700, 700], 'confidence' => 0.9]]]),
            // A box is never surer than its row.
            $this->row('Statice', ['confidence' => 0.4, 'boxes' => [['box_2d' => [100, 100, 300, 300], 'confidence' => 0.9]]]),
        ]])['suggested_materials'];

        $this->assertFalse($clear['boxes'][0]['possible']);
        $this->assertFalse($clear['needs_review']);

        $this->assertTrue($possible['boxes'][0]['possible']);
        $this->assertTrue($possible['needs_review']);
        $this->assertStringContainsString('Possible match', $possible['review_message']);

        $this->assertCount(1, $unsure['boxes']);
        $this->assertSame(1, $unsure['hidden_box_count']);
        $this->assertTrue($unsure['needs_review']);
        $this->assertStringContainsString('hidden for staff review', $unsure['review_message']);

        $this->assertSame([], $unsureRow['boxes']);
        $this->assertSame(1, $unsureRow['hidden_box_count']);
        $this->assertNull($unsureRow['visual_location']);
        $this->assertTrue($unsureRow['needs_review']);

        $html = $this->renderStage($service, [$possible, $clear], 800, 600);
        $this->assertSame(1, substr_count($html, 'data-possible'));
        $this->assertStringContainsString('border-style: dashed;', $html);
        $this->assertStringContainsString('>1?</span>', $html);
    }

    public function test_prompt_asks_for_one_box_per_cluster_on_geminis_scale(): void
    {
        Http::fake(['*' => Http::response($this->geminiText(['suggested_materials' => [$this->row('White Roses')]]))]);

        (new GeminiVisionService())->analyzeImageFromPath($this->jpegFile(1), null, 'wedding');

        Http::assertSent(function (HttpRequest $request) {
            $prompt = (string) (json_decode($request->body(), true)['contents'][0]['parts'][0]['text'] ?? '');

            return str_contains($prompt, '"boxes": [{ "box_2d": [ymin, xmin, ymax, xmax], "confidence": number from 0.0 to 1.0 }]')
                && str_contains($prompt, 'on a 0-1000 scale')
                && str_contains($prompt, 'never one box per stem or bloom')
                && str_contains($prompt, 'never from the number of boxes')
                && !str_contains($prompt, '"visual_location"')
                && !str_contains($prompt, '[ymin, xmin, ymax, xmax] or null');
        });
    }

    public function test_the_cache_key_includes_the_prompt_version(): void
    {
        // Results cached for an older prompt (one box per row) are never served for the current one.
        $context = array_fill_keys(['event_type', 'event_date', 'event_time', 'end_time', 'venue', 'venue_setting', 'scale', 'special_requests', 'design_brief'], '');
        $context['event_type'] = 'wedding';

        $this->assertSame(
            md5(json_encode($context + ['prompt_version' => GeminiVisionService::PROMPT_VERSION])),
            GeminiVisionService::analysisContextHash(['event_type' => 'wedding'])
        );
    }

    // ------------------------------------------------------------------ Turned phone photos

    public function test_exif_orientation_is_read_and_the_displayed_size_is_turned(): void
    {
        $plain = $this->jpegFile(1);
        $this->assertSame(1, ImageOrientation::read($plain));
        $this->assertSame([4, 2], ImageOrientation::displaySize($plain));

        foreach ([false, true] as $bigEndian) {
            $turned = $this->jpegFile(6, $bigEndian);
            $this->assertSame(6, ImageOrientation::read($turned));
            $this->assertSame([2, 4], ImageOrientation::displaySize($turned));

            $untagged = ImageOrientation::withoutOrientationTag(file_get_contents($turned));
            $this->assertSame(strlen(file_get_contents($turned)), strlen($untagged));
            $this->assertSame(1, ImageOrientation::read($this->tempFile($untagged)));
        }

        $this->assertNull(ImageOrientation::withoutOrientationTag(base64_decode(self::JPEG_4X2)));
        $this->assertNull(ImageOrientation::withoutOrientationTag('not a jpeg'));
    }

    public function test_boxes_are_turned_the_way_the_browser_turns_the_photo(): void
    {
        // A box near the top-left of the stored pixels.
        $box = [100, 200, 300, 600];

        $this->assertSame([100.0, 200.0, 300.0, 600.0], ImageOrientation::mapBoxToDisplay($box, 1));
        $this->assertSame([100.0, 400.0, 300.0, 800.0], ImageOrientation::mapBoxToDisplay($box, 2)); // mirrored
        $this->assertSame([700.0, 400.0, 900.0, 800.0], ImageOrientation::mapBoxToDisplay($box, 3)); // half turn
        $this->assertSame([700.0, 200.0, 900.0, 600.0], ImageOrientation::mapBoxToDisplay($box, 4)); // flipped upside down
        $this->assertSame([200.0, 100.0, 600.0, 300.0], ImageOrientation::mapBoxToDisplay($box, 5)); // mirrored along the diagonal
        $this->assertSame([200.0, 700.0, 600.0, 900.0], ImageOrientation::mapBoxToDisplay($box, 6)); // quarter turn clockwise
        $this->assertSame([400.0, 700.0, 800.0, 900.0], ImageOrientation::mapBoxToDisplay($box, 7)); // mirrored along the other diagonal
        $this->assertSame([400.0, 100.0, 800.0, 300.0], ImageOrientation::mapBoxToDisplay($box, 8)); // quarter turn anticlockwise
    }

    public function test_a_turned_phone_photo_gets_boxes_in_the_displayed_frame(): void
    {
        Http::fake(['*' => Http::response($this->geminiText(['suggested_materials' => [
            $this->row('White Roses', ['boxes' => [['box_2d' => [100, 200, 300, 600], 'confidence' => 0.9]]]),
        ]]))]);

        $result = (new GeminiVisionService())->analyzeImageFromPath($this->jpegFile(6), null, 'wedding');
        $boxes = $result['analysis']['suggested_materials'][0]['boxes'];
        $this->assertSame(6, $result['image_metadata']['exif_orientation']);
        $this->assertArrayNotHasKey('box_2d', $result['analysis']['suggested_materials'][0]);

        $sent = null;
        Http::assertSent(function (HttpRequest $request) use (&$sent) {
            $sent = base64_decode(json_decode($request->body(), true)['contents'][0]['parts'][1]['inline_data']['data'] ?? '');

            return true;
        });

        if (function_exists('imagecreatefromjpeg')) {
            // GD turns the pixels upright before upload, so Gemini's box is already in the shown frame.
            $this->assertSame(1, $result['image_metadata']['box_orientation']);
            $this->assertSame([100, 200, 300, 600], $boxes[0]['box_2d']);
        } else {
            // Without GD the stored pixels are sent with the turn tag cleared, and the box is turned after.
            $this->assertSame(6, $result['image_metadata']['box_orientation']);
            $this->assertSame(1, ImageOrientation::read($this->tempFile($sent)));
            $this->assertSame([200.0, 700.0, 600.0, 900.0], $boxes[0]['box_2d']);
            $this->assertSame(0.9, $boxes[0]['confidence']);
        }
    }

    // ------------------------------------------------------------------ Drawing

    public function test_boxes_are_colored_by_area_numbered_and_linked_to_their_line(): void
    {
        $service = new GeminiVisionService();
        $analysis = $service->normalizeAiAnalysis(['suggested_materials' => [
            $this->row('White Roses', ['decor_area' => 'centerpieces', 'boxes' => [['box_2d' => [500, 250, 750, 750]], ['box_2d' => [100, 100, 200, 200]]], 'image_id' => 1]),
            $this->row('Pampas Grass', ['decor_area' => 'entrance', 'area' => 'entrance', 'boxes' => [['box_2d' => [0, 0, 500, 500]]], 'image_id' => 2]),
            $this->row('Floral Foam', ['is_detected' => false, 'is_recommendation' => true, 'image_id' => 1]),
        ]], ['decor_areas' => ['entrance', 'centerpieces']]);

        $html = $this->renderStage($service, $analysis['suggested_materials'], 800, 400, ['maxHeight' => 320]);

        $this->assertSame(3, substr_count($html, 'data-annotation-box '));
        $this->assertSame(2, substr_count($html, 'data-line-id="m1" data-image-id="1"'));
        $this->assertStringContainsString('border-color: #059669;', $html); // table centerpieces
        $this->assertStringContainsString('border-color: #e11d48;', $html); // entrance
        $this->assertStringContainsString('title="1 · White Roses (Table centerpieces)"', $html);
        // Every photo numbers its own boxes; another photo's boxes start hidden.
        $this->assertStringContainsString('title="1 · Pampas Grass (Entrance or arch)"', $html);
        $this->assertMatchesRegularExpression('/ hidden"\s+style="[^"]+"\s+data-annotation-item data-annotation-box data-line-id="m2" data-image-id="2"/', $html);
        // Recommendations stay in the lists only.
        $this->assertStringNotContainsString('Floral Foam', $html);
        $this->assertStringContainsString('2 places', $html);
        $this->assertStringContainsString('width: min(100%, 640px)', $html);
        $this->assertStringContainsString('window.highlightAnnotationLine', $html);
        $this->assertStringContainsString('window.showAnnotationsForImage', $html);
        $this->assertStringContainsString('window.fitAnnotatedImageStage', $html);
    }

    public function test_guest_photo_switcher_shows_only_that_photos_boxes(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'inspiration_image' => 'images/inspiration.jpg',
            'status' => 'pending',
            'guest_access_token' => 'guest-token-annotations',
            'guest_email' => 'annotations@example.com',
            'guest_phone' => '09170000000',
        ]);

        $html = view('guest.booking-analysis', [
            'booking' => $booking,
            'analysisMaterials' => [
                $this->row('White Roses', ['line_id' => 'p1-m1', 'boxes' => [['box_2d' => [100, 100, 400, 400]]], 'image_id' => 1]),
                $this->row('Pampas Grass', ['line_id' => 'p2-m1', 'boxes' => [['box_2d' => [100, 100, 400, 400]]], 'image_id' => 2]),
            ],
            'totalCost' => 2500,
            'token' => 'guest-token-annotations',
        ])->render();

        $this->assertStringContainsString('window.showAnnotationsForImage?.(stage, trackingImages[idx].id)', $html);
        $this->assertSame(1, substr_count($html, 'data-annotation-box data-line-id="p2-m1" data-image-id="2"'));
        // The material list entries carry the same line IDs, so a box and its line find each other.
        $this->assertStringContainsString('data-image-id="2" data-line-id="p2-m1"', $html);
    }

    public function test_guest_form_viewer_draws_real_ai_boxes_only(): void
    {
        $html = $this->get(route('guest.booking.create'))->assertOk()->getContent();

        $this->assertStringNotContainsString('sampleCoords', $html);
        $this->assertStringNotContainsString('visual_marker', $html);
        $this->assertStringContainsString('function annotationBoxes(mat)', $html);
        $this->assertStringContainsString('if (!mat || !mat.is_detected || mat.is_recommendation) return [];', $html);
        $this->assertStringContainsString('const annotationColors = {', $html);
        $this->assertStringContainsString('function fitViewerMarkers()', $html);
        $this->assertMatchesRegularExpression('/id="viewer-main-img"[^>]*object-contain/', $html);
        $this->assertStringContainsString('id="viewer-markers-hint-text"', $html);
    }

    // ------------------------------------------------------------------ Helpers

    private function renderStage(GeminiVisionService $service, array $materials, int $width, int $height, array $extra = []): string
    {
        return view('components.annotated-image', $extra + [
            'previewImage' => 'https://example.test/photo.jpg',
            'imageAspectRatio' => "{$width} / {$height}",
            'imageWidth' => $width,
            'imageHeight' => $height,
            'overlayItems' => collect($service->normalizeAreaAnalysis(['suggested_materials' => $materials]))->flatMap(fn ($group) => $group['items'])->values(),
        ])->render();
    }

    private function row(string $name, array $overrides = []): array
    {
        return array_merge([
            'item_name' => $name,
            'category' => 'flower',
            'quantity' => 10,
            'unit_type' => 'stem',
            'unit_cost_php' => 30,
            'area' => 'tables',
            'is_detected' => true,
            'is_recommendation' => false,
        ], $overrides);
    }

    private function geminiText(array $json): array
    {
        return ['candidates' => [['content' => ['parts' => [['text' => json_encode($json)]]]]]];
    }

    /** The 4x2 JPEG, with an EXIF Orientation tag unless $orientation is 1. */
    private function jpegFile(int $orientation, bool $bigEndian = false): string
    {
        $jpeg = base64_decode(self::JPEG_4X2);

        if ($orientation !== 1) {
            $tiff = $bigEndian
                ? "MM\x00\x2A" . pack('N', 8) . pack('n', 1) . pack('nnN', 0x0112, 3, 1) . pack('n', $orientation) . "\x00\x00" . pack('N', 0)
                : "II\x2A\x00" . pack('V', 8) . pack('v', 1) . pack('vvV', 0x0112, 3, 1) . pack('v', $orientation) . "\x00\x00" . pack('V', 0);
            $app1 = "Exif\x00\x00" . $tiff;
            $jpeg = substr($jpeg, 0, 2) . "\xFF\xE1" . pack('n', strlen($app1) + 2) . $app1 . substr($jpeg, 2);
        }

        return $this->tempFile($jpeg);
    }

    private function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'annot');
        file_put_contents($path, $contents);
        $this->tempFiles[] = $path;

        return $path;
    }
}
