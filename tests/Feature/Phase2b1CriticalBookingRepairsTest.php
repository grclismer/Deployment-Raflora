<?php

namespace Tests\Feature;

use App\Models\AssetReturn;
use App\Models\Booking;
use App\Models\Client;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\ReturnItem;
use App\Models\ReturnItemEvidence;
use App\Models\TemporaryGuestBooking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class Phase2b1CriticalBookingRepairsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Requirement A: Guest claim identity and foreign-key integrity
     * with deliberately different User and Client IDs.
     */
    public function test_guest_booking_claim_preserves_identity_and_foreign_key_integrity_with_disparate_user_and_client_ids(): void
    {
        // Pre-create dummy records so User ID and Client ID do not align
        for ($i = 0; $i < 5; $i++) {
            User::factory()->create();
        }
        for ($i = 0; $i < 12; $i++) {
            Client::create([
                'full_name' => "Dummy Client {$i}",
                'email' => "dummy{$i}@example.com",
                'phone' => '09123456789',
                'address' => 'Dummy Address',
            ]);
        }

        // Target user (e.g. ID will be 6)
        $user = User::factory()->create([
            'email' => 'maria.santos@example.com',
            'name' => 'Maria Santos',
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        // Pre-existing client record for this user (e.g. ID will be 13)
        $client = Client::create([
            'full_name' => 'Maria Santos',
            'email' => 'maria.santos@example.com',
            'phone' => '09181112233',
            'address' => '123 Sampaguita St, Quezon City',
        ]);

        $this->assertNotEquals($user->id, $client->id, 'User ID and Client ID must be deliberately different for this test.');

        $rawToken = (string) Str::uuid();
        $tempBooking = TemporaryGuestBooking::create([
            'claim_token_hash' => hash('sha256', $rawToken),
            'guest_name' => 'Maria Santos',
            'guest_email' => 'maria.santos@example.com',
            'guest_phone' => '09181112233',
            'guest_address' => '123 Sampaguita St, Quezon City',
            'booking_type' => 'custom',
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '14:00:00',
            'venue' => 'Manila Cathedral',
            'guest_count' => 150,
            'expires_at' => now()->addHours(24),
        ]);

        $response = $this->actingAs($user)->post(route('client.claim-guest-booking.claim', ['token' => $rawToken]));

        $response->assertRedirect(route('bookings'));
        $response->assertSessionHas('success');

        // Verify that temporary_guest_bookings.client_id references clients.id (NOT users.id)
        $this->assertDatabaseHas('temporary_guest_bookings', [
            'id' => $tempBooking->id,
            'client_id' => $client->id,
        ]);

        // Verify that bookings.client_id references clients.id (NOT users.id)
        $this->assertDatabaseHas('bookings', [
            'guest_email' => 'maria.santos@example.com',
            'client_id' => $client->id,
            'status' => 'pending',
        ]);

        // Verify Eloquent relationship resolution
        $freshTemp = $tempBooking->fresh();
        $this->assertNotNull($freshTemp->client);
        $this->assertSame($client->id, $freshTemp->client->id);
        $this->assertSame($client->email, $freshTemp->client->email);
    }

    /**
     * Requirement B: Authenticated payment submission sets payment_submitted
     * and does NOT prematurely confirm booking.
     */
    public function test_authenticated_payment_submission_sets_payment_submitted_status_without_premature_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $client = Client::create([
            'full_name' => $user->name,
            'email' => $user->email,
            'phone' => '09123456789',
            'address' => 'Test Address',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(30)->toDateString(),
            'venue' => 'Grand Ballroom',
            'status' => 'admin_approved',
            'total_quoted' => 10000.00,
            'final_quoted_price' => 10000.00,
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'total_amount' => 10000.00,
            'final_quoted_price' => 10000.00,
            'downpayment_percentage' => 50.0,
            'issued_by' => 1,
        ]);

        $response = $this->actingAs($user)->post(route('bookings.payment.reference', ['booking' => $booking->id]), [
            'reference_number' => 'REF-AUTH-123456',
            'payment_type' => 'gcash',
            'payment_option' => 'downpayment',
        ]);

        $response->assertSessionHas('success');

        $booking->refresh();
        $this->assertSame('payment_submitted', $booking->status);
        $this->assertNotSame('confirmed', $booking->status);

        $this->assertDatabaseHas('payments', [
            'booking_id' => $booking->id,
            'reference_number' => 'REF-AUTH-123456',
            'status' => 'pending',
            'amount' => 5000.00,
        ]);
    }

    /**
     * Requirement B & D: Guest payment submission sets payment_submitted
     * and redirects to guest.booking.analysis rather than a 404 route.
     */
    public function test_guest_payment_submission_sets_payment_submitted_and_redirects_to_guest_booking_analysis(): void
    {
        $issuer = User::factory()->create(['role' => 'admin']);
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Guest User',
            'guest_email' => 'guest@example.com',
            'guest_phone' => '09987654321',
            'guest_address' => 'Pasig City',
            'guest_access_token' => $guestToken,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(25)->toDateString(),
            'venue' => 'Garden Venue',
            'status' => 'admin_approved',
            'total_quoted' => 8000.00,
            'final_quoted_price' => 8000.00,
        ]);

        Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ACCEPTED,
            'total_amount' => 8000.00,
            'final_quoted_price' => 8000.00,
            'downpayment_percentage' => 50.0,
            'issued_by' => $issuer->id,
        ]);

        $response = $this->post(route('guest.bookings.payment.reference', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
            'reference_number' => 'REF-GUEST-789012',
            'payment_type' => 'gcash',
            'payment_option' => 'full_payment',
        ]);

        // Must redirect to guest.booking.analysis with the token with error (unclaimed guest blocked)
        $expectedRedirectUrl = route('guest.booking.analysis', ['token' => $guestToken]);
        $response->assertRedirect($expectedRedirectUrl);
        $response->assertSessionHas('error');

        $booking->refresh();
        $this->assertSame('admin_approved', $booking->status);
        $this->assertNotSame('confirmed', $booking->status);

        // Verify following the redirect yields 200 OK (NOT 404)
        $followResponse = $this->get($expectedRedirectUrl);
        $followResponse->assertStatus(200);
        $followResponse->assertViewIs('guest.booking-analysis');
    }

    /**
     * Requirement B: Submitted payments appear in admin notifications queue and dashboard counts.
     */
    public function test_submitted_payments_appear_in_admin_notifications_and_dashboard_pending_counts(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'email_verified_at' => now(),
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Alert Guest',
            'guest_email' => 'alert@example.com',
            'event_type' => 'corporate',
            'event_date' => now()->addDays(15)->toDateString(),
            'venue' => 'Makati Diamond Residences',
            'status' => 'payment_submitted',
            'total_quoted' => 20000.00,
        ]);

        // 1. Admin notifications view must contain this booking under Payment Review Required
        $response = $this->actingAs($admin)->get(route('admin.notifications'));
        $response->assertStatus(200);

        $alerts = $response->viewData('alerts');
        $paymentAlert = collect($alerts)->first(function ($alert) use ($booking) {
            return ($alert->booking_id ?? null) === $booking->id && ($alert->type ?? null) === 'payment_pending';
        });

        $this->assertNotNull($paymentAlert, 'Submitted payment must generate a notification alert for admin review.');
        $this->assertSame('Payment Review Required', $paymentAlert->title);

        // 2. Admin dashboard pending count must include payment_submitted
        $dashboardResponse = $this->actingAs($admin)->get(route('admin.dashboard'));
        $dashboardResponse->assertStatus(200);
        $pendingBookingsCount = $dashboardResponse->viewData('pendingBookings');
        $this->assertGreaterThanOrEqual(1, $pendingBookingsCount);
    }

    /**
     * Requirement B: Admin booking update validation accepts payment_submitted.
     */
    public function test_admin_booking_status_validation_accepts_payment_submitted_status(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'email_verified_at' => now(),
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Validation Guest',
            'guest_email' => 'valid@example.com',
            'event_type' => 'debut',
            'event_date' => now()->addDays(40)->toDateString(),
            'venue' => 'Oasis Manila',
            'status' => 'payment_submitted',
            'total_quoted' => 15000.00,
        ]);

        $response = $this->actingAs($admin)->put(route('admin.bookings.update', ['booking' => $booking->id]), [
            'event_type' => 'debut',
            'event_date' => now()->addDays(40)->toDateString(),
            'venue' => 'Oasis Manila',
            'status' => 'payment_submitted',
            'action' => 'save',
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertSame('payment_submitted', $booking->fresh()->status);
    }

    /**
     * Requirement B: Booking model status display labels for payment_submitted.
     */
    public function test_booking_model_display_labels_handle_payment_submitted(): void
    {
        $booking = new Booking(['status' => 'payment_submitted']);
        $this->assertSame('Payment Submitted', $booking->status_display_label);
        $this->assertSame('Payment Submitted', $booking->client_status_label);
    }

    /**
     * Requirement C: Secure evidence-file authorization.
     */
    public function test_secure_evidence_authorization_for_client_and_staff_roles(): void
    {
        Storage::fake('local');
        $filePath = 'evidence/test_damage.jpg';
        Storage::disk('local')->put($filePath, 'fake image binary content');

        // Create disparate IDs for owner User and Client
        $ownerUser = User::factory()->create(['role' => 'client', 'email' => 'owner@example.com', 'email_verified_at' => now()]);
        $unrelatedUser = User::factory()->create(['role' => 'client', 'email' => 'unrelated@example.com', 'email_verified_at' => now()]);
        $assignedStaff = User::factory()->create(['role' => 'staff', 'email' => 'staff.assigned@example.com', 'email_verified_at' => now()]);
        $unassignedStaff = User::factory()->create(['role' => 'staff', 'email' => 'staff.other@example.com', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'is_bootstrap' => false, 'email_verified_at' => now()]);

        // Pre-create clients so Client ID is distinct from User ID
        for ($i = 0; $i < 10; $i++) {
            Client::create([
                'full_name' => "Pad Client {$i}",
                'email' => "pad{$i}@example.com",
                'phone' => '09123456789',
                'address' => 'Address',
            ]);
        }
        $ownerClient = Client::create([
            'full_name' => 'Owner Client',
            'email' => 'owner@example.com',
            'phone' => '09171234567',
            'address' => 'Owner Address',
        ]);
        $this->assertNotEquals($ownerUser->id, $ownerClient->id);

        $booking = Booking::create([
            'client_id' => $ownerClient->id,
            'staff_id' => $assignedStaff->id,
            'event_type' => 'wedding',
            'event_date' => now()->subDays(2)->toDateString(),
            'venue' => 'San Agustin Church',
            'status' => 'pending_return',
        ]);

        $assetReturn = AssetReturn::create([
            'booking_id' => $booking->id,
            'return_date' => now()->subDay()->toDateString(),
            'status' => 'pending_inspection',
            'total_damage_charge' => 500.00,
            'inspected_by' => $assignedStaff->id,
        ]);

        $inventoryItem = \App\Models\InventoryItem::create([
            'name' => 'Fabric Arch',
            'item_code' => 'ITM-ARCH-01',
            'category' => 'fabric',
            'quantity' => 10,
            'unit' => 'pcs',
            'unit_cost' => 500.00,
            'unit_price' => 750.00,
            'replacement_cost' => 1000.00,
            'status' => 'available',
        ]);

        $returnItem = ReturnItem::create([
            'return_id' => $assetReturn->id,
            'inventory_item_id' => $inventoryItem->id,
            'condition' => 'damaged',
            'notes' => 'Torn fabric',
            'damage_charge' => 500.00,
        ]);

        $evidence = ReturnItemEvidence::create([
            'return_item_id' => $returnItem->id,
            'file_path' => $filePath,
            'file_name' => 'test_damage.jpg',
            'mime_type' => 'image/jpeg',
            'size' => 1024,
            'uploaded_by' => $assignedStaff->id,
        ]);

        $evidenceUrl = route('secure.evidence.show', ['id' => $evidence->id]);

        // 1. Owning Client (with different User ID and Client ID) must be granted access (200)
        $ownerResponse = $this->actingAs($ownerUser)->get($evidenceUrl);
        $ownerResponse->assertStatus(200);

        // 2. Unrelated Client must be denied (403)
        $unrelatedResponse = $this->actingAs($unrelatedUser)->get($evidenceUrl);
        $unrelatedResponse->assertStatus(403);

        // 3. Assigned Staff must be granted access (200)
        $assignedStaffResponse = $this->actingAs($assignedStaff)->get($evidenceUrl);
        $assignedStaffResponse->assertStatus(200);

        // 4. Unassigned Staff must be denied (403)
        $unassignedStaffResponse = $this->actingAs($unassignedStaff)->get($evidenceUrl);
        $unassignedStaffResponse->assertStatus(403);

        // 5. Admin must be granted access (200)
        $adminResponse = $this->actingAs($admin)->get($evidenceUrl);
        $adminResponse->assertStatus(200);

        // 6. Unauthenticated must be redirected to login (or 401 JSON)
        auth()->logout();
        $guestResponse = $this->get($evidenceUrl);
        $guestResponse->assertRedirect('/login');

        $guestJsonResponse = $this->getJson($evidenceUrl);
        $guestJsonResponse->assertStatus(401);
    }

    /**
     * Requirement D: Guest quotation acceptance redirects to guest.booking.analysis.
     */
    public function test_guest_quotation_acceptance_redirects_to_guest_booking_analysis(): void
    {
        $guestToken = (string) Str::uuid();
        $booking = Booking::create([
            'client_id' => null,
            'guest_name' => 'Acceptance Guest',
            'guest_email' => 'accept@example.com',
            'guest_access_token' => $guestToken,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(35)->toDateString(),
            'venue' => 'Tagaytay Highlands',
            'status' => 'quotation_sent',
            'total_quoted' => 12000.00,
        ]);

        $issuer = User::factory()->create(['role' => 'admin']);
        Quotation::create([
            'booking_id' => $booking->id,
            'version' => 1,
            'status' => Quotation::STATUS_ISSUED,
            'total_amount' => 12000.00,
            'final_quoted_price' => 12000.00,
            'valid_until' => now()->addDays(7),
            'issued_by' => $issuer->id,
        ]);

        $response = $this->post(route('guest.bookings.accept', ['booking' => $booking->id]), [
            'guest_token' => $guestToken,
        ]);

        $expectedRedirectUrl = route('guest.booking.analysis', ['token' => $guestToken]);
        $response->assertRedirect($expectedRedirectUrl);
        $response->assertSessionHas('error');

        $booking->refresh();
        $this->assertSame('quotation_sent', $booking->status);

        // Follow redirect to ensure no 404
        $followResponse = $this->get($expectedRedirectUrl);
        $followResponse->assertStatus(200);
    }
}
