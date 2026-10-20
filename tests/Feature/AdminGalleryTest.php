<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Gallery;

class AdminGalleryTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_add_gallery()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $response = $this->actingAs($admin)->get(route('admin.gallery.create'));
        $response->assertStatus(200);
    }

    public function test_non_admin_cannot_access_add_gallery()
    {
        $guestResponse = $this->get(route('admin.gallery.create'));
        $guestResponse->assertRedirect(route('login'));

        $client = User::factory()->create(['role' => 'client']);
        $clientResponse = $this->actingAs($client)->get(route('admin.gallery.create'));
        $clientResponse->assertStatus(403);

        $staff = User::factory()->create(['role' => 'staff']);
        $staffResponse = $this->actingAs($staff)->get(route('admin.gallery.create'));
        $staffResponse->assertStatus(403);
    }

    public function test_admin_can_store_gallery_with_multiple_images()
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $image1 = UploadedFile::fake()->create('photo1.jpg', 100, 'image/jpeg');
        $image2 = UploadedFile::fake()->create('photo2.png', 100, 'image/png');

        $response = $this->actingAs($admin)->post(route('admin.gallery.store'), [
            'title' => 'Test Wedding',
            'event_date' => now()->subDay()->toDateString(),
            'event_type' => 'Wedding',
            'theme' => 'Rustic',
            'images' => [
                $image1,
                $image2
            ]
        ]);

        $response->assertRedirect(route('admin.gallery'));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('galleries', [
            'title' => 'Test Wedding',
            'event_type' => 'Wedding'
        ]);

        $gallery = Gallery::where('title', 'Test Wedding')->first();
        $this->assertCount(2, $gallery->images);

        foreach ($gallery->images as $img) {
            $this->assertStringStartsWith("galleries/{$gallery->id}/", $img->image_path);
            Storage::disk('public')->assertExists($img->image_path);
            $this->assertEquals(asset('storage/' . $img->image_path), $img->url);
        }

        // Test isolation: ensure no files were written to public/assets/images/galleries/
        foreach ($gallery->images as $img) {
            $legacyFile = public_path('assets/images/galleries/' . $gallery->id . '/' . basename($img->image_path));
            $this->assertFileDoesNotExist($legacyFile);
        }
    }

    public function test_admin_cannot_store_gallery_with_future_date()
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $image1 = UploadedFile::fake()->create('photo1.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($admin)->post(route('admin.gallery.store'), [
            'title' => 'Future Wedding',
            'event_date' => now()->addDay()->toDateString(),
            'event_type' => 'Wedding',
            'theme' => 'Rustic',
            'images' => [
                $image1
            ]
        ]);

        $response->assertSessionHasErrors(['event_date' => 'The event date cannot be in the future.']);
        $this->assertDatabaseMissing('galleries', [
            'title' => 'Future Wedding'
        ]);
    }

    public function test_gallery_appears_in_guest_gallery()
    {
        $gallery = Gallery::create([
            'title' => 'Guest Gallery Test',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Corporate',
            'theme' => 'Modern'
        ]);

        $gallery->images()->create([
            'image_path' => 'assets/images/galleries/test/photo.jpg'
        ]);

        $response = $this->get(route('gallery'));
        $response->assertStatus(200);
        $response->assertSee('Guest Gallery Test');
        $response->assertSee('Corporate');
        $response->assertSee('Modern');
    }

    public function test_gallery_with_storage_disk_images_appears_in_guest_gallery()
    {
        Storage::fake('public');

        $gallery = Gallery::create([
            'title' => 'Storage Gallery Test',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Anniversary',
            'theme' => 'Golden'
        ]);

        $imagePath = 'galleries/' . $gallery->id . '/sample.jpg';
        Storage::disk('public')->put($imagePath, 'fake-content');

        $gallery->images()->create([
            'image_path' => $imagePath,
        ]);

        $response = $this->get(route('gallery'));
        $response->assertStatus(200);
        $response->assertSee('Storage Gallery Test');
        $escapedStoragePath = trim(json_encode('storage/' . $imagePath), '"');
        $response->assertSee($escapedStoragePath, false);
    }

    public function test_admin_can_update_gallery_and_upload_additional_images()
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);
        
        $gallery = Gallery::create([
            'title' => 'Old Title',
            'event_date' => now()->subDays(5)->toDateString(),
            'event_type' => 'Old Type',
            'theme' => 'Old Theme'
        ]);

        $newImage = UploadedFile::fake()->create('added.jpg', 100, 'image/jpeg');

        $response = $this->actingAs($admin)->put(route('admin.gallery.update', $gallery), [
            'title' => 'New Title',
            'event_date' => now()->subDay()->toDateString(),
            'event_type' => 'New Type',
            'theme' => 'New Theme',
            'images' => [$newImage],
        ]);

        $response->assertRedirect(route('admin.gallery'));
        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'title' => 'New Title',
            'event_type' => 'New Type'
        ]);

        $this->assertCount(1, $gallery->fresh()->images);
        $stored = $gallery->fresh()->images->first();
        $this->assertStringStartsWith("galleries/{$gallery->id}/", $stored->image_path);
        Storage::disk('public')->assertExists($stored->image_path);
        $this->assertEquals(asset('storage/' . $stored->image_path), $stored->url);
    }

    public function test_gallery_archiving_preserves_images_on_disk()
    {
        Storage::fake('public');

        $admin = User::factory()->create(['role' => 'admin']);

        $gallery = Gallery::create([
            'title' => 'Archive Preserve Test',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Party',
            'theme' => 'Fun'
        ]);

        $imagePath = 'galleries/' . $gallery->id . '/preserve_me.jpg';
        Storage::disk('public')->put($imagePath, 'fake-content');
        $gallery->images()->create(['image_path' => $imagePath]);

        $response = $this->actingAs($admin)->delete(route('admin.gallery.destroy', $gallery));

        $response->assertRedirect(route('admin.gallery'));
        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'is_archived' => 1
        ]);

        // File must remain on disk after archiving
        Storage::disk('public')->assertExists($imagePath);
    }

    public function test_gallery_image_safe_deletion_removes_file_unless_referenced_elsewhere()
    {
        Storage::fake('public');

        $gallery = Gallery::create([
            'title' => 'Safe Deletion Test',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Party',
            'theme' => 'Fun'
        ]);

        // 1. Single reference file
        $singlePath = 'galleries/' . $gallery->id . '/single.jpg';
        Storage::disk('public')->put($singlePath, 'content');
        $singleImg = $gallery->images()->create(['image_path' => $singlePath]);

        $singleImg->delete();
        Storage::disk('public')->assertMissing($singlePath);

        // 2. Shared reference file
        $sharedPath = 'galleries/' . $gallery->id . '/shared.jpg';
        Storage::disk('public')->put($sharedPath, 'content');
        $img1 = $gallery->images()->create(['image_path' => $sharedPath]);
        $img2 = $gallery->images()->create(['image_path' => $sharedPath]);

        $img1->delete();
        // File must still exist because $img2 references it
        Storage::disk('public')->assertExists($sharedPath);

        $img2->delete();
        // Now no references exist, file should be deleted
        Storage::disk('public')->assertMissing($sharedPath);

        // 3. Legacy asset path is never deleted
        $legacyImg = $gallery->images()->create(['image_path' => 'assets/images/background.jpg']);
        $legacyImg->delete();
        $this->assertDatabaseMissing('gallery_images', ['id' => $legacyImg->id]);
    }

    public function test_gallery_image_url_accessor_handles_legacy_and_storage_paths()
    {
        $gallery = Gallery::create([
            'title' => 'Accessor Test',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Party',
            'theme' => 'Fun'
        ]);

        $legacyImg = $gallery->images()->create(['image_path' => 'assets/images/galleries/test.jpg']);
        $storageImg = $gallery->images()->create(['image_path' => 'galleries/99/test.jpg']);
        $emptyImg = new \App\Models\GalleryImage();

        $this->assertEquals(asset('assets/images/galleries/test.jpg'), $legacyImg->url);
        $this->assertEquals(asset('storage/galleries/99/test.jpg'), $storageImg->url);
        $this->assertEquals(asset('assets/images/background.jpg'), $emptyImg->url);
    }

    public function test_admin_can_archive_gallery()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $gallery = Gallery::create([
            'title' => 'To Be Archived',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Party',
            'theme' => 'Fun'
        ]);

        $response = $this->actingAs($admin)->delete(route('admin.gallery.destroy', $gallery));

        $response->assertRedirect(route('admin.gallery'));
        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'is_archived' => 1
        ]);
    }

    public function test_archived_gallery_does_not_appear_in_guest_gallery()
    {
        $gallery = Gallery::create([
            'title' => 'Archived Test',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Corporate',
            'theme' => 'Modern',
            'is_archived' => true
        ]);

        $response = $this->get(route('gallery'));
        $response->assertStatus(200);
        $response->assertDontSee('Archived Test');
    }

    public function test_non_admin_cannot_edit_or_archive_gallery()
    {
        $gallery = Gallery::create([
            'title' => 'Standard Gallery',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Wedding',
            'theme' => 'Rustic'
        ]);

        $client = User::factory()->create(['role' => 'client']);

        $editResponse = $this->actingAs($client)->get(route('admin.gallery.edit', $gallery));
        $editResponse->assertStatus(403);

        $updateResponse = $this->actingAs($client)->put(route('admin.gallery.update', $gallery), [
            'title' => 'Hacked Title',
            'event_date' => now()->subDay()->toDateString(),
            'event_type' => 'Hacked',
            'theme' => 'Hacked'
        ]);
        $updateResponse->assertStatus(403);

        $archiveResponse = $this->actingAs($client)->delete(route('admin.gallery.destroy', $gallery));
        $archiveResponse->assertStatus(403);
    }

    public function test_admin_can_restore_gallery()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $gallery = Gallery::create([
            'title' => 'To Be Restored',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Party',
            'theme' => 'Fun',
            'is_archived' => true
        ]);

        $response = $this->actingAs($admin)->post(route('admin.gallery.restore', $gallery));

        $response->assertRedirect(route('admin.gallery.archived'));
        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'is_archived' => 0
        ]);
    }

    public function test_admin_can_view_archived_galleries()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        
        $gallery = Gallery::create([
            'title' => 'Archived Secret',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Secret',
            'theme' => 'Secret',
            'is_archived' => true
        ]);

        $response = $this->actingAs($admin)->get(route('admin.gallery.archived'));
        $response->assertStatus(200);
        $response->assertSee('Archived Secret');
    }
}
