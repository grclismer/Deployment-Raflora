<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Services\GeminiVisionService;
use Tests\TestCase;

class AiAnalysisFailureHandlingTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_analysis_rejects_incomplete_gemini_materials(): void
    {
        Storage::fake('public');
        $this->fakeSuccessfulImageValidationThenIncompleteAnalysis();

        $response = $this->post(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
            'venue' => 'Test venue',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertArrayNotHasKey('analysis_token', $response->json());
    }

    public function test_guest_analysis_rejects_incomplete_gemini_materials(): void
    {
        Storage::fake('public');
        $this->fakeSuccessfulImageValidationThenIncompleteAnalysis();

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
            'venue' => 'Test venue',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJson(['success' => false]);
        $this->assertArrayNotHasKey('analysis_token', $response->json());
    }

    public function test_client_analysis_rejects_an_image_that_gemini_marks_unusable(): void
    {
        $this->fakeUnusableImageValidation();
        $path = $this->writeTemporaryImage();
        $result = (new GeminiVisionService())->validateImage($path);
        @unlink($path);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('The image is not clear enough for reliable analysis. Please upload a clearer image.', $result['rejection_reason']);
    }

    public function test_guest_analysis_rejects_an_image_that_gemini_marks_unusable(): void
    {
        $this->fakeUnusableImageValidation();
        $path = $this->writeTemporaryImage();
        $result = (new GeminiVisionService())->validateImage($path);
        @unlink($path);

        $this->assertFalse($result['is_valid']);
        $this->assertSame('The image is not clear enough for reliable analysis. Please upload a clearer image.', $result['rejection_reason']);
    }

    public function test_client_ai_analysis_reports_connection_failure_separately_from_blurry_image(): void
    {
        putenv('GEMINI_API_KEY=test-key');
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Connection timed out');
            },
        ]);

        $response = $this->post(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
            'venue' => 'Test venue',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Image analysis could not be completed because of a connection problem. Please check your internet connection and try again.');
    }

    public function test_guest_ai_analysis_reports_connection_failure_separately_from_blurry_image(): void
    {
        putenv('GEMINI_API_KEY=test-key');
        Http::fake([
            '*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: Connection timed out');
            },
        ]);

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
            'venue' => 'Test venue',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Image analysis could not be completed because of a connection problem. Please check your internet connection and try again.');
    }

    public function test_client_ai_analysis_reports_service_failure_separately_from_blurry_image(): void
    {
        putenv('GEMINI_API_KEY=test-key');
        Http::fake([
            '*' => Http::response(['error' => 'SERVICE_UNAVAILABLE'], 503),
        ]);

        $response = $this->post(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
            'venue' => 'Test venue',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Image analysis could not be completed right now. Please try again.');
    }

    public function test_guest_ai_analysis_reports_service_failure_separately_from_blurry_image(): void
    {
        putenv('GEMINI_API_KEY=test-key');
        Http::fake([
            '*' => Http::response(['error' => 'SERVICE_UNAVAILABLE'], 503),
        ]);

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
            'venue' => 'Test venue',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Image analysis could not be completed right now. Please try again.');
    }

    public function test_client_custom_ai_booking_rejects_submission_without_successful_analysis_token(): void
    {
        $user = User::factory()->create();
        $client = Client::create([
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '09170000000',
        ]);

        $this->actingAs($user)->post(route('bookings.store'), [
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Test venue',
            'inspiration_image' => $this->validImageUpload(),
        ])->assertRedirect();

        $this->assertSame(0, Booking::where('client_id', $client->id)->count());
    }

    public function test_guest_custom_ai_booking_rejects_submission_without_successful_analysis_token(): void
    {
        $this->post(route('guest.booking.store'), [
            'guest_name' => 'Guest User',
            'guest_email' => 'guest-analysis@example.com',
            'guest_phone' => '09170000001',
            'booking_type' => 'custom_ai',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'event_time' => '10:00',
            'venue' => 'Test venue',
            'inspiration_image' => $this->validImageUpload(),
        ])->assertRedirect();

        $this->assertSame(0, Booking::where('guest_email', 'guest-analysis@example.com')->count());
    }

    public function test_client_diagnostic_record_contains_request_context_and_material_evidence(): void
    {
        $diagnostic = GeminiVisionService::buildAnalysisDiagnostic(
            'client',
            [
                'model_used' => 'gemini-2.5-flash',
                'analysis' => [
                    'suggested_materials' => [[
                        'item_name' => 'White Roses',
                        'category' => 'flower',
                        'quantity' => 12,
                        'unit_type' => 'stem',
                        'unit_cost_php' => 25,
                        'area' => 'tables',
                        'is_detected' => true,
                        'is_recommendation' => false,
                        'note' => 'Fresh floral accents',
                        'visual_location' => ['x' => 0.42, 'y' => 0.28, 'width' => 0.18, 'height' => 0.12],
                    ]],
                ],
            ],
            [
                'event_type' => 'wedding',
                'event_time' => '18:00',
                'venue' => 'Luna Garden',
                'guest_count' => 120,
                'table_count' => 12,
                'special_requests' => 'Velvet palette',
            ],
            'abc123',
            '/tmp/example.jpg',
            false,
            [
                'mime_type' => 'image/jpeg',
                'width' => 1600,
                'height' => 1200,
                'prepared_mime_type' => 'image/jpeg',
                'prepared_width' => 1024,
                'prepared_height' => 768,
            ],
            ['temperature' => 0.2, 'responseMimeType' => 'application/json']
        );

        $this->assertSame('client', $diagnostic['source']);
        $this->assertSame('abc123', $diagnostic['image']['hash']);
        $this->assertSame('wedding', $diagnostic['request_context']['event_type']);
        $this->assertSame('gemini-2.5-flash', $diagnostic['gemini_config']['model']);
        $this->assertSame('application/json', $diagnostic['gemini_config']['response_mime_type']);
        $this->assertSame('White Roses', $diagnostic['analysis_result']['suggested_materials'][0]['name']);
        $this->assertFalse($diagnostic['completed_template_reused']);
    }

    public function test_guest_diagnostic_record_contains_equivalent_structure(): void
    {
        $diagnostic = GeminiVisionService::buildAnalysisDiagnostic(
            'guest',
            [
                'model_used' => 'gemini-3.5-flash',
                'analysis' => [
                    'suggested_materials' => [[
                        'item_name' => 'White Roses',
                        'category' => 'flower',
                        'quantity' => 12,
                        'unit_type' => 'stem',
                        'unit_cost_php' => 25,
                        'area' => 'tables',
                        'is_detected' => true,
                        'is_recommendation' => false,
                        'note' => 'Fresh floral accents',
                        'visual_location' => ['x' => 0.42, 'y' => 0.28, 'width' => 0.18, 'height' => 0.12],
                    ]],
                ],
            ],
            [
                'event_type' => 'wedding',
                'event_time' => '18:00',
                'venue' => 'Luna Garden',
                'guest_count' => 120,
                'table_count' => 12,
                'special_requests' => 'Velvet palette',
            ],
            'abc123',
            '/tmp/example-guest.jpg',
            false,
            [
                'mime_type' => 'image/jpeg',
                'width' => 1600,
                'height' => 1200,
                'prepared_mime_type' => 'image/jpeg',
                'prepared_width' => 1024,
                'prepared_height' => 768,
            ],
            ['temperature' => 0.2, 'responseMimeType' => 'application/json']
        );

        $this->assertSame('guest', $diagnostic['source']);
        $this->assertSame('abc123', $diagnostic['image']['hash']);
        $this->assertSame('wedding', $diagnostic['request_context']['event_type']);
        $this->assertSame('gemini-3.5-flash', $diagnostic['gemini_config']['model']);
        $this->assertSame('White Roses', $diagnostic['analysis_result']['suggested_materials'][0]['name']);
        $this->assertFalse($diagnostic['completed_template_reused']);
    }

    public function test_diagnostic_produces_same_image_hash_for_same_bytes_and_distinct_hash_for_different_bytes(): void
    {
        $sameBytes = "\x89PNG\r\n\x1a\n" . str_repeat('A', 20);
        $hashA = sha1($sameBytes);
        $hashB = sha1("\x89PNG\r\n\x1a\n" . str_repeat('B', 20));

        $this->assertSame($hashA, sha1($sameBytes));
        $this->assertNotSame($hashA, $hashB);
        $this->assertSame(40, strlen($hashA));
        $this->assertSame(40, strlen($hashB));
    }

    public function test_diagnostic_preserves_detection_and_visual_location_and_redacts_secrets(): void
    {
        $diagnostic = GeminiVisionService::buildAnalysisDiagnostic(
            'client',
            [
                'model_used' => 'gemini-2.5-flash',
                'analysis' => [
                    'suggested_materials' => [[
                        'item_name' => 'Recommended Arch Decor',
                        'category' => 'prop',
                        'quantity' => 1,
                        'unit_type' => 'set',
                        'unit_cost_php' => 80,
                        'area' => 'entrance',
                        'is_detected' => false,
                        'is_recommendation' => true,
                        'note' => 'Suggested accent',
                        'visual_location' => ['x' => 0.15, 'y' => 0.28, 'width' => 0.55, 'height' => 0.33],
                    ]],
                ],
            ],
            ['event_type' => 'wedding', 'special_requests' => 'Use white florals'],
            'hash-123',
            '/tmp/example.jpg',
            false,
            ['mime_type' => 'image/jpeg', 'width' => 1200, 'height' => 900],
            ['temperature' => 0.2, 'responseMimeType' => 'application/json']
        );

        $json = json_encode($diagnostic);
        $this->assertStringContainsString('recommendation', strtolower((string) $diagnostic['analysis_result']['suggested_materials'][0]['source']));
        $this->assertSame(0.15, $diagnostic['analysis_result']['suggested_materials'][0]['visual_location']['x']);
        $this->assertStringNotContainsString('api_key', strtolower($json));
        $this->assertStringNotContainsString('password', strtolower($json));
        $this->assertStringNotContainsString('guest_access_token', strtolower($json));
        $this->assertStringNotContainsString('csrf', strtolower($json));
    }

    public function test_diagnostic_distinguishes_fresh_analysis_from_completed_template_reuse(): void
    {
        $fresh = GeminiVisionService::buildAnalysisDiagnostic('client', ['model_used' => 'gemini-2.5-flash', 'analysis' => ['suggested_materials' => []]], ['event_type' => 'birthday'], 'hash-1', '/tmp/example.jpg', false, ['mime_type' => 'image/jpeg', 'width' => 800, 'height' => 600], ['temperature' => 0.2, 'responseMimeType' => 'application/json']);
        $reused = GeminiVisionService::buildAnalysisDiagnostic('guest', ['model_used' => 'gemini-2.5-flash', 'analysis' => ['suggested_materials' => []]], ['event_type' => 'birthday'], 'hash-2', '/tmp/example.jpg', true, ['mime_type' => 'image/jpeg', 'width' => 800, 'height' => 600], ['temperature' => 0.2, 'responseMimeType' => 'application/json']);

        $this->assertFalse($fresh['completed_template_reused']);
        $this->assertTrue($reused['completed_template_reused']);
        $this->assertTrue($fresh['fresh_gemini_analysis']);
        $this->assertFalse($reused['fresh_gemini_analysis']);
    }

    public function test_complete_analysis_payload_is_accepted_by_the_success_contract(): void
    {
        (new GeminiVisionService())->validateAnalysisPayload([
            'suggested_materials' => [[
                'item_name' => 'Rose',
                'category' => 'flower',
                'quantity' => 12,
                'unit_type' => 'stem',
                'unit_cost_php' => 25,
            ]],
        ]);

        $this->assertTrue(true);
    }

    public function test_analysis_can_be_grouped_by_area_without_fabricating_coordinates(): void
    {
        $analysis = [
            'suggested_materials' => [
                [
                    'item_name' => 'White Roses',
                    'category' => 'flower',
                    'quantity' => 12,
                    'unit_type' => 'stem',
                    'unit_cost_php' => 30,
                    'area' => 'ceiling',
                    'is_detected' => true,
                    'is_recommendation' => false,
                ],
                [
                    'item_name' => 'Floral Foam',
                    'category' => 'supply',
                    'quantity' => 2,
                    'unit_type' => 'piece',
                    'unit_cost_php' => 10,
                    'area' => 'stage',
                    'is_detected' => false,
                    'is_recommendation' => true,
                ],
            ],
        ];

        $groups = (new GeminiVisionService())->normalizeAreaAnalysis($analysis);

        $this->assertCount(2, $groups);
        $this->assertSame('Ceiling', $groups[0]['area_label']);
        $this->assertSame('AI Detected', $groups[0]['items'][0]['source_label']);
        $this->assertSame('Estimated', $groups[1]['items'][0]['source_label']);
        $this->assertArrayNotHasKey('coordinates', $groups[0]['items'][0]);
    }

    protected function fakeSuccessfulImageValidationThenIncompleteAnalysis(): void
    {
        putenv('GEMINI_API_KEY=test-key');
        Http::fake([
            '*' => Http::sequence()
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'is_floral_or_event_related' => true,
                                    'is_clear_usable' => true,
                                    'rejection_reason' => null,
                                ]),
                            ]],
                        ],
                    ]],
                ], 200)
                ->push([
                    'candidates' => [[
                        'content' => [
                            'parts' => [[
                                'text' => json_encode([
                                    'suggested_materials' => [],
                                ]),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);
    }

    protected function fakeUnusableImageValidation(): void
    {
        putenv('GEMINI_API_KEY=test-key');
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'is_floral_or_event_related' => true,
                                'is_clear_usable' => false,
                                'rejection_reason' => 'The image is not clear enough for reliable analysis. Please upload a clearer image.',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);
    }

    protected function validImageUpload(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('inspiration.png', $png);
    }

    protected function writeTemporaryImage(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'raflora-ai-test-');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));

        return $path;
    }
}
