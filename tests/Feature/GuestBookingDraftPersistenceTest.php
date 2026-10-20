<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class GuestBookingDraftPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_booking_create_view_contains_draft_persistence_and_restore_infrastructure(): void
    {
        $response = $this->get(route('guest.booking.create'));

        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Storage key
        $this->assertStringContainsString('raflora_guest_booking_draft_v1', $html);

        // 2. Draft lifecycle functions
        $this->assertStringContainsString('function saveGuestDraft()', $html);
        $this->assertStringContainsString('function restoreGuestDraft()', $html);
        $this->assertStringContainsString('function clearGuestDraft()', $html);
        $this->assertStringContainsString('function generateThumbnail(', $html);

        // 3. Clear draft on form submission
        $this->assertStringContainsString('clearGuestDraft()', $html);
    }

    public function test_show_analysis_temp_image_streams_temporary_inspiration_image_for_active_session(): void
    {
        Storage::fake('local');

        $token = (string) Str::uuid();
        $fakePngContent = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $tempPath = 'bookings/temp-analysis/' . $token . '.png';
        Storage::disk('local')->put($tempPath, $fakePngContent);

        // Attach analysis payload into session
        $payload = [
            'temp_path' => $tempPath,
            'original_filename' => 'inspiration.png',
            'analysis' => ['summary' => 'Test'],
        ];

        $response = $this->withSession([
            "booking_analysis_payloads.{$token}" => $payload,
        ])->get(route('guest.bookings.analysis-temp-image', ['token' => $token]));

        $response->assertStatus(200);
        $this->assertEquals('image/png', $response->headers->get('Content-Type'));
        $this->assertEquals($fakePngContent, $response->streamedContent());
    }

    public function test_show_analysis_temp_image_rejects_missing_or_foreign_session_tokens(): void
    {
        Storage::fake('local');

        $token = (string) Str::uuid();

        // No session payload
        $response = $this->get(route('guest.bookings.analysis-temp-image', ['token' => $token]));
        $response->assertStatus(404);

        // Foreign session / missing temp file
        $otherToken = (string) Str::uuid();
        $response2 = $this->withSession([
            "booking_analysis_payloads.{$otherToken}" => [
                'temp_path' => 'nonexistent/path.png',
            ],
        ])->get(route('guest.bookings.analysis-temp-image', ['token' => $otherToken]));
        $response2->assertStatus(404);
    }

    public function test_step1_grid_layout_and_progress_header_alignment(): void
    {
        $response = $this->get(route('guest.booking.create'));
        $response->assertStatus(200);
        $html = $response->getContent();

        // 1. Step 1 dynamic scale fields balanced 2-column spans
        $this->assertStringContainsString('id="scale-fields-container"', $html);
        $this->assertStringContainsString('id="scale-tables-container"', $html);
        $this->assertStringContainsString('lg:col-span-2', $html);

        // 2. Venue City and Specific Venue grouped on dedicated row
        $this->assertStringContainsString('name="venue_city"', $html);
        $this->assertStringContainsString('name="venue_specific"', $html);
        $this->assertStringContainsString('lg:col-span-1', $html);
        $this->assertStringContainsString('lg:col-span-3', $html);

        // 3. Progress nav items have whitespace-nowrap and shrink-0
        $this->assertStringContainsString('whitespace-nowrap', $html);
        $this->assertStringContainsString('shrink-0', $html);

        // 4. Draft restoration bypasses validation
        $this->assertStringContainsString('showGuestStep(targetStep, true)', $html);
    }
}
