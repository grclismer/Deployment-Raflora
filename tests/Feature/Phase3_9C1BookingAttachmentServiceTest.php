<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\Booking;
use App\Models\Client;
use App\Models\User;
use App\Models\BookingMessage;
use App\Services\BookingAttachmentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class Phase3_9C1BookingAttachmentServiceTest extends TestCase
{
    use RefreshDatabase;

    protected BookingAttachmentService $service;
    protected Booking $booking;
    protected User $clientUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->service = new BookingAttachmentService();
        Storage::fake('local');
        
        $this->clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '1234567890',
        ]);
        
        $this->booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30),
            'venue' => 'Test Venue',
            'status' => 'pending',
            'total_quoted' => 1000,
            'raw_materials_sum' => 1000,
            'final_quoted_price' => 1000,
        ]);
    }

    public function test_text_only_message_persistence()
    {
        $messageData = [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientUser->id,
            'message' => 'Hello this is a text message.',
        ];

        $message = $this->service->storeMessage($this->booking, $messageData, null, null);

        $this->assertInstanceOf(BookingMessage::class, $message);
        $this->assertEquals('Hello this is a text message.', $message->message);
        $this->assertNull($message->attachment_path);
        
        $this->assertDatabaseHas('booking_messages', [
            'id' => $message->id,
            'message' => 'Hello this is a text message.',
        ]);
    }

    public function test_valid_categorized_attachment_persistence()
    {
        $file = UploadedFile::fake()->create('test_image.jpg', 100, 'image/jpeg');
        
        $messageData = [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientUser->id,
            'message' => 'Here is my inspiration image.',
        ];

        $message = $this->service->storeMessage($this->booking, $messageData, $file, 'inspiration_reference');

        $this->assertNotNull($message->attachment_path);
        $this->assertEquals('test_image.jpg', $message->attachment_name);
        $this->assertEquals('inspiration_reference', $message->attachment_category);
        $this->assertEquals($file->getMimeType(), $message->mime_type);
        $this->assertEquals($file->getSize(), $message->file_size);
        
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_missing_category_rejection_when_file_attached()
    {
        $file = UploadedFile::fake()->create('test_image.jpg', 100, 'image/jpeg');
        
        $messageData = [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientUser->id,
            'message' => 'No category provided.',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->service->storeMessage($this->booking, $messageData, $file, null);
    }

    public function test_invalid_category_rejection()
    {
        $file = UploadedFile::fake()->create('test_image.jpg', 100, 'image/jpeg');
        
        $messageData = [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientUser->id,
            'message' => 'Invalid category.',
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->service->storeMessage($this->booking, $messageData, $file, 'invalid_category');
    }

    public function test_database_failure_cleans_up_file()
    {
        $file = UploadedFile::fake()->create('test_image.jpg', 100, 'image/jpeg');
        
        $messageData = [
            // Intentionally omit required fields to cause a database failure
            'booking_id' => $this->booking->id,
        ];

        try {
            $this->service->storeMessage($this->booking, $messageData, $file, 'event_venue');
            $this->fail('Expected an exception due to missing fields.');
        } catch (\Throwable $e) {
            // Find all files in the directory
            $files = Storage::disk('local')->files("messages/attachments/{$this->booking->id}");
            $this->assertEmpty($files, 'The uploaded file should have been cleaned up after a DB failure.');
        }
    }

    public function test_validation_rules_structure()
    {
        $rules = BookingAttachmentService::getValidationRules();
        
        $this->assertArrayHasKey('attachment', $rules);
        $this->assertArrayHasKey('attachment_category', $rules);
        
        $this->assertContains('nullable', $rules['attachment']);
        $this->assertContains('mimes:jpeg,jpg,png,webp,pdf', $rules['attachment']);
        
        $rulesRequired = BookingAttachmentService::getValidationRules(true);
        $this->assertContains('required', $rulesRequired['attachment']);
    }
}
