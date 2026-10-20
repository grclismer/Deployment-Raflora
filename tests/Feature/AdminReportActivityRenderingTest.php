<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\AuditLog;
use App\Models\Booking;

class AdminReportActivityRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Create an admin user for access
        $this->admin = User::factory()->create(['role' => 'admin']);
        // Create a staff user to test forbidden access
        $this->staff = User::factory()->create(['role' => 'staff']);
    }

    public function test_report_access_is_authorized_for_admin_and_forbidden_for_staff()
    {
        $this->actingAs($this->staff)->get(route('admin.reports'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.reports'))->assertSuccessful();
    }

    public function test_renders_audit_details_with_string_message_in_array()
    {
        AuditLog::record($this->admin->id, 'test_action', ['message' => 'Profile updated successfully.']);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertSuccessful();
        $response->assertSee('Profile updated successfully.');
    }

    public function test_renders_audit_details_with_structured_data_without_message()
    {
        AuditLog::record($this->admin->id, 'test_action', [
            'status' => 'confirmed',
            'amount' => 5000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertSuccessful();
        $response->assertSee('Status: confirmed, Amount: 5000');
    }

    public function test_renders_audit_details_with_message_and_structured_data()
    {
        AuditLog::record($this->admin->id, 'test_action', [
            'message' => 'Inventory restocked',
            'item_id' => 12,
            'qty' => 50,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertSuccessful();
        $response->assertSee('Inventory restocked (Item id: 12, Qty: 50)', false);
    }

    public function test_renders_audit_details_with_nested_array()
    {
        AuditLog::record($this->admin->id, 'test_action', [
            'changes' => ['from' => 'pending', 'to' => 'confirmed']
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertSuccessful();
        $response->assertSee(e('Changes: {"from":"pending","to":"confirmed"}'), false);
    }

    public function test_renders_audit_details_with_null_or_empty_values()
    {
        // AuditLog record method wraps string details into array, 
        // to pass actual null details we need to use Eloquent create
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'test_action',
            'module' => 'test',
            'event_type' => 'test',
            'details' => null,
            'ip_address' => '127.0.0.1'
        ]);
        
        AuditLog::create([
            'user_id' => $this->admin->id,
            'action' => 'test_action',
            'module' => 'test',
            'event_type' => 'test',
            'details' => [],
            'ip_address' => '127.0.0.1'
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertSuccessful();
        $response->assertSee('No additional details');
    }

    public function test_renders_fallback_activity_from_bookings_if_audit_logs_empty()
    {
        // No audit logs created
        $booking = Booking::forceCreate([
            'client_id' => null,
            'guest_name' => 'John Doe',
            'guest_email' => 'john@example.com',
            'event_date' => now()->addDays(5)->format('Y-m-d'),
            'status' => 'confirmed',
            'total_quoted' => 1000,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.reports'));
        $response->assertSuccessful();
        $response->assertSee('Booking #' . $booking->id . ' is Confirmed');
    }
}
