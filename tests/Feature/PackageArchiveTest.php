<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageArchiveTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Ensure roles exist if your app uses them
        // This assumes your app has a way to make a user an admin.
    }

    private function getAdminUser()
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function getStaffUser()
    {
        return User::factory()->create(['role' => 'staff']);
    }

    public function test_admin_can_view_active_packages()
    {
        $admin = $this->getAdminUser();
        Package::create([
            'title' => 'Alpha Package Test',
            'price' => 100,
            'is_archived' => false
        ]);
        Package::create([
            'title' => 'Beta Package Test',
            'price' => 100,
            'is_archived' => true
        ]);

        $response = $this->actingAs($admin)->get(route('admin.packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Alpha Package Test');
        $response->assertDontSee('Beta Package Test');
    }

    public function test_admin_can_archive_package()
    {
        $admin = $this->getAdminUser();
        $package = Package::create([
            'title' => 'To be archived',
            'price' => 100,
            'is_archived' => false
        ]);

        $response = $this->actingAs($admin)->post(route('admin.packages.archive', $package));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Package archived successfully.');

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'is_archived' => true
        ]);
    }

    public function test_admin_can_view_archived_packages()
    {
        $admin = $this->getAdminUser();
        Package::create([
            'title' => 'Alpha Package Test',
            'price' => 100,
            'is_archived' => false
        ]);
        Package::create([
            'title' => 'Beta Package Test',
            'price' => 100,
            'is_archived' => true
        ]);

        $response = $this->actingAs($admin)->get(route('admin.packages.archived'));

        $response->assertStatus(200);
        $response->assertSee('Beta Package Test');
        $response->assertDontSee('Alpha Package Test');
    }

    public function test_admin_can_restore_package()
    {
        $admin = $this->getAdminUser();
        $package = Package::create([
            'title' => 'To be restored',
            'price' => 100,
            'is_archived' => true
        ]);

        $response = $this->actingAs($admin)->post(route('admin.packages.restore', $package));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Package restored successfully.');

        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'is_archived' => false
        ]);
    }

    public function test_archived_package_does_not_appear_in_guest_packages()
    {
        Package::create([
            'title' => 'Guest Active Package',
            'price' => 100,
            'is_active' => true,
            'is_archived' => false
        ]);
        Package::create([
            'title' => 'Guest Archived Package',
            'price' => 100,
            'is_active' => true,
            'is_archived' => true
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertStatus(200);
        $response->assertSee('Guest Active Package');
        $response->assertDontSee('Guest Archived Package');
    }

    public function test_non_admin_cannot_archive_or_restore_packages()
    {
        $staff = $this->getStaffUser();
        $package = Package::create([
            'title' => 'Test Package',
            'price' => 100,
            'is_archived' => false
        ]);

        $archiveResponse = $this->actingAs($staff)->post(route('admin.packages.archive', $package));
        $archiveResponse->assertStatus(403);

        $package->update(['is_archived' => true]);

        $restoreResponse = $this->actingAs($staff)->post(route('admin.packages.restore', $package));
        $restoreResponse->assertStatus(403);
    }
}
