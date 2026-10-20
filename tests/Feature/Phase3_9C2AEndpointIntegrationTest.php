<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Booking;
use App\Models\BookingMessage;

class Phase3_9C2AEndpointIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected User $adminUser;
    protected Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();
        
        Storage::fake('local');
        
        $this->clientUser = User::factory()->create(['role' => 'client']);
        $client = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '1234567890',
        ]);
        
        $this->adminUser = User::factory()->create(['role' => 'admin']);
        
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

    public function test_client_text_only_message_succeeds()
    {
        $response = $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Hello from client text only',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'visibility' => 'shared',
            'message' => 'Hello from client text only',
        ]);
    }

    public function test_admin_text_only_message_succeeds()
    {
        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Hello from admin text only',
            'action' => 'reply_only',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'visibility' => 'shared',
            'message' => 'Hello from admin text only',
        ]);
    }

    public function test_client_can_submit_valid_categorized_attachment()
    {
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Here is my payment proof',
            'visibility' => 'shared',
            'attachment' => $file,
            'attachment_category' => 'payment_proof',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $message = BookingMessage::where('message', 'Here is my payment proof')->first();
        $this->assertNotNull($message);
        $this->assertNotNull($message->attachment_path);
        $this->assertEquals('payment_proof', $message->attachment_category);
        $this->assertEquals('proof.pdf', $message->attachment_name);

        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_admin_can_submit_valid_categorized_attachment()
    {
        $file = UploadedFile::fake()->create('proposal.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->adminUser)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Here is the new proposal',
            'action' => 'reply_only',
            'visibility' => 'shared',
            'attachment' => $file,
            'attachment_category' => 'proposal_quotation',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $message = BookingMessage::where('message', 'Here is the new proposal')->first();
        $this->assertNotNull($message);
        $this->assertNotNull($message->attachment_path);
        $this->assertEquals('proposal_quotation', $message->attachment_category);
        
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_missing_category_is_rejected_when_file_attached()
    {
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Here is my file',
            'visibility' => 'shared',
            'attachment' => $file,
        ]);

        $response->assertSessionHasErrors('attachment_category');
    }

    public function test_unsupported_file_and_oversized_file_are_rejected()
    {
        $file = UploadedFile::fake()->create('script.php', 100, 'text/x-php');

        $response = $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Bad file',
            'visibility' => 'shared',
            'attachment' => $file,
            'attachment_category' => 'other_booking_document',
        ]);

        $response->assertSessionHasErrors('attachment');

        $bigFile = UploadedFile::fake()->create('big.jpg', 6000, 'image/jpeg');
        
        $response2 = $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Big file',
            'visibility' => 'shared',
            'attachment' => $bigFile,
            'attachment_category' => 'other_booking_document',
        ]);

        $response2->assertSessionHasErrors('attachment');
    }

    public function test_secure_retrieval_does_not_expose_filesystem_paths()
    {
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'File for retrieval',
            'visibility' => 'shared',
            'attachment' => $file,
            'attachment_category' => 'payment_proof',
        ]);

        $message = BookingMessage::where('message', 'File for retrieval')->first();

        $response = $this->actingAs($this->clientUser)->get(route('secure.attachment.show', $message->id));
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/pdf');
        $response->assertHeader('Content-Disposition', 'inline; filename="proof.pdf"');
    }

    public function test_unauthorized_client_cannot_access_another_bookings_attachments()
    {
        $file = UploadedFile::fake()->create('proof.pdf', 100, 'application/pdf');

        $this->actingAs($this->clientUser)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Secret file',
            'visibility' => 'shared',
            'attachment' => $file,
            'attachment_category' => 'payment_proof',
        ]);

        $message = BookingMessage::where('message', 'Secret file')->first();

        $otherClientUser = User::factory()->create(['role' => 'client']);
        Client::create([
            'email' => $otherClientUser->email,
            'full_name' => $otherClientUser->name,
            'phone' => '0987654321',
        ]);

        $response = $this->actingAs($otherClientUser)->get(route('secure.attachment.show', $message->id));
        
        $response->assertStatus(403);
    }
}
