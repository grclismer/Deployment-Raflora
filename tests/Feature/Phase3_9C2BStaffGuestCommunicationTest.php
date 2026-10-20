<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3_9C2BStaffGuestCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_assigned_staff_can_submit_message_and_attachment()
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $client = \App\Models\Client::create(['full_name' => 'Test', 'email' => $clientUser->email]);
        $staff = User::factory()->create(['role' => 'staff']);
        
        $booking = Booking::create([
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'status' => 'confirmed',
            'booking_type' => 'custom',
            'event_type' => 'other',
            'guest_email' => $client->email,
        ]);

        $file = UploadedFile::fake()->create('venue.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($staff)->post(route('staff.events.reply', $booking->id), [
            'visibility' => 'shared',
            'message' => 'Staff venue check completed.',
            'visibility' => 'shared',
            'attachment' => $file,
            'attachment_category' => 'event_venue',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $booking->id,
            'sender_type' => 'staff',
            'sender_id' => $staff->id,
            'visibility' => 'shared',
            'message' => 'Staff venue check completed.',
            'attachment_category' => 'event_venue',
        ]);

        $message = \App\Models\BookingMessage::where('booking_id', $booking->id)->where('sender_type', 'staff')->first();
        $this->assertNotNull($message->attachment_path);
        Storage::disk('local')->assertExists($message->attachment_path);
    }

    public function test_unassigned_staff_cannot_submit_message()
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $client = \App\Models\Client::create(['full_name' => 'Test', 'email' => $clientUser->email]);
        $staff1 = User::factory()->create(['role' => 'staff']);
        $staff2 = User::factory()->create(['role' => 'staff']);
        
        $booking = Booking::create([
            'client_id' => $client->id,
            'staff_id' => $staff1->id,
            'status' => 'confirmed',
            'booking_type' => 'custom',
            'event_type' => 'other',
            'guest_email' => $client->email,
        ]);

        $response = $this->actingAs($staff2)->post(route('staff.events.reply', $booking->id), [
            'visibility' => 'shared',
            'message' => 'Unassigned staff message.',
        ]);

        $response->assertStatus(404);
    }

    public function test_assigned_staff_can_view_attachment()
    {
        $clientUser = User::factory()->create(['role' => 'client']);
        $client = \App\Models\Client::create(['full_name' => 'Test', 'email' => $clientUser->email]);
        $staff = User::factory()->create(['role' => 'staff']);
        
        $booking = Booking::create([
            'client_id' => $client->id,
            'staff_id' => $staff->id,
            'status' => 'confirmed',
            'booking_type' => 'custom',
            'event_type' => 'other',
            'guest_email' => $client->email,
        ]);

        $file = UploadedFile::fake()->create('test.jpg', 100, 'image/jpeg');
        $path = $file->store('bookings/messages', 'local');

        $message = \App\Models\BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_type' => 'client',
            'sender_id' => $clientUser->id,
            'visibility' => 'shared',
            'message' => 'Here is the file.',
            'attachment_path' => $path,
            'attachment_name' => 'test.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => 1024,
        ]);

        $response = $this->actingAs($staff)->get(route('secure.attachment.show', ['messageId' => $message->id]));
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/jpeg');
    }
}
