<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Client;
use App\Models\Presentation;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\InventoryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhaseFiveProposalAndAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_upload_a_proposal_version_for_a_booking(): void
    {
        Storage::fake('public');

        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin-phase5@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(5)->toDateString(),
            'venue' => 'The Garden',
            'status' => 'pending',
            'total_quoted' => 1000,
        ]);

        $file = UploadedFile::fake()->create('proposal-v1.pdf', 200, 'application/pdf');

        $response = $this->actingAs($admin)->post(route('admin.bookings.presentations.store', $booking), [
            'proposal_file' => $file,
        ]);

        $response->assertRedirect();
        $booking->refresh();
        $this->assertSame(1, $booking->presentations()->count());
        $this->assertSame('v1', $booking->presentations()->first()->version);
    }

    public function test_client_can_submit_feedback_and_approval_for_a_proposal(): void
    {
        $clientUser = User::create([
            'name' => 'Client User',
            'email' => 'client-phase5@example.com',
            'password' => bcrypt('password123'),
            'role' => 'client',
            'email_verified_at' => now(),
        ]);

        $client = Client::create([
            'full_name' => 'Client User',
            'email' => $clientUser->email,
            'phone' => '09000000000',
            'address' => 'Test address',
        ]);

        $booking = Booking::create([
            'client_id' => $client->id,
            'event_type' => 'birthday',
            'event_date' => now()->addDays(8)->toDateString(),
            'venue' => 'The Loft',
            'status' => 'quotation_sent',
            'total_quoted' => 1500,
        ]);

        $presentation = Presentation::create([
            'booking_id' => $booking->id,
            'version' => 'v1',
            'file_path' => 'proposals/test.pdf',
            'file_name' => 'test.pdf',
            'status' => 'sent',
        ]);

        $response = $this->actingAs($clientUser)->post(route('bookings.proposals.feedback', ['booking' => $booking->id, 'presentation' => $presentation->id]), [
            'approval_status' => 'approved',
            'feedback_text' => 'Looks great.',
        ]);

        $response->assertRedirect();
        $presentation->refresh();
        $this->assertSame('approved', $presentation->approval_status);
        $this->assertSame('Looks great.', $presentation->feedback_text);
    }

    public function test_audit_logs_are_created_for_booking_inventory_and_user_changes(): void
    {
        $admin = User::create([
            'name' => 'Audit Admin',
            'email' => 'audit-admin@example.com',
            'password' => bcrypt('password123'),
            'role' => 'admin',
        ]);

        $booking = Booking::create([
            'client_id' => null,
            'event_type' => 'wedding',
            'event_date' => now()->addDays(4)->toDateString(),
            'venue' => 'The Garden',
            'status' => 'pending',
            'total_quoted' => 1000,
        ]);

        $booking->update([
            'status' => 'quotation_sent',
            'final_quoted_price' => 1200,
        ]);

        $inventoryItem = InventoryItem::create([
            'name' => 'Blue Orchids',
            'category' => 'flowers',
            'is_perishable' => true,
            'current_stock' => 4,
            'min_stock' => 1,
            'unit_cost' => 90,
            'unit' => 'stem',
        ]);

        $inventoryItem->update([
            'current_stock' => 2,
        ]);

        $admin->update([
            'role' => 'staff',
        ]);

        $this->assertTrue(AuditLog::where('module', 'booking')->where('action', 'status_changed')->exists());
        $this->assertTrue(AuditLog::where('module', 'booking')->where('action', 'quote_updated')->exists());
        $this->assertTrue(AuditLog::where('module', 'inventory')->where('action', 'inventory_updated')->exists());
        $this->assertTrue(AuditLog::where('module', 'user')->where('action', 'role_updated')->exists());
    }
}
