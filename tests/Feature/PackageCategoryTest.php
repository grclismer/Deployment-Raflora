<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use App\Services\TrustedDeviceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PackageCategoryTest extends TestCase
{
    use RefreshDatabase;

    private function getAdmin()
    {
        return User::factory()->create([
            'role' => 'admin',
            'email_verified_at' => now(),
        ]);
    }

    private function generateToken($user)
    {
        $service = new TrustedDeviceService();
        $rawToken = $service->generateToken();

        \App\Models\TrustedDevice::create([
            'user_id' => $user->id,
            'device_token_hash' => $service->hashToken($rawToken),
            'device_name' => 'Test',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'expires_at' => $service->trustExpiresAt(),
        ]);

        return $rawToken;
    }

    public function test_admin_can_create_package_with_category()
    {
        $admin = $this->getAdmin();
        $token = $this->generateToken($admin);

        $response = $this->actingAs($admin)->withCookies([TrustedDeviceService::COOKIE_NAME => $token])
            ->post(route('admin.packages.store'), [
                'title' => 'Test Package',
                'category' => 'Test Category',
                'price' => 1000,
                'is_active' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('packages', [
            'title' => 'Test Package',
            'category' => 'Test Category',
        ]);
    }

    public function test_admin_can_update_package_category()
    {
        $admin = $this->getAdmin();
        $token = $this->generateToken($admin);

        $package = Package::create([
            'title' => 'Old Package',
            'category' => 'Old Category',
            'price' => 1000,
            'is_active' => 1,
        ]);

        $response = $this->actingAs($admin)->withCookies([TrustedDeviceService::COOKIE_NAME => $token])
            ->put(route('admin.packages.update', $package), [
                'title' => 'Old Package',
                'category' => 'New Category',
                'price' => 1000,
                'is_active' => 1,
            ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('packages', [
            'id' => $package->id,
            'category' => 'New Category',
        ]);
    }

    public function test_guest_sees_dynamic_categories_on_packages_index()
    {
        Package::create([
            'title' => 'Pack 1',
            'category' => 'UniqueCat1',
            'price' => 100,
            'is_active' => 1,
        ]);

        Package::create([
            'title' => 'Pack 2',
            'category' => 'UniqueCat2',
            'price' => 200,
            'is_active' => 1,
        ]);

        Package::create([
            'title' => 'Pack 3',
            'category' => 'InactiveCat',
            'price' => 300,
            'is_active' => 0, // inactive should not be shown
        ]);

        $response = $this->get(route('packages.index'));

        $response->assertOk();
        $response->assertSee('UniqueCat1');
        $response->assertSee('UniqueCat2');
        $response->assertDontSee('InactiveCat');
    }

    public function test_guest_can_filter_packages_by_category()
    {
        $p1 = Package::create([
            'title' => 'Match Package',
            'category' => 'FilterCat',
            'price' => 100,
            'is_active' => 1,
        ]);

        $p2 = Package::create([
            'title' => 'Other Package',
            'category' => 'OtherCat',
            'price' => 200,
            'is_active' => 1,
        ]);

        $response = $this->get(route('packages.index', ['category' => 'FilterCat']));

        $response->assertOk();
        $response->assertSee('Match Package');
        $response->assertDontSee('Other Package');
    }
}
