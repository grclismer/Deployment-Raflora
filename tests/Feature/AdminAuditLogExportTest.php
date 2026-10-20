<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Audit Trail dialog in Account Settings exports the whole audit log as a CSV file.
 */
class AdminAuditLogExportTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_exports_the_whole_audit_log_as_csv(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'name' => 'Raflora Admin']);
        AuditLog::create(['user_id' => $admin->id, 'action' => 'inventory_updated', 'module' => 'inventory', 'details' => 'Inventory stock updated to 15.00', 'ip_address' => '127.0.0.1']);
        AuditLog::create(['user_id' => null, 'action' => 'status_changed', 'module' => 'bookings', 'details' => '=HYPERLINK("x")']);

        $this->actingAs($admin)->get(route('admin.settings'))
            ->assertOk()
            ->assertSee(route('admin.settings.audit-log.export'), false)
            ->assertSee('Export CSV');

        $response = $this->actingAs($admin)->get(route('admin.settings.audit-log.export'));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('raflora_audit_log_', $response->headers->get('Content-Disposition'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Time,User,Role,Action,Module,Details,"IP Address"', $csv);
        $this->assertStringContainsString('"Raflora Admin",Admin,"Inventory Updated",inventory,"Inventory stock updated to 15.00",127.0.0.1', $csv);
        $this->assertStringContainsString("System,,\"Status Changed\",bookings,\"'=HYPERLINK(\"\"x\"\")\"", $csv, 'Formula-like details are neutralised.');
    }

    public function test_staff_cannot_export_the_audit_log(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $response = $this->actingAs($staff)->get(route('admin.settings.audit-log.export'));

        $this->assertNotSame(200, $response->getStatusCode());
    }
}
