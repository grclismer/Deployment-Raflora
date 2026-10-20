<?php

namespace Tests\Feature;

use App\Services\GeminiVisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use ReflectionClass;
use Tests\TestCase;

class GeminiConfigurationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    private ?string $originalApiKey = null;
    private ?string $originalApiUrl = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalApiKey = config('services.gemini.api_key');
        $this->originalApiUrl = config('services.gemini.api_url');
    }

    protected function tearDown(): void
    {
        if ($this->originalApiKey !== null) {
            Config::set('services.gemini.api_key', $this->originalApiKey);
            putenv("GEMINI_API_KEY={$this->originalApiKey}");
            $_ENV['GEMINI_API_KEY'] = $this->originalApiKey;
            $_SERVER['GEMINI_API_KEY'] = $this->originalApiKey;
        } else {
            Config::set('services.gemini.api_key', null);
            putenv('GEMINI_API_KEY');
            unset($_ENV['GEMINI_API_KEY'], $_SERVER['GEMINI_API_KEY']);
        }

        if ($this->originalApiUrl !== null) {
            Config::set('services.gemini.api_url', $this->originalApiUrl);
            putenv("GEMINI_API_URL={$this->originalApiUrl}");
            $_ENV['GEMINI_API_URL'] = $this->originalApiUrl;
            $_SERVER['GEMINI_API_URL'] = $this->originalApiUrl;
        } else {
            Config::set('services.gemini.api_url', null);
            putenv('GEMINI_API_URL');
            unset($_ENV['GEMINI_API_URL'], $_SERVER['GEMINI_API_URL']);
        }

        parent::tearDown();
    }

    protected function unsetGeminiApiKey(): void
    {
        Config::set('services.gemini.api_key', null);
        putenv('GEMINI_API_KEY');
        unset($_ENV['GEMINI_API_KEY'], $_SERVER['GEMINI_API_KEY']);
    }

    public function test_gemini_service_loads_api_key_from_laravel_configuration(): void
    {
        Config::set('services.gemini.api_key', 'test-cfg-key-12345');

        $service = new GeminiVisionService();

        $ref = new ReflectionClass($service);
        $keyProp = $ref->getProperty('apiKey');
        $keyProp->setAccessible(true);

        $this->assertSame('test-cfg-key-12345', $keyProp->getValue($service));
    }

    public function test_gemini_service_loads_api_url_from_laravel_configuration(): void
    {
        Config::set('services.gemini.api_url', 'https://proxy.example.com/v1beta/models');

        $service = new GeminiVisionService();

        $ref = new ReflectionClass($service);
        $urlProp = $ref->getProperty('apiUrl');
        $urlProp->setAccessible(true);

        $this->assertSame('https://proxy.example.com/v1beta/models', $urlProp->getValue($service));
    }

    public function test_gemini_service_uses_default_api_url_when_none_provided(): void
    {
        Config::set('services.gemini.api_url', null);
        putenv('GEMINI_API_URL');
        unset($_ENV['GEMINI_API_URL'], $_SERVER['GEMINI_API_URL']);

        $service = new GeminiVisionService();

        $ref = new ReflectionClass($service);
        $urlProp = $ref->getProperty('apiUrl');
        $urlProp->setAccessible(true);

        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models', $urlProp->getValue($service));
    }

    public function test_gemini_service_throws_clean_exception_when_api_key_is_missing(): void
    {
        $this->unsetGeminiApiKey();

        $tempImage = $this->writeTemporaryImage();

        try {
            $service = new GeminiVisionService();

            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('GEMINI_API_KEY is not configured in environment.');

            $service->analyzeImageFromPath($tempImage, 'special requests', 'wedding');
        } finally {
            @unlink($tempImage);
        }
    }

    public function test_gemini_service_validate_image_handles_missing_api_key_gracefully(): void
    {
        $this->unsetGeminiApiKey();

        $tempImage = $this->writeTemporaryImage();

        try {
            $service = new GeminiVisionService();
            $result = $service->validateImage($tempImage);

            $this->assertFalse($result['is_valid']);
            $this->assertSame('analysis', $result['error_type']);
            $this->assertSame('Image analysis could not be completed. Please try again with a clearer image or a different photo.', $result['rejection_reason']);
        } finally {
            @unlink($tempImage);
        }
    }

    public function test_config_cache_simulation_succeeds_when_env_is_inaccessible(): void
    {
        // In Laravel production with config:cache, env() returns null for all variables,
        // but config() contains the compiled configuration from services.php.
        $this->unsetGeminiApiKey();
        Config::set('services.gemini.api_key', 'cached-production-api-key');
        Config::set('services.gemini.api_url', 'https://generativelanguage.googleapis.com/v1beta/models');

        $service = new GeminiVisionService();

        $ref = new ReflectionClass($service);
        $keyProp = $ref->getProperty('apiKey');
        $keyProp->setAccessible(true);
        $urlProp = $ref->getProperty('apiUrl');
        $urlProp->setAccessible(true);

        $this->assertSame('cached-production-api-key', $keyProp->getValue($service));
        $this->assertSame('https://generativelanguage.googleapis.com/v1beta/models', $urlProp->getValue($service));
    }

    public function test_endpoint_url_generation_preserves_url_normalization_with_config(): void
    {
        Config::set('services.gemini.api_key', 'test-key');
        Config::set('services.gemini.api_url', 'https://custom-endpoint.googleapis.com/v1beta/models/');

        Http::fake([
            'https://custom-endpoint.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode([
                                    'is_floral_or_event_related' => true,
                                    'is_clear_usable' => true,
                                    'rejection_reason' => null,
                                ])]
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $tempImage = $this->writeTemporaryImage();

        $service = new GeminiVisionService();
        $result = $service->validateImage($tempImage);
        @unlink($tempImage);

        $this->assertTrue($result['is_valid']);

        Http::assertSent(function ($request) {
            return str_starts_with($request->url(), 'https://custom-endpoint.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=test-key');
        });
    }

    public function test_missing_api_key_in_ajax_analysis_handles_failure_gracefully_without_exposing_secrets(): void
    {
        $this->unsetGeminiApiKey();

        $file = $this->validImageUpload();

        $response = $this->post(route('bookings.analyze-temp-image'), [
            'inspiration_image' => $file,
            'event_type' => 'wedding',
            'venue' => 'Garden Hall',
        ], ['Accept' => 'application/json']);

        $response->assertStatus(422);
        $json = $response->json();

        $this->assertFalse($json['success']);
        // Must show safe user-friendly message, not exception stack trace or internal variable names
        $this->assertSame('Image analysis could not be completed. Please try again with a clearer image or a different photo.', $json['message']);
        $this->assertStringNotContainsString('GEMINI_API_KEY', json_encode($json));
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
