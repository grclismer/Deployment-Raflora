<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingMessage;
use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Stabilization Plan — Phase 2: Auth and Ownership.
 */
class Phase2AuthAndOwnershipStabilizationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $staff;
    private User $clientUser;
    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->user('admin', 'p2-admin@example.com');
        $this->staff = $this->user('staff', 'p2-staff@example.com');
        $this->clientUser = $this->user('client', 'p2-client@example.com');
        $this->client = Client::create(['full_name' => 'Phase Two Client', 'email' => $this->clientUser->email, 'phone' => '09170000004']);
    }

    // ------------------------------------------------------------ Role boundaries

    public function test_admin_and_staff_cannot_use_the_client_portal(): void
    {
        $this->actingAs($this->admin)->get(route('client.dashboard'))->assertRedirect(route('admin.dashboard'));
        $this->actingAs($this->staff)->get(route('client.dashboard'))->assertRedirect(route('staff.dashboard'));
        $this->actingAs($this->staff)->getJson(route('bookings'))->assertForbidden();

        // No client record is silently created for operational accounts.
        $this->assertDatabaseMissing('clients', ['email' => $this->admin->email]);
        $this->assertDatabaseMissing('clients', ['email' => $this->staff->email]);
    }

    public function test_admin_cannot_change_email_through_client_account_settings(): void
    {
        $this->actingAs($this->admin)->post(route('account-settings.update'), [
            'first_name' => 'Admin',
            'last_name' => 'User',
            'email' => 'hijacked-admin@example.com',
        ])->assertRedirect(route('admin.dashboard'));

        $this->assertSame('p2-admin@example.com', $this->admin->fresh()->email);
    }

    public function test_client_role_can_still_use_the_client_portal(): void
    {
        $this->actingAs($this->clientUser)->get(route('client.dashboard'))->assertOk();
        $this->actingAs($this->clientUser)->get(route('bookings'))->assertOk();
    }

    // ------------------------------------------------------------ Account ↔ client record linkage

    public function test_client_email_change_keeps_bookings_and_requires_reverification(): void
    {
        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Linked Venue',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($this->clientUser)->post(route('account-settings.update'), [
            'first_name' => 'Phase',
            'last_name' => 'Two',
            'email' => 'p2-client-new@example.com',
        ]);

        $response->assertRedirect(route('verification.notice'));
        $user = $this->clientUser->fresh();
        $this->assertSame('p2-client-new@example.com', $user->email);
        $this->assertNull($user->email_verified_at, 'A changed email must be verified again.');
        $this->assertSame('p2-client-new@example.com', $this->client->fresh()->email, 'The client record must follow the account email.');
        $this->assertSame(1, Client::count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'client_email_changed', 'user_id' => $user->id]);

        // Portal access waits for verification of the new address.
        $this->actingAs($user)->get(route('bookings.show', $booking))->assertRedirect(route('verification.notice'));

        // After verification the same bookings are still owned by the account.
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($user->fresh())->get(route('bookings.show', $booking))->assertOk();
    }

    public function test_client_cannot_take_over_another_client_record_by_changing_email(): void
    {
        $victim = Client::create(['full_name' => 'Unlinked Client', 'email' => 'victim-client@example.com']);
        Booking::create([
            'client_id' => $victim->id,
            'event_type' => 'debut',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Victim Venue',
            'status' => 'pending',
        ]);

        $this->actingAs($this->clientUser)->post(route('account-settings.update'), [
            'first_name' => 'Phase',
            'last_name' => 'Two',
            'email' => 'victim-client@example.com',
        ])->assertSessionHasErrors('email');

        $this->assertSame('p2-client@example.com', $this->clientUser->fresh()->email);
        $this->assertNotNull($this->clientUser->fresh()->email_verified_at);
        $this->assertSame('p2-client@example.com', $this->client->fresh()->email);
    }

    public function test_profile_update_without_email_change_keeps_verification(): void
    {
        $this->actingAs($this->clientUser)->post(route('account-settings.update'), [
            'first_name' => 'Renamed',
            'last_name' => 'Client',
            'email' => 'p2-client@example.com',
        ])->assertRedirect(route('account-settings'));

        $this->assertNotNull($this->clientUser->fresh()->email_verified_at);
        $this->assertSame('Renamed Client', $this->clientUser->fresh()->name);
    }

    // ------------------------------------------------------------ Secure files and guest tokens

    public function test_empty_guest_token_does_not_unlock_client_booking_files(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bookings/inspiration-images/private.jpg', 'private-image');
        Storage::disk('local')->put('booking-attachments/proof.pdf', 'payment-proof');

        $booking = Booking::create([
            'client_id' => $this->client->id,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Private Venue',
            'status' => 'payment_submitted',
            'inspiration_image' => 'bookings/inspiration-images/private.jpg',
        ]);
        $this->assertNull($booking->guest_access_token);
        $message = BookingMessage::create([
            'booking_id' => $booking->id,
            'sender_type' => 'client',
            'sender_id' => $this->client->id,
            'message' => 'Payment proof attached',
            'visibility' => 'client_admin',
            'attachment_path' => 'booking-attachments/proof.pdf',
            'attachment_name' => 'proof.pdf',
            'attachment_category' => 'payment_proof',
            'mime_type' => 'application/pdf',
        ]);

        foreach (['?guest_token=', '?guest_token', ''] as $query) {
            $this->get('/secure-inspiration/' . $booking->id . $query)->assertForbidden();
            $this->get('/secure-attachment/' . $message->id . $query)->assertForbidden();
        }

        // The owner still has access.
        $this->actingAs($this->clientUser)->get(route('secure.inspiration.show', $booking->id))->assertOk();
    }

    public function test_guest_token_only_unlocks_files_for_its_unclaimed_booking(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('bookings/inspiration-images/guest.jpg', 'guest-image');
        $token = (string) Str::uuid();

        $booking = Booking::create([
            'guest_name' => 'Guest',
            'guest_email' => 'p2-guest@example.com',
            'guest_access_token' => $token,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(20)->toDateString(),
            'venue' => 'Guest Venue',
            'status' => 'pending',
            'inspiration_image' => 'bookings/inspiration-images/guest.jpg',
        ]);

        $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => $token]))->assertOk();
        $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => strtoupper($token)]))->assertForbidden();

        // Once claimed, the booking is only reachable through the owning client account.
        $booking->update(['client_id' => $this->client->id]);
        $this->get(route('secure.inspiration.show', ['bookingId' => $booking->id, 'guest_token' => $token]))->assertForbidden();
    }

    // ------------------------------------------------------------ Accounts, mock routes, AI abuse

    public function test_operations_panel_can_create_staff_but_not_a_second_admin(): void
    {
        $this->actingAs($this->admin)->post(route('admin.account.accounts.store'), [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'role' => 'admin',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('role', null, 'createAccount');
        $this->assertDatabaseMissing('users', ['email' => 'second-admin@example.com']);
        $this->assertSame(1, User::where('role', 'admin')->count());

        $this->actingAs($this->admin)->post(route('admin.account.accounts.store'), [
            'name' => 'New Staff',
            'email' => 'new-staff@example.com',
            'role' => 'staff',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHas('success');
        $this->assertDatabaseHas('users', ['email' => 'new-staff@example.com', 'role' => 'staff']);

        $this->actingAs($this->staff)->post(route('admin.account.accounts.store'), [
            'name' => 'Sneaky Staff',
            'email' => 'sneaky@example.com',
            'role' => 'staff',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertForbidden();
    }

    public function test_mock_ui_routes_are_not_exposed_outside_local(): void
    {
        $this->assertFalse(app()->environment('local'));
        $this->get('/mock')->assertNotFound();
        $this->get('/mock/admin/pending')->assertNotFound();
    }

    public function test_public_image_validation_endpoint_is_rate_limited(): void
    {
        Http::fake();

        for ($i = 0; $i < 5; $i++) {
            $status = $this->post(route('bookings.validate-image'), [
                'inspiration_image' => UploadedFile::fake()->create('flowers.jpg', 10, 'image/jpeg'),
            ])->getStatusCode();
            $this->assertNotSame(429, $status);
        }

        $this->post(route('bookings.validate-image'), [
            'inspiration_image' => UploadedFile::fake()->create('flowers.jpg', 10, 'image/jpeg'),
        ])->assertStatus(429);
    }

    private function user(string $role, string $email): User
    {
        return User::create([
            'name' => ucfirst($role) . ' Phase Two',
            'first_name' => ucfirst($role),
            'last_name' => 'Phase Two',
            'email' => $email,
            'password' => bcrypt('password123'),
            'role' => $role,
            'email_verified_at' => now(),
        ]);
    }
}
