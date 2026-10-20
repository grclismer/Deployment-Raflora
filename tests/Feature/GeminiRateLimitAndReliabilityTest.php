<?php

namespace Tests\Feature;

use App\Services\GeminiVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GeminiRateLimitAndReliabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.gemini.api_key', 'test-gemini-key');
        putenv('GEMINI_API_KEY=test-gemini-key');
        Cache::flush();
    }

    public function test_preferred_model_order_starts_with_current_flash_lite(): void
    {
        $service = new GeminiVisionService();
        $models = $service->getFallbackModels();

        $this->assertNotEmpty($models);
        $this->assertSame('gemini-3.5-flash-lite', $models[0]);
        $this->assertSame('gemini-3.5-flash', $models[1]);
    }

    public function test_successful_analysis_uses_primary_model_without_excessive_calls(): void
    {
        Storage::fake('local');

        Http::fake([
            '*/gemini-3.5-flash-lite:generateContent*' => Http::sequence()
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
                                    'suggested_materials' => [
                                        [
                                            'item_name' => 'White Roses',
                                            'category' => 'flower',
                                            'quantity' => 20,
                                            'unit_type' => 'stem',
                                            'unit_cost_php' => 35,
                                            'area' => 'tables',
                                            'is_detected' => true,
                                            'is_recommendation' => false,
                                        ],
                                    ],
                                ]),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        // Validation + Analysis = exactly 2 calls on primary model
        Http::assertSentCount(2);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-3.5-flash-lite');
        });
    }

    public function test_transient_429_retries_and_falls_back_to_secondary_model(): void
    {
        Storage::fake('local');

        Http::fake([
            // Primary model returns 429
            '*/gemini-3.5-flash-lite:generateContent*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'RESOURCE_EXHAUSTED: Rate limit exceeded',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429, ['Retry-After' => '1']),

            // Fallback model succeeds
            '*/gemini-3.5-flash:generateContent*' => Http::sequence()
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
                                    'suggested_materials' => [
                                        [
                                            'item_name' => 'Pink Carnations',
                                            'category' => 'flower',
                                            'quantity' => 15,
                                            'unit_type' => 'stem',
                                            'unit_cost_php' => 25,
                                            'area' => 'tables',
                                            'is_detected' => true,
                                            'is_recommendation' => false,
                                        ],
                                    ],
                                ]),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'gemini-3.5-flash');
        });
    }

    public function test_repeated_429_fails_fast_after_consecutive_quota_failures(): void
    {
        Storage::fake('local');

        Http::fake([
            '*' => Http::response([
                'error' => [
                    'code' => 429,
                    'message' => 'RESOURCE_EXHAUSTED: Quota limit reached',
                    'status' => 'RESOURCE_EXHAUSTED',
                ],
            ], 429),
        ]);

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'is_quota_error' => true,
                'error_type' => 'service',
            ]);

        // Fast-fail: after 2 consecutive quota failures, it stops early and does not call model 3
        Http::assertSentCount(2);
    }

    public function test_transient_503_service_unavailable_handled_with_fallback(): void
    {
        Storage::fake('local');

        Http::fake([
            '*/gemini-3.5-flash-lite:generateContent*' => Http::response(['error' => 'UNAVAILABLE'], 503),
            '*/gemini-3.5-flash:generateContent*' => Http::sequence()
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
                                    'suggested_materials' => [
                                        [
                                            'item_name' => 'Hydrangea',
                                            'category' => 'flower',
                                            'quantity' => 5,
                                            'unit_type' => 'stem',
                                            'unit_cost_php' => 150,
                                            'area' => 'tables',
                                            'is_detected' => true,
                                            'is_recommendation' => false,
                                        ],
                                    ],
                                ]),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        $response = $this->post(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);
    }

    public function test_controller_distinguishes_image_rejection_from_quota_failure(): void
    {
        Storage::fake('local');

        // Validation rejects image because it's blurry/non-floral
        Http::fake([
            '*' => Http::response([
                'candidates' => [[
                    'content' => [
                        'parts' => [[
                            'text' => json_encode([
                                'is_floral_or_event_related' => false,
                                'is_clear_usable' => false,
                                'rejection_reason' => 'The image does not contain event or floral decorations.',
                            ]),
                        ]],
                    ],
                ]],
            ], 200),
        ]);

        $response = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'is_quota_error' => false,
                'error_type' => 'image_quality',
                'message' => 'The image does not contain event or floral decorations.',
            ]);
    }

    public function test_duplicate_image_upload_uses_cache_and_avoids_repeated_api_calls(): void
    {
        Storage::fake('local');

        Http::fake([
            '*/gemini-3.5-flash-lite:generateContent*' => Http::sequence()
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
                                    'suggested_materials' => [
                                        [
                                            'item_name' => 'Sunflowers',
                                            'category' => 'flower',
                                            'quantity' => 10,
                                            'unit_type' => 'stem',
                                            'unit_cost_php' => 80,
                                            'area' => 'tables',
                                            'is_detected' => true,
                                            'is_recommendation' => false,
                                        ],
                                    ],
                                ]),
                            ]],
                        ],
                    ]],
                ], 200),
        ]);

        // First upload: triggers 2 Gemini calls
        $res1 = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);
        $res1->assertStatus(200);
        $this->assertEquals(2, Http::recorded()->count());

        // Second upload with identical image content: served from cache, 0 new calls
        $res2 = $this->post(route('guest.bookings.analyze-temp-image'), [
            'inspiration_image' => $this->validImageUpload(),
            'event_type' => 'wedding',
        ], ['Accept' => 'application/json']);
        $res2->assertStatus(200);
        $this->assertEquals(2, Http::recorded()->count(), 'Second request must be served from cache without generating new Gemini requests.');
    }

    public function test_retryable_rate_limit_detection_helper(): void
    {
        $service = new GeminiVisionService();

        $this->assertTrue($service->isRetryableRateLimit(429, ''));
        $this->assertTrue($service->isRetryableRateLimit(503, ''));
        $this->assertTrue($service->isRetryableRateLimit(200, 'Error: RESOURCE_EXHAUSTED'));
        $this->assertTrue($service->isRetryableRateLimit(200, 'The quota has been exceeded'));
        $this->assertTrue($service->isRetryableRateLimit(200, 'Service UNAVAILABLE'));

        $this->assertFalse($service->isRetryableRateLimit(400, 'Bad Request'));
        $this->assertFalse($service->isRetryableRateLimit(404, 'Not Found'));
        $this->assertFalse($service->isRetryableRateLimit(200, '{"is_floral_or_event_related": true}'));
    }

    protected function validImageUpload(): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=');

        return UploadedFile::fake()->createWithContent('inspiration.png', $png);
    }
}
