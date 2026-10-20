<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class P01G_GlobalSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_downpayment_percentage(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'role' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.settings.update'), [
                'downpayment_percentage' => 30.5
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertEquals(30.5, (float) Setting::getSetting('downpayment_percentage'));
    }

    public function test_non_admin_cannot_update_settings(): void
    {
        $staff = User::create([
            'name' => 'Staff',
            'email' => 'staff@test.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
        ]);

        $this->actingAs($staff)
            ->post(route('admin.settings.update'), [
                'downpayment_percentage' => 10.0
            ])
            ->assertForbidden();

        // 50.0 is the default seeded value
        $this->assertEquals(50.0, (float) Setting::getSetting('downpayment_percentage', 50.0));
    }
}
