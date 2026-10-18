<?php

namespace Tests\Feature;

use App\Models\AdminAlert;
use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingCommunicationTest extends TestCase
{
    use RefreshDatabase;

    protected User $clientUser;
    protected Client $clientRecord;
    protected User $adminUser;
    protected User $otherClientUser;
    protected Client $otherClientRecord;
    protected Booking $booking;
    protected Booking $otherBooking;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'client@example.com',
            'name' => 'Alice Client',
        ]);
        $this->clientRecord = Client::create([
            'email' => $this->clientUser->email,
            'full_name' => $this->clientUser->name,
            'phone' => '09171234567',
        ]);

        $this->otherClientUser = User::factory()->create([
            'role' => 'client',
            'email' => 'other@example.com',
            'name' => 'Bob Client',
        ]);
        $this->otherClientRecord = Client::create([
            'email' => $this->otherClientUser->email,
            'full_name' => $this->otherClientUser->name,
            'phone' => '09181234567',
        ]);

        $this->adminUser = User::factory()->create([
            'role' => 'admin',
            'email' => 'admin@example.com',
            'name' => 'Admin Boss',
        ]);

        $this->booking = Booking::create([
            'client_id' => $this->clientRecord->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30),
            'venue' => 'Grand Ballroom',
            'status' => 'quotation_sent',
            'total_quoted' => 25000,
            'final_quoted_price' => 25000,
        ]);

        $this->otherBooking = Booking::create([
            'client_id' => $this->otherClientRecord->id,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(20),
            'venue' => 'Garden Oasis',
            'status' => 'quotation_sent',
            'total_quoted' => 15000,
            'final_quoted_price' => 15000,
        ]);
    }

    public function test_client_can_send_message_for_own_booking(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'Hello Admin, can we adjust the floral arrangement?',
                'submission_key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => [
                    'sender_type' => 'client',
                    'sender_label' => 'You',
                    'is_mine' => true,
                    'message' => 'Hello Admin, can we adjust the floral arrangement?',
                ],
            ]);

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Hello Admin, can we adjust the floral arrangement?',
        ]);

        $this->assertDatabaseHas('admin_alerts', [
            'booking_id' => $this->booking->id,
            'type' => 'client_message',
            'is_read' => false,
        ]);
    }

    public function test_client_cannot_send_message_for_another_clients_booking(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->otherBooking), [
                'message' => 'Trying to access someone elses booking',
            ]);

        $response->assertStatus(403);

        $this->assertDatabaseMissing('booking_messages', [
            'booking_id' => $this->otherBooking->id,
            'message' => 'Trying to access someone elses booking',
        ]);
    }

    public function test_client_cannot_retrieve_messages_for_another_clients_booking(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->getJson(route('bookings.messages.index', $this->otherBooking));

        $response->assertStatus(403);
    }

    public function test_admin_can_send_message_for_authorized_booking(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.bookings.messages.store', $this->booking), [
                'message' => 'Hello Alice, we have reviewed your request and updated the items.',
                'submission_key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => [
                    'sender_type' => 'admin',
                    'sender_label' => 'You (Admin)',
                    'is_mine' => true,
                    'message' => 'Hello Alice, we have reviewed your request and updated the items.',
                ],
            ]);

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'sender_id' => $this->adminUser->id,
            'message' => 'Hello Alice, we have reviewed your request and updated the items.',
        ]);

        $this->assertDatabaseHas('client_notifications', [
            'booking_id' => $this->booking->id,
            'user_id' => $this->clientUser->id,
        ]);
    }

    public function test_non_admin_cannot_use_admin_message_endpoints(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->getJson(route('admin.bookings.messages.index', $this->booking));

        $response->assertStatus(403);

        $postResponse = $this->actingAs($this->clientUser)
            ->postJson(route('admin.bookings.messages.store', $this->booking), [
                'message' => 'Unauthorized admin attempt',
            ]);

        $postResponse->assertStatus(403);
    }

    public function test_sender_identity_is_always_derived_server_side(): void
    {
        // Client attempts to spoof sender_type as admin and sender_id as 9999
        $response = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'Spoof attempt',
                'sender_type' => 'admin',
                'sender_id' => 9999,
            ]);

        $response->assertStatus(201);

        $message = BookingMessage::where('message', 'Spoof attempt')->first();
        $this->assertNotNull($message);
        $this->assertSame('client', $message->sender_type);
        $this->assertSame((int) $this->clientRecord->id, (int) $message->sender_id);
    }

    public function test_duplicate_submission_key_does_not_create_duplicate_message(): void
    {
        $key = 'unique-submission-key-999';

        $firstResponse = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'First try',
                'submission_key' => $key,
            ]);

        $firstResponse->assertStatus(201);

        $secondResponse = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'First try',
                'submission_key' => $key,
            ]);

        $secondResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'is_duplicate' => true,
            ]);

        $this->assertSame(1, BookingMessage::where('submission_key', $key)->count());
    }

    public function test_booking_model_messages_relationship_works(): void
    {
        BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Relationship test message',
        ]);

        $this->assertTrue($this->booking->messages()->exists());
        $this->assertInstanceOf(BookingMessage::class, $this->booking->messages->first());
        $this->assertSame('Relationship test message', $this->booking->messages->first()->message);
        $this->assertTrue($this->booking->bookingMessages()->exists());
    }

    public function test_booking_messages_are_scoped_to_correct_booking(): void
    {
        BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Message for Booking A',
        ]);

        BookingMessage::create([
            'booking_id' => $this->otherBooking->id,
            'sender_type' => 'client',
            'sender_id' => $this->otherClientRecord->id,
            'message' => 'Message for Booking B',
        ]);

        $clientRes = $this->actingAs($this->clientUser)
            ->getJson(route('bookings.messages.index', $this->booking));

        $clientRes->assertStatus(200);
        $messages = collect($clientRes->json('messages'));
        $this->assertTrue($messages->contains('message', 'Message for Booking A'));
        $this->assertFalse($messages->contains('message', 'Message for Booking B'));

        $adminRes = $this->actingAs($this->adminUser)
            ->getJson(route('admin.bookings.messages.index', $this->booking));

        $adminRes->assertStatus(200);
        $adminMessages = collect($adminRes->json('messages'));
        $this->assertTrue($adminMessages->contains('message', 'Message for Booking A'));
        $this->assertFalse($adminMessages->contains('message', 'Message for Booking B'));
    }

    public function test_unread_state_and_marking_as_read_for_admin_and_client(): void
    {
        // 1. Client sends message -> unread for Admin
        $msg1 = BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Hello from client',
            'read_at' => null,
        ]);

        $this->assertNull($msg1->read_at);
        $this->assertSame(1, $this->booking->messages()->where('sender_type', 'client')->whereNull('read_at')->count());

        // 2. Admin retrieves messages -> marks client message as read
        $adminRes = $this->actingAs($this->adminUser)
            ->getJson(route('admin.bookings.messages.index', $this->booking));

        $adminRes->assertStatus(200);
        $msg1->refresh();
        $this->assertNotNull($msg1->read_at);
        $this->assertSame(0, $this->booking->messages()->where('sender_type', 'client')->whereNull('read_at')->count());

        // 3. Admin sends reply -> unread for Client
        $msg2 = BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'sender_id' => $this->adminUser->id,
            'message' => 'Hello from admin',
            'read_at' => null,
        ]);

        $this->assertNull($msg2->read_at);
        $this->assertSame(1, $this->booking->messages()->where('sender_type', 'admin')->whereNull('read_at')->count());

        // 4. Client retrieves messages -> marks admin message as read
        $clientRes = $this->actingAs($this->clientUser)
            ->getJson(route('bookings.messages.index', $this->booking));

        $clientRes->assertStatus(200);
        $msg2->refresh();
        $this->assertNotNull($msg2->read_at);
        $this->assertSame(0, $this->booking->messages()->where('sender_type', 'admin')->whereNull('read_at')->count());
    }

    public function test_message_validation_rejects_empty_and_oversized_messages(): void
    {
        $emptyRes = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => '',
            ]);
        $emptyRes->assertStatus(422)
            ->assertJsonValidationErrors(['message']);

        $oversizedRes = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => str_repeat('a', 2001),
            ]);
        $oversizedRes->assertStatus(422)
            ->assertJsonValidationErrors(['message']);
    }

    public function test_related_quotation_version_persists_correctly(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.bookings.messages.store', $this->booking), [
                'message' => 'Referencing quotation version 3',
                'related_quotation_version' => 3,
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'message' => 'Referencing quotation version 3',
            'related_quotation_version' => 3,
        ]);
    }

    public function test_existing_change_request_creates_formatted_booking_message(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), [
                'change_type' => 'material',
                'change_item' => 'White Roses',
                'change_quantity' => 15,
                'change_reason' => 'Prefer white over red',
            ]);

        $response->assertRedirect();

        $message = BookingMessage::where('booking_id', $this->booking->id)
            ->where('sender_type', 'client')
            ->latest('id')
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Request Changes:', $message->message);
        $this->assertStringContainsString('White Roses', $message->message);
        $this->assertStringContainsString('15', $message->message);
        $this->assertStringContainsString('Prefer white over red', $message->message);
    }

    public function test_existing_cancellation_request_creates_booking_message(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->post(route('bookings.request-cancellation', $this->booking), [
                'cancellation_reason' => 'Event postponed indefinitely due to family emergency',
            ]);

        $response->assertRedirect();

        $message = BookingMessage::where('booking_id', $this->booking->id)
            ->where('sender_type', 'client')
            ->latest('id')
            ->first();

        $this->assertNotNull($message);
        $this->assertStringContainsString('Requested cancellation:', $message->message);
        $this->assertStringContainsString('Event postponed indefinitely', $message->message);
    }

    public function test_admin_message_triggers_client_notification(): void
    {
        $response = $this->actingAs($this->adminUser)
            ->postJson(route('admin.bookings.messages.store', $this->booking), [
                'message' => 'Please review the updated arrangement proposal.',
                'submission_key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('client_notifications', [
            'booking_id' => $this->booking->id,
            'user_id' => $this->clientUser->id,
            'type' => 'booking_update',
            'title' => 'New message from Admin',
            'is_read' => false,
        ]);
    }

    public function test_client_message_triggers_admin_alert(): void
    {
        $response = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'Client asking a clarification question.',
                'submission_key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('admin_alerts', [
            'booking_id' => $this->booking->id,
            'type' => 'client_message',
            'is_read' => false,
        ]);
    }

    public function test_admin_booking_review_renders_communication_tab_and_component(): void
    {
        // Add an incoming client message
        BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Hello Admin, this is a test message from client.',
            'visibility' => 'client_admin',
            'related_quotation_version' => 1,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->get(route('admin.bookings.show', $this->booking));

        $response->assertOk();
        $response->assertSee('Communication');
        $response->assertSee('Client ↔ Admin · Booking #' . $this->booking->id);
        $response->assertSee('Live Sync');
        $response->assertSee('Hello Admin, this is a test message from client.');
        $response->assertSee('Ref: Quotation v1');
        // The conversation opens from a floating message button on every tab instead of its own tab.
        $response->assertSee('id="admin-chat-launcher"', false);
        $response->assertSee('id="admin-chat-panel"', false);
        $response->assertSee('data-chat-view="meetings"', false);
        $response->assertSee('id="meetings-heading"', false);
        $response->assertDontSee('data-target="tab-communication"', false);
    }

    public function test_client_booking_analysis_renders_conversation_component(): void
    {
        BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'sender_id' => $this->adminUser->id,
            'message' => 'Hello Alice, quotation is prepared for your wedding event.',
            'visibility' => 'client_admin',
            'related_quotation_version' => 1,
            'read_at' => null,
        ]);

        $response = $this->actingAs($this->clientUser)
            ->get(route('bookings.analysis', $this->booking));

        $response->assertOk();
        $response->assertSee('Communication');
        $response->assertSee('Client ↔ Admin · Booking #' . $this->booking->id);
        $response->assertSee('Live Sync');
        $response->assertSee('Hello Alice, quotation is prepared for your wedding event.');
        $response->assertSee('booking-conversation-root');
    }

    public function test_client_notification_for_admin_message_links_to_booking_conversation(): void
    {
        ClientNotification::create([
            'user_id' => $this->clientUser->id,
            'booking_id' => $this->booking->id,
            'type' => 'booking_update',
            'title' => 'New message from Admin',
            'message' => 'Admin replied: Please check the revised flower options.',
            'is_read' => false,
        ]);

        $response = $this->actingAs($this->clientUser)
            ->get(route('client.notifications.index'));

        $response->assertOk();
        $response->assertSee('New message from Admin');
        $response->assertSee('Admin replied: Please check the revised flower options.');
        $response->assertSee('data-booking-id="' . $this->booking->id . '"', false);
    }

    public function test_two_client_isolation_on_all_communication_surfaces(): void
    {
        // Client A's message
        BookingMessage::create([
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Confidential details from Alice',
            'visibility' => 'client_admin',
        ]);

        // Client B tries to view Client A's booking analysis
        $responseView = $this->actingAs($this->otherClientUser)
            ->get(route('bookings.analysis', $this->booking));
        $responseView->assertForbidden();

        // Client B tries to fetch Client A's messages via JSON
        $responseIndex = $this->actingAs($this->otherClientUser)
            ->getJson(route('bookings.messages.index', $this->booking));
        $responseIndex->assertForbidden();

        // Client B tries to post a message into Client A's booking
        $responsePost = $this->actingAs($this->otherClientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'Malicious intrusion attempt',
                'submission_key' => (string) Str::uuid(),
            ]);
        $responsePost->assertForbidden();
    }

    public function test_two_authenticated_sessions_full_e2e_communication_workflow(): void
    {
        Storage::fake('local');

        // -------------------------------------------------------------
        // STEP 1: CLIENT SENDS MESSAGE WITH QUOTATION REFERENCE
        // -------------------------------------------------------------
        $clientSubmissionKey = (string) Str::uuid();
        $clientSendResponse = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'Hello Admin, can we update the white lilies to calla lilies?',
                'submission_key' => $clientSubmissionKey,
                'related_quotation_version' => 1,
            ]);

        $clientSendResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => [
                    'sender_type' => 'client',
                    'sender_label' => 'You',
                    'is_mine' => true,
                    'message' => 'Hello Admin, can we update the white lilies to calla lilies?',
                    'related_quotation_version' => 1,
                    'is_read' => false,
                ],
            ]);

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->clientRecord->id,
            'message' => 'Hello Admin, can we update the white lilies to calla lilies?',
            'related_quotation_version' => 1,
            'read_at' => null,
        ]);

        $this->assertDatabaseHas('admin_alerts', [
            'booking_id' => $this->booking->id,
            'type' => 'client_message',
            'is_read' => false,
        ]);

        // -------------------------------------------------------------
        // STEP 2: ADMIN RECEIVES WITHOUT REFRESH (Simulated 3.5s poll)
        // -------------------------------------------------------------
        $adminPollResponse = $this->actingAs($this->adminUser)
            ->getJson(route('admin.bookings.messages.index', $this->booking));

        $adminPollResponse->assertOk()
            ->assertJson([
                'success' => true,
                'unread_count' => 0,
            ]);

        $adminReceivedMessages = collect($adminPollResponse->json('messages'));
        $clientMessageForAdmin = $adminReceivedMessages->firstWhere('submission_key', $clientSubmissionKey);

        $this->assertNotNull($clientMessageForAdmin);
        $this->assertSame('client', $clientMessageForAdmin['sender_type']);
        $this->assertSame('Alice Client', $clientMessageForAdmin['sender_label']);
        $this->assertFalse($clientMessageForAdmin['is_mine']);
        $this->assertSame(1, $clientMessageForAdmin['related_quotation_version']);

        // Database verified: read_at marked and admin alert resolved
        $this->assertDatabaseMissing('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'read_at' => null,
        ]);
        $this->assertDatabaseMissing('admin_alerts', [
            'booking_id' => $this->booking->id,
            'type' => 'client_message',
            'is_read' => false,
        ]);

        // -------------------------------------------------------------
        // STEP 3: ADMIN REPLIES
        // -------------------------------------------------------------
        $adminSubmissionKey = (string) Str::uuid();
        $adminSendResponse = $this->actingAs($this->adminUser)
            ->postJson(route('admin.bookings.messages.store', $this->booking), [
                'message' => 'Certainly Alice! Calla lilies have been added to your quotation.',
                'submission_key' => $adminSubmissionKey,
                'related_quotation_version' => 1,
            ]);

        $adminSendResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => [
                    'sender_type' => 'admin',
                    'sender_label' => 'You (Admin)',
                    'is_mine' => true,
                    'message' => 'Certainly Alice! Calla lilies have been added to your quotation.',
                    'related_quotation_version' => 1,
                    'is_read' => false,
                ],
            ]);

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'sender_id' => $this->adminUser->id,
            'message' => 'Certainly Alice! Calla lilies have been added to your quotation.',
            'read_at' => null,
        ]);

        $this->assertDatabaseHas('client_notifications', [
            'booking_id' => $this->booking->id,
            'user_id' => $this->clientUser->id,
            'type' => 'booking_update',
            'title' => 'New message from Admin',
            'is_read' => false,
        ]);

        // -------------------------------------------------------------
        // STEP 4: CLIENT RECEIVES WITHOUT REFRESH (Simulated 3.5s poll)
        // -------------------------------------------------------------
        $clientPollResponse = $this->actingAs($this->clientUser)
            ->getJson(route('bookings.messages.index', $this->booking));

        $clientPollResponse->assertOk()
            ->assertJson([
                'success' => true,
                'unread_count' => 0,
            ]);

        $clientReceivedMessages = collect($clientPollResponse->json('messages'));
        $adminMessageForClient = $clientReceivedMessages->firstWhere('submission_key', $adminSubmissionKey);

        $this->assertNotNull($adminMessageForClient);
        $this->assertSame('admin', $adminMessageForClient['sender_type']);
        $this->assertSame('Admin', $adminMessageForClient['sender_label']);
        $this->assertFalse($adminMessageForClient['is_mine']);

        // Database verified: read_at marked for admin reply
        $this->assertDatabaseMissing('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'read_at' => null,
        ]);

        // -------------------------------------------------------------
        // STEP 5: ATTACHMENT ACCESS & SECURITY
        // -------------------------------------------------------------
        $fakePdf = UploadedFile::fake()->create('event_layout.pdf', 300, 'application/pdf');
        $attachmentSubmissionKey = (string) Str::uuid();

        $clientAttachmentResponse = $this->actingAs($this->clientUser)
            ->post(route('bookings.messages.store', $this->booking), [
                'message' => 'Here is our preferred venue arrangement layout.',
                'submission_key' => $attachmentSubmissionKey,
                'attachment' => $fakePdf,
                'attachment_category' => 'event_venue',
            ], ['Accept' => 'application/json']);

        $clientAttachmentResponse->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => [
                    'has_attachment' => true,
                    'attachment_name' => 'event_layout.pdf',
                    'attachment_category' => 'event_venue',
                ],
            ]);

        $attachmentMsg = BookingMessage::where('submission_key', $attachmentSubmissionKey)->first();
        $this->assertNotNull($attachmentMsg);
        $this->assertNotNull($attachmentMsg->attachment_path);
        Storage::disk('local')->assertExists($attachmentMsg->attachment_path);

        // Client owner can access attachment
        $this->actingAs($this->clientUser)
            ->get(route('secure.attachment.show', $attachmentMsg->id))
            ->assertOk();

        // Admin can access attachment
        $this->actingAs($this->adminUser)
            ->get(route('secure.attachment.show', $attachmentMsg->id))
            ->assertOk();

        // Another client cannot access attachment
        $this->actingAs($this->otherClientUser)
            ->get(route('secure.attachment.show', $attachmentMsg->id))
            ->assertForbidden();

        // -------------------------------------------------------------
        // STEP 6: REFRESH PERSISTENCE (Both reload full HTML views)
        // -------------------------------------------------------------
        $clientHtml = $this->actingAs($this->clientUser)
            ->get(route('bookings.analysis', $this->booking));
        $clientHtml->assertOk();
        $clientHtml->assertSee('Hello Admin, can we update the white lilies to calla lilies?');
        $clientHtml->assertSee('Certainly Alice! Calla lilies have been added to your quotation.');
        $clientHtml->assertSee('Ref: Quotation v1');
        $clientHtml->assertSee('event_layout.pdf');

        $adminHtml = $this->actingAs($this->adminUser)
            ->get(route('admin.bookings.show', $this->booking));
        $adminHtml->assertOk();
        $adminHtml->assertSee('Hello Admin, can we update the white lilies to calla lilies?');
        $adminHtml->assertSee('Certainly Alice! Calla lilies have been added to your quotation.');
        $adminHtml->assertSee('Ref: Quotation v1');
        $adminHtml->assertSee('event_layout.pdf');

        // -------------------------------------------------------------
        // STEP 7: DUPLICATE SUBMISSION PROTECTION
        // -------------------------------------------------------------
        $clientDupResponse = $this->actingAs($this->clientUser)
            ->postJson(route('bookings.messages.store', $this->booking), [
                'message' => 'Hello Admin, can we update the white lilies to calla lilies?',
                'submission_key' => $clientSubmissionKey,
            ]);
        $clientDupResponse->assertOk()
            ->assertJson(['is_duplicate' => true]);

        $adminDupResponse = $this->actingAs($this->adminUser)
            ->postJson(route('admin.bookings.messages.store', $this->booking), [
                'message' => 'Certainly Alice! Calla lilies have been added to your quotation.',
                'submission_key' => $adminSubmissionKey,
            ]);
        $adminDupResponse->assertOk()
            ->assertJson(['is_duplicate' => true]);

        $this->assertSame(3, $this->booking->messages()->count());

        // -------------------------------------------------------------
        // STEP 8: CHANGE REQUEST & CANCELLATION REQUEST PRESENTATION
        // -------------------------------------------------------------
        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-changes', $this->booking), [
                'change_type' => 'material',
                'change_item' => 'Orchids',
                'change_quantity' => 20,
                'change_reason' => 'Accent flower requested',
            ]);

        $this->actingAs($this->clientUser)
            ->post(route('bookings.request-cancellation', $this->booking), [
                'cancellation_reason' => 'Schedule conflict with family',
            ]);

        $clientReload = $this->actingAs($this->clientUser)->get(route('bookings.analysis', $this->booking));
        $clientReload->assertSee('Change Request');
        $clientReload->assertSee('Cancellation Request');

        $adminReload = $this->actingAs($this->adminUser)->get(route('admin.bookings.show', $this->booking));
        $adminReload->assertSee('Change Request');
        $adminReload->assertSee('Cancellation Request');

        // -------------------------------------------------------------
        // STEP 9: NOTIFICATION NAVIGATION
        // -------------------------------------------------------------
        $notifRes = $this->actingAs($this->clientUser)
            ->get(route('client.notifications.index'));
        $notifRes->assertOk();
        $notifRes->assertSee('New message from Admin');
        $notifRes->assertSee('data-booking-id="' . $this->booking->id . '"', false);

        // -------------------------------------------------------------
        // STEP 10: UNAUTHORIZED ACCESS & BOOKING ISOLATION
        // -------------------------------------------------------------
        $this->actingAs($this->otherClientUser)
            ->getJson(route('bookings.messages.index', $this->booking))
            ->assertForbidden();

        $this->actingAs($this->otherClientUser)
            ->postJson(route('bookings.messages.store', $this->booking), ['message' => 'Malicious intrusion'])
            ->assertForbidden();

        $this->actingAs($this->otherClientUser)
            ->get(route('bookings.analysis', $this->booking))
            ->assertForbidden();

        // Guest unauthenticated check
        $this->app['auth']->logout();
        $this->getJson(route('bookings.messages.index', $this->booking))->assertUnauthorized();
        $this->postJson(route('bookings.messages.store', $this->booking), ['message' => 'Guest attempt'])->assertUnauthorized();

        // -------------------------------------------------------------
        // STEP 11: RESPONSIVE UI CHECKS
        // -------------------------------------------------------------
        $clientAnalysisView = $this->actingAs($this->clientUser)
            ->get(route('bookings.analysis', $this->booking));
        $clientAnalysisView->assertSee('booking-conversation-root');
        $clientAnalysisView->assertSee('Live Sync');
        $clientAnalysisView->assertSee('max-w-xl', false);
        $clientAnalysisView->assertSee('sm:max-w-md', false);
        $clientAnalysisView->assertSee('comm-send-btn');
        $clientAnalysisView->assertSee('comm-message-text');
    }
}
