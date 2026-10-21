<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfilePhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private function createFakeImage(string $filename = 'profile.png'): UploadedFile
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return UploadedFile::fake()->createWithContent($filename, $png);
    }

    public function test_client_can_update_profile_photo_and_sees_initial_avatar_fallback_when_missing(): void
    {
        Storage::fake('public');
        $client = User::factory()->create([
            'role' => 'client',
            'name' => 'Jamie Client',
            'first_name' => 'Jamie',
            'last_name' => 'Client',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($client)
            ->get(route('account-settings'))
            ->assertOk()
            ->assertSee('data-profile-image-input')
            ->assertSee('data-profile-image-fallback')
            ->assertSee('Crop your profile photo');

        $this->actingAs($client)
            ->get(route('account-settings'))
            ->assertSee('data-mobile-avatar-nav', false)
            ->assertSee('data-account-menu-open', false)
            ->assertSee('data-mobile-account-menu class="hidden', false);

        preg_match('/<div id="mobileAccountMenu"[^>]*>(.*?)<\/div>/s', $this->get(route('account-settings'))->getContent(), $accountMenu);
        $accountMenuText = preg_replace('/\s+/', ' ', trim(strip_tags($accountMenu[1] ?? '')));
        $this->assertSame('ACCOUNT BOOK EVENT MY BOOKINGS DASHBOARD HISTORY Account Settings Log Out', $accountMenuText);

        preg_match('/<div data-desktop-avatar-actions[^>]*>(.*?)<\/div>/s', $this->get(route('account-settings'))->getContent(), $desktopAccountMenu);
        $desktopAccountMenuText = preg_replace('/\s+/', ' ', trim(strip_tags($desktopAccountMenu[1] ?? '')));
        $this->assertSame('DASHBOARD HISTORY Account Settings Log Out', $desktopAccountMenuText);
        $this->assertStringNotContainsString('My Bookings', $desktopAccountMenuText);

        $this->actingAs($client)->post(route('account-settings.update'), [
            'first_name' => 'Jamie',
            'last_name' => 'Client',
            'email' => $client->email,
            'profile_image' => $this->createFakeImage(),
        ])->assertRedirect(route('account-settings'));

        $client->refresh();
        $this->assertNotNull($client->profile_image);
        Storage::disk('public')->assertExists($client->profile_image);

        $this->actingAs($client)
            ->get(route('account-settings'))
            ->assertSee('data-profile-image-preview')
            ->assertSee('storage/' . $client->profile_image);
    }

    public function test_staff_can_upload_profile_photo_through_staff_only_settings(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create([
            'role' => 'staff',
            'name' => 'Taylor Staff',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($staff)
            ->get(route('staff.profile.edit'))
            ->assertOk()
            ->assertSee('Choose a photo to crop')
            ->assertSee(route('staff.profile.photo.update'));

        $this->actingAs($staff)->post(route('staff.profile.photo.update'), [
            'profile_image' => $this->createFakeImage('staff.png'),
        ])->assertRedirect(route('staff.profile.edit'));

        $staff->refresh();
        $this->assertNotNull($staff->profile_image);
        Storage::disk('public')->assertExists($staff->profile_image);

        $this->actingAs($staff)
            ->get(route('staff.dashboard'))
            ->assertSee('storage/' . $staff->profile_image);
    }

    public function test_staff_photo_settings_are_not_available_to_clients_or_admins(): void
    {
        $client = User::factory()->create(['role' => 'client', 'email_verified_at' => now()]);
        $admin = User::factory()->create(['role' => 'admin', 'email_verified_at' => now()]);

        $this->actingAs($client)->get(route('staff.profile.edit'))->assertForbidden();
        $this->actingAs($admin)->get(route('staff.profile.edit'))->assertForbidden();
    }

    public function test_staff_profile_photo_requires_a_supported_image(): void
    {
        Storage::fake('public');
        $staff = User::factory()->create(['role' => 'staff', 'email_verified_at' => now()]);

        $this->actingAs($staff)->post(route('staff.profile.photo.update'), [
            'profile_image' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertSessionHasErrors('profile_image');

        $this->assertNull($staff->fresh()->profile_image);
    }

    public function test_admin_profile_settings_use_the_shared_cropper_and_display_saved_photo(): void
    {
        Storage::fake('public');
        $path = 'profile-images/admin-test.jpg';
        Storage::disk('public')->put($path, 'profile image contents');
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_bootstrap' => false,
            'profile_image' => $path,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.settings'))
            ->assertOk()
            ->assertSee('data-profile-image-input')
            ->assertSee('Crop your profile photo')
            ->assertSee('storage/' . $path);
    }
}
