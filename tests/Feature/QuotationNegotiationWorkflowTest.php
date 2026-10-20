<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class QuotationNegotiationWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $client;
    protected Booking $booking;
    protected Quotation $quotationV1;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->admin = User::factory()->create(['role' => 'admin']);
        $this->client = User::factory()->create(['role' => 'client']);
        $clientProfile = \App\Models\Client::create(['full_name' => 'Test Client', 'email' => $this->client->email, 'phone' => '09170000000', 'address' => 'Address', 'user_id' => $this->client->id]);
        
        $this->booking = Booking::create([
            'client_id' => $clientProfile->id,
            'status' => 'quotation_sent',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30),
            'venue' => 'Grand Hall',
            'guest_count' => 100,
        ]);

        \App\Models\BookingItem::create([
            'booking_id' => $this->booking->id,
            'item_name' => 'Roses',
            'quantity' => 10,
            'quoted_unit_price' => 500,
            'confirmed_at' => now(),
            'is_ai_suggested' => false,
        ]);

        $this->quotationV1 = Quotation::create([
            'booking_id' => $this->booking->id,
            'version' => 1,
            'status' => 'issued',
            'final_quoted_price' => 5000,
            'valid_until' => now()->addDays(7),
        ]);
    }

    public function test_admin_reply_creates_message_and_preserves_negotiation_state()
    {
        $this->booking->update(['status' => 'change_requested']);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'We can update the flowers.',
            'action' => 'reply_only',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'admin',
            'sender_id' => $this->admin->id,
            'visibility' => 'shared',
            'message' => 'We can update the flowers.',
        ]);

        // Booking status must remain change_requested, not quotation_sent
        $this->assertEquals('change_requested', $this->booking->fresh()->status);
        
        // V1 should still be issued, but not a new quotation
        $this->assertEquals(1, Quotation::where('booking_id', $this->booking->id)->count());
    }

    public function test_client_can_reply_during_change_requested()
    {
        $this->booking->update(['status' => 'change_requested']);

        $response = $this->actingAs($this->client)->post(route('bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'This is a test reply from the client.',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->booking->client_id,
            'visibility' => 'shared',
            'message' => 'This is a test reply from the client.',
        ]);

        $this->assertEquals('change_requested', $this->booking->fresh()->status);
    }

    public function test_admin_cannot_silently_mutate_items_via_send_note()
    {
        $this->booking->update(['status' => 'change_requested']);
        $originalPrice = $this->booking->final_quoted_price;

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'venue' => 'Grand Hall',
            'status' => 'change_requested', 
            'action' => 'send_note', // This action no longer exists in controller
            'valid_until' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        
        // Ensure no new quotation was issued
        $this->assertEquals(1, Quotation::where('booking_id', $this->booking->id)->count());
        
        // Ensure no "note_sent" email was sent, and no "New update" notification was created
        $this->assertDatabaseMissing('client_notifications', [
            'booking_id' => $this->booking->id,
            'title' => 'New update from Raflora Enterprises'
        ]);
    }

    public function test_admin_ordinary_save_cannot_silently_mutate_quotation_affecting_data()
    {
        $this->booking->update(['status' => 'change_requested']);
        $originalPrice = $this->booking->final_quoted_price;

        $bookingItem = $this->booking->bookingItems()->first();
        $originalQuantity = $bookingItem->quantity;

        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'venue' => 'Grand Hall',
            'status' => 'change_requested', 
            'action' => 'save',
            'final_quoted_price' => 9999,
            'items' => [
                [
                    'booking_item_id' => $bookingItem->id,
                    'quantity' => 50,
                    'unit_price' => 100,
                ]
            ]
        ]);

        $response->assertRedirect();
        
        $this->booking->refresh();
        $bookingItem->refresh();

        // The final quoted price should not be mutated by an ordinary save
        $this->assertEquals($originalPrice, $this->booking->final_quoted_price);
        
        // The booking item quantity should not be mutated by an ordinary save
        $this->assertEquals($originalQuantity, $bookingItem->quantity);
    }


    public function test_client_cannot_accept_stale_quotation_during_negotiation()
    {
        // Client requests changes, booking becomes change_requested
        $this->booking->update(['status' => 'change_requested']);

        // Admin replies (preserves change_requested)
        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Sure, we will revise it.',
            'action' => 'reply_only',
        ]);

        // Client attempts to accept the old quotation
        $response = $this->actingAs($this->client)->post(route('bookings.accept', $this->booking));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'This booking cannot be accepted at this stage.');

        $this->assertEquals('change_requested', $this->booking->fresh()->status);
        $this->assertEquals('issued', $this->quotationV1->fresh()->status);
    }

    public function test_actual_quotation_issuance_creates_v2_and_supersedes_v1()
    {
        $this->booking->update(['status' => 'change_requested']);

        // Admin uses the main update method to send a new quotation
        $response = $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'venue' => 'Grand Hall',
            'status' => 'change_requested', 
            'action' => 'send_quotation',
            'valid_until' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $response->assertSessionMissing('error');

        $this->booking->refresh();
        $this->assertEquals('quotation_sent', $this->booking->status);

        // V1 should be superseded
        $this->assertEquals('superseded', $this->quotationV1->fresh()->status);

        // V2 should be created and issued
        $quotations = Quotation::where('booking_id', $this->booking->id)->orderBy('version')->get();
        $this->assertCount(2, $quotations);
        
        $v2 = $quotations->last();
        $this->assertEquals(2, $v2->version);
        $this->assertEquals('issued', $v2->status);
        
        // Active quotation should be V2
        $this->assertEquals($v2->id, $this->booking->activeQuotation->id);
    }

    public function test_multi_version_quotation_negotiation_flow()
    {
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'venue' => 'Grand Hall',
            'status' => 'change_requested', 
            'action' => 'send_quotation',
            'valid_until' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $this->booking->refresh();
        $this->assertEquals('quotation_sent', $this->booking->status);
        
        // Client requests changes again
        $this->booking->update(['status' => 'change_requested']);
        
        $this->actingAs($this->admin)->put(route('admin.bookings.update', $this->booking), [
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30)->format('Y-m-d'),
            'venue' => 'Grand Hall',
            'status' => 'change_requested', 
            'action' => 'send_quotation',
            'valid_until' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $this->booking->refresh();
        $this->assertEquals('quotation_sent', $this->booking->status);

        $quotations = Quotation::where('booking_id', $this->booking->id)->orderBy('version')->get();
        $this->assertCount(3, $quotations);
        
        $v1 = $quotations[0];
        $v2 = $quotations[1];
        $v3 = $quotations[2];

        $this->assertEquals('superseded', $v1->status);
        $this->assertEquals('superseded', $v2->status);
        $this->assertEquals('issued', $v3->status);
        $this->assertEquals($v3->id, $this->booking->activeQuotation->id);

        // Client accepts V3
        $response = $this->actingAs($this->client)->post(route('bookings.accept', $this->booking));
        $response->assertRedirect();
        
        $this->booking->refresh();
        $this->assertEquals('approved', $this->booking->status);
        $this->assertEquals('accepted', $v3->fresh()->status);
    }

    public function test_cancellation_approval_transitions_to_cancelled()
    {
        $this->booking->update(['status' => 'cancellation_requested', 'cancellation_reason' => 'Changed my mind']);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $this->booking), [
            'action' => 'approve',
            'admin_note' => 'Approved as requested.',
        ]);

        $response->assertRedirect();
        
        $this->booking->refresh();
        $this->assertEquals('cancelled', $this->booking->status);
        $this->assertEquals('Changed my mind', $this->booking->cancellation_reason);

        // Verify message was created
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'visibility' => 'client_admin',
            'message' => 'Approved as requested.',
        ]);

        // Verify client cannot accept
        $acceptResponse = $this->actingAs($this->client)->post(route('bookings.accept', $this->booking));
        $acceptResponse->assertSessionHas('error');
    }

    public function test_cancellation_denial_transitions_to_change_requested()
    {
        $this->booking->update(['status' => 'cancellation_requested', 'cancellation_reason' => 'Too expensive']);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $this->booking), [
            'action' => 'deny',
            'admin_note' => 'We can offer a discount instead.',
        ]);

        $response->assertRedirect();
        
        $this->booking->refresh();
        // Preserves stale quotation protection by transitioning to change_requested
        $this->assertEquals('change_requested', $this->booking->status);
        $this->assertNull($this->booking->cancellation_reason);

        // Verify client cannot immediately accept the old quotation
        $acceptResponse = $this->actingAs($this->client)->post(route('bookings.accept', $this->booking));
        $acceptResponse->assertSessionHas('error');
        
        // V1 should still be issued
        $this->assertEquals('issued', $this->quotationV1->fresh()->status);
    }

    public function test_admin_reply_creates_client_notification()
    {
        $this->booking->update(['status' => 'change_requested']);

        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Reply test',
            'action' => 'reply_only',
        ]);

        $this->assertDatabaseHas('client_notifications', [
            'user_id' => $this->client->id,
            'booking_id' => $this->booking->id,
            'title' => 'New message from Admin',
            'type' => 'booking_update'
        ]);
    }

    public function test_cancellation_approval_and_denial_create_notifications()
    {
        $this->booking->update(['status' => 'cancellation_requested']);

        $response = $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $this->booking), [
            'action' => 'approve',
        ]);
        $this->assertDatabaseHas('client_notifications', [
            'user_id' => $this->client->id,
            'booking_id' => $this->booking->id,
            'title' => 'Cancellation Approved',
        ]);
        
        // Test Denial on a fresh booking
        $clientProfile = \App\Models\Client::first();
        $booking2 = Booking::create([
            'client_id' => $clientProfile->id,
            'status' => 'cancellation_requested',
            'pre_cancellation_status' => 'quotation_sent',
            'event_type' => 'Wedding',
            'event_date' => now()->addDays(30),
            'venue' => 'Grand Hall',
            'guest_count' => 100,
        ]);

        $this->actingAs($this->admin)->post(route('admin.bookings.handle-cancellation', $booking2), [
            'action' => 'deny',
        ]);

        $this->assertDatabaseHas('client_notifications', [
            'user_id' => $this->client->id,
            'booking_id' => $booking2->id,
            'title' => 'Cancellation Denied',
        ]);
    }

    public function test_unauthorized_users_cannot_perform_admin_negotiation_actions()
    {
        $this->booking->update(['status' => 'change_requested']);

        // Unauthenticated
        $this->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Test', 'action' => 'reply_only'
        ])->assertRedirect(route('login'));

        $this->post(route('admin.bookings.handle-cancellation', $this->booking), [
            'action' => 'approve'
        ])->assertRedirect(route('login'));

        // Authenticated as Client
        $this->actingAs($this->client)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Test', 'action' => 'reply_only'
        ])->assertForbidden();

        $this->actingAs($this->client)->post(route('admin.bookings.handle-cancellation', $this->booking), [
            'action' => 'approve'
        ])->assertForbidden();
    }

    public function test_client_cannot_accept_another_clients_quotation()
    {
        $otherClient = User::factory()->create(['role' => 'client']);
        
        $response = $this->actingAs($otherClient)->post(route('bookings.accept', $this->booking));
        
        $response->assertForbidden();
    }

    public function test_payment_is_gated_during_negotiation_and_cancellation_requests()
    {
        $this->booking->update(['status' => 'change_requested']);

        $response = $this->actingAs($this->client)->post(route('bookings.payment.reference', $this->booking), [
            'reference_number' => '123456',
        ]);

        $response->assertSessionHas('error'); // assuming validation/gating returns error

        $this->withoutExceptionHandling(); $this->booking->update(['status' => 'cancellation_requested']);

        $response = $this->actingAs($this->client)->post(route('bookings.payment.reference', $this->booking), [
            'reference_number' => '123456',
        ]);

        $response->assertSessionHas('error');
    }

    public function test_client_proposal_feedback_creates_feedback_record_and_booking_message()
    {
        // Arrange
        $presentation = \App\Models\Presentation::create([
            'booking_id' => $this->booking->id,
            'version' => 1,
            'file_name' => 'proposal.pdf',
            'file_path' => 'proposals/proposal.pdf',
            'status' => 'sent',
            'sent_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($this->client)->post(route('bookings.proposals.feedback', [
            'booking' => $this->booking->id,
            'presentation' => $presentation->id
        ]), [
            'approval_status' => 'needs_revision',
            'feedback_text' => 'need to add ribbon',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Original feedback record must be preserved
        $presentation->refresh();
        $this->assertEquals('needs_revision', $presentation->approval_status);
        $this->assertEquals('need to add ribbon', $presentation->feedback_text);

        // BookingMessage must also be created
        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->booking->client_id,
            'visibility' => 'client_admin',
            'message' => 'Proposal v1 feedback: need to add ribbon',
            'related_quotation_version' => 1,
        ]);
    }

    public function test_client_request_changes_creates_message_and_updates_status()
    {
        // Act
        $response = $this->actingAs($this->client)->post(route('bookings.request-changes', $this->booking), [
            'change_type' => 'material',
            'change_item' => 'Roses',
            'change_quantity' => 15,
            'change_reason' => 'Please add 15 red roses to the arrangement.',
        ]);

        // Assert
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->booking->refresh();
        $this->assertEquals('change_requested', $this->booking->status);

        $expectedMessage = "Request Changes:\nType: Material\nItem/Material: Roses\nQuantity: 15\nDetails: Please add 15 red roses to the arrangement.";

        $this->assertDatabaseHas('booking_messages', [
            'booking_id' => $this->booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->booking->client_id,
            'visibility' => 'client_admin',
            'message' => $expectedMessage,
            'related_quotation_version' => 1,
        ]);
    }
    public function test_admin_reply_idempotency_prevents_duplicate_submission()
    {
        $this->booking->update(['status' => 'change_requested']);
        $submissionKey = 'test-uuid-1234';

        // Act - First submission
        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Yes, we can include that.',
            'action' => 'reply_only',
            'submission_key' => $submissionKey,
        ]);

        // Act - Immediate duplicate submission with same key
        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Yes, we can include that.',
            'action' => 'reply_only',
            'submission_key' => $submissionKey,
        ]);

        // Assert only one message exists for this key
        $messages = \App\Models\BookingMessage::where('submission_key', $submissionKey)->get();
        $this->assertCount(1, $messages);
    }

    public function test_admin_reply_allows_identical_message_with_different_submission_key()
    {
        $this->booking->update(['status' => 'change_requested']);

        // Act - First submission A
        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Yes, we can include that.',
            'action' => 'reply_only',
            'submission_key' => 'submission-A',
        ]);

        // Act - Submission B with identical message text
        $this->actingAs($this->admin)->post(route('admin.bookings.reply', $this->booking), [
            'visibility' => 'shared',
            'message' => 'Yes, we can include that.',
            'action' => 'reply_only',
            'submission_key' => 'submission-B',
        ]);

        // Assert two separate messages exist
        $messages = \App\Models\BookingMessage::where('booking_id', $this->booking->id)
            ->where('message', 'Yes, we can include that.')
            ->get();
        $this->assertCount(2, $messages);
    }
}
