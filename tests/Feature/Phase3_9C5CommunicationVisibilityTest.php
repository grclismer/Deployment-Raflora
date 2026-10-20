<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Phase3_9C5CommunicationVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');
    }

    public function test_client_cannot_see_admin_staff_messages()
    {
        $client = User::factory()->create(['role' => 'client']);
        $clientRecord = \App\Models\Client::create(['full_name' => 'Test', 'email' => $client->email]);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $booking = Booking::create([
            'client_id' => $clientRecord->id,
            'guest_email' => $client->email,
            'booking_type' => 'custom',
            'event_type' => 'other',
            'staff_id' => $staff->id,
            'status' => 'approved',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $admin->id,
            'sender_type' => 'admin',
            'message' => 'Private note for staff',
            'visibility' => 'admin_staff',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $client->id,
            'sender_type' => 'client',
            'message' => 'Hello admin',
            'visibility' => 'client_admin',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $admin->id,
            'sender_type' => 'admin',
            'message' => 'Hello everyone',
            'visibility' => 'shared',
        ]);

        $response = $this->actingAs($client)->get(route('bookings.analysis', $booking->id));
        $response->assertStatus(200);
        
        $response->assertSee('Hello admin');
        $response->assertSee('Hello everyone');
        $response->assertDontSee('Private note for staff');
    }

    public function test_staff_cannot_see_client_admin_messages()
    {
        $client = User::factory()->create(['role' => 'client']);
        $clientRecord = \App\Models\Client::create(['full_name' => 'Test', 'email' => $client->email]);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $booking = Booking::create([
            'client_id' => $clientRecord->id,
            'guest_email' => $client->email,
            'booking_type' => 'custom',
            'event_type' => 'other',
            'staff_id' => $staff->id,
            'status' => 'approved',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $admin->id,
            'sender_type' => 'admin',
            'message' => 'Private note for staff',
            'visibility' => 'admin_staff',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $client->id,
            'sender_type' => 'client',
            'message' => 'Hello admin',
            'visibility' => 'client_admin',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $staff->id,
            'sender_type' => 'staff',
            'message' => 'Hello everyone',
            'visibility' => 'shared',
        ]);

        $response = $this->actingAs($staff)->get(route('staff.events.show', $booking->id));
        $response->assertStatus(200);
        
        $response->assertSee('Private note for staff');
        $response->assertSee('Hello everyone');
        $response->assertDontSee('Hello admin');
    }

    public function test_admin_can_see_all_messages()
    {
        $client = User::factory()->create(['role' => 'client']);
        $clientRecord = \App\Models\Client::create(['full_name' => 'Test', 'email' => $client->email]);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);

        $booking = Booking::create([
            'client_id' => $clientRecord->id,
            'guest_email' => $client->email,
            'booking_type' => 'custom',
            'event_type' => 'other',
            'staff_id' => $staff->id,
            'status' => 'approved',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $admin->id,
            'sender_type' => 'admin',
            'message' => 'Private note for staff',
            'visibility' => 'admin_staff',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $client->id,
            'sender_type' => 'client',
            'message' => 'Hello admin',
            'visibility' => 'client_admin',
        ]);

        BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $staff->id,
            'sender_type' => 'staff',
            'message' => 'Hello everyone',
            'visibility' => 'shared',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bookings.show', $booking->id));
        $response->assertStatus(200);
        
        $response->assertSee('Private note for staff');
        $response->assertSee('Hello admin');
        $response->assertSee('Hello everyone');
    }

    public function test_attachment_security_respects_visibility()
    {
        $client = User::factory()->create(['role' => 'client']);
        $clientRecord = \App\Models\Client::create(['full_name' => 'Test', 'email' => $client->email]);
        $staff = User::factory()->create(['role' => 'staff']);
        $admin = User::factory()->create(['role' => 'admin']);
        $unassignedStaff = User::factory()->create(['role' => 'staff']);
        $otherClient = User::factory()->create(['role' => 'client']);
        $otherClientRecord = \App\Models\Client::create(['full_name' => 'Other', 'email' => $otherClient->email]);

        $booking = Booking::create([
            'client_id' => $clientRecord->id,
            'guest_email' => $client->email,
            'booking_type' => 'custom',
            'event_type' => 'other',
            'staff_id' => $staff->id,
            'status' => 'approved',
        ]);

        $clientAdminMsg = BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $client->id,
            'sender_type' => 'client',
            'message' => 'Private file',
            'visibility' => 'client_admin',
            'attachment_path' => 'booking_attachments/private1.pdf',
            'attachment_name' => 'private1.pdf',
            'attachment_category' => 'other_booking_document',
        ]);

        $adminStaffMsg = BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $admin->id,
            'sender_type' => 'admin',
            'message' => 'Staff file',
            'visibility' => 'admin_staff',
            'attachment_path' => 'booking_attachments/staff1.pdf',
            'attachment_name' => 'staff1.pdf',
            'attachment_category' => 'general',
        ]);

        $sharedMsg = BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_id' => $staff->id,
            'sender_type' => 'staff',
            'message' => 'Shared file',
            'visibility' => 'shared',
            'attachment_path' => 'booking_attachments/shared1.pdf',
            'attachment_name' => 'shared1.pdf',
            'attachment_category' => 'general',
        ]);

        // Client attempts
        $this->actingAs($client)->get(route('secure.attachment.show', $clientAdminMsg->id))->assertStatus(404); 
        $this->actingAs($client)->get(route('secure.attachment.show', $sharedMsg->id))->assertStatus(404);
        $this->actingAs($client)->get(route('secure.attachment.show', $adminStaffMsg->id))->assertStatus(403);

        // Staff attempts
        $this->actingAs($staff)->get(route('secure.attachment.show', $adminStaffMsg->id))->assertStatus(404);
        $this->actingAs($staff)->get(route('secure.attachment.show', $sharedMsg->id))->assertStatus(404);
        $this->actingAs($staff)->get(route('secure.attachment.show', $clientAdminMsg->id))->assertStatus(403);

        // Admin attempts
        $this->actingAs($admin)->get(route('secure.attachment.show', $clientAdminMsg->id))->assertStatus(404);
        $this->actingAs($admin)->get(route('secure.attachment.show', $adminStaffMsg->id))->assertStatus(404);
        $this->actingAs($admin)->get(route('secure.attachment.show', $sharedMsg->id))->assertStatus(404);
    }
}
