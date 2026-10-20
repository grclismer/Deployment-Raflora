<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use App\Models\User;
use App\Models\Package;
use App\Models\PackageImage;
use App\Models\Gallery;
use App\Models\GalleryImage;

class GuestPublicExperienceCorrectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_hero_has_mobile_readability_styling()
    {
        $response = $this->get(route('home'));
        
        $response->assertStatus(200);
        // High contrast mobile overlay is present
        $response->assertSee('bg-white/85', false);
        // Heading text styling
        $response->assertSee('Creating Beautiful', false);
        $response->assertSee('text-[#0F2E58]', false);
        // Text paragraph has clear contrast
        $response->assertSee('text-slate-800', false);
    }

    public function test_landing_page_sections_ordered_matching_navbar()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        
        $content = $response->getContent();
        
        $heroPos = strpos($content, 'id="home-hero"');
        $galleryPos = strpos($content, 'id="gallery"');
        $packagesPos = strpos($content, 'id="packages-preview"');
        $aboutPos = strpos($content, 'id="about"');
        
        $this->assertNotFalse($heroPos, 'Hero section must exist');
        $this->assertNotFalse($galleryPos, 'Gallery section must exist');
        $this->assertNotFalse($packagesPos, 'Packages section must exist');
        $this->assertNotFalse($aboutPos, 'About section must exist');
        
        // Exact order matching navbar: HOME -> GALLERY -> PACKAGES -> ABOUT
        $this->assertTrue($heroPos < $galleryPos, 'Hero section must precede Gallery section');
        $this->assertTrue($galleryPos < $packagesPos, 'Gallery section must precede Packages preview section');
        $this->assertTrue($packagesPos < $aboutPos, 'Packages preview section must precede About section');
    }

    public function test_active_admin_package_with_images_relationship_appears_on_home_and_packages_page()
    {
        Storage::fake('public');

        $package = Package::create([
            'title' => 'Opulent Orchid Celebration',
            'description' => 'Lavish orchid floral styling for grand celebrations.',
            'category' => 'Wedding',
            'price' => 75000.00,
            'is_active' => true,
            'is_archived' => false,
            'included_items' => ['Bridal bouquet', '10 Table centerpieces', 'Floral arch'],
        ]);

        $imagePath = 'packages/orchid_photo_1.jpg';
        Storage::disk('public')->put($imagePath, 'fake-image-binary');

        PackageImage::create([
            'package_id' => $package->id,
            'image_path' => $imagePath,
            'is_primary' => true,
            'order' => 1,
        ]);

        // 1. Verify Home Page
        $homeResponse = $this->get(route('home'));
        $homeResponse->assertStatus(200);
        $homeResponse->assertSee('Opulent Orchid Celebration');
        $homeResponse->assertSee('75,000.00');
        // Primary image url should resolve to the storage url
        $homeResponse->assertSee('storage/' . $imagePath, false);
        // View Details button has embedded JSON with package data
        $homeResponse->assertSee('view-package-btn', false);
        $homeResponse->assertSee('data-package=', false);

        // 2. Verify Packages Index Page
        $packagesResponse = $this->get(route('packages.index'));
        $packagesResponse->assertStatus(200);
        $packagesResponse->assertSee('Opulent Orchid Celebration');
        $packagesResponse->assertSee('storage/' . $imagePath, false);
    }

    public function test_inactive_or_archived_package_does_not_appear_on_public_pages()
    {
        $activePackage = Package::create([
            'title' => 'Visible Active Package',
            'description' => 'Should be visible.',
            'category' => 'Debut',
            'price' => 20000.00,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $inactivePackage = Package::create([
            'title' => 'Hidden Inactive Package',
            'description' => 'Should be hidden.',
            'category' => 'Debut',
            'price' => 25000.00,
            'is_active' => false,
            'is_archived' => false,
        ]);

        $archivedPackage = Package::create([
            'title' => 'Hidden Archived Package',
            'description' => 'Should be hidden.',
            'category' => 'Debut',
            'price' => 30000.00,
            'is_active' => true,
            'is_archived' => true,
        ]);

        // Check Home Page
        $homeResponse = $this->get(route('home'));
        $homeResponse->assertStatus(200);
        $homeResponse->assertSee('Visible Active Package');
        $homeResponse->assertDontSee('Hidden Inactive Package');
        $homeResponse->assertDontSee('Hidden Archived Package');

        // Check Packages Index Page
        $packagesResponse = $this->get(route('packages.index'));
        $packagesResponse->assertStatus(200);
        $packagesResponse->assertSee('Visible Active Package');
        $packagesResponse->assertDontSee('Hidden Inactive Package');
        $packagesResponse->assertDontSee('Hidden Archived Package');
    }

    public function test_featured_gallery_connects_to_real_gallery_records()
    {
        Storage::fake('public');

        $activeGallery = Gallery::create([
            'title' => 'Grand Manila Ballroom Wedding',
            'event_date' => now()->subDays(5)->toDateString(),
            'event_type' => 'Wedding',
            'theme' => 'Grand Ballroom',
            'is_archived' => false,
        ]);

        GalleryImage::create([
            'gallery_id' => $activeGallery->id,
            'image_path' => 'galleries/ballroom_1.jpg',
        ]);

        $archivedGallery = Gallery::create([
            'title' => 'Archived Secret Event',
            'event_date' => now()->subDays(20)->toDateString(),
            'event_type' => 'Private',
            'theme' => 'Secret',
            'is_archived' => true,
        ]);

        GalleryImage::create([
            'gallery_id' => $archivedGallery->id,
            'image_path' => 'galleries/secret_1.jpg',
        ]);

        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('Grand Manila Ballroom Wedding');
        $response->assertDontSee('Archived Secret Event');
        // Lightbox trigger with real gallery ID
        $response->assertSee('openLightbox(\'' . $activeGallery->id . '\')', false);
    }

    public function test_gallery_page_does_not_contain_unsupported_claims_and_uses_data_driven_stats()
    {
        $gallery1 = Gallery::create([
            'title' => 'Event Showcase One',
            'event_date' => now()->subDays(2)->toDateString(),
            'event_type' => 'Wedding',
            'theme' => 'Classic',
            'is_archived' => false,
        ]);

        $gallery2 = Gallery::create([
            'title' => 'Event Showcase Two',
            'event_date' => now()->subDays(10)->toDateString(),
            'event_type' => 'Birthday',
            'theme' => 'Floral Bloom',
            'is_archived' => false,
        ]);

        $response = $this->get(route('gallery'));
        $response->assertStatus(200);

        // Unsupported claims must NOT exist
        $response->assertDontSee('150+ Event Galleries');
        $response->assertDontSee('100% Real Events');
        $response->assertDontSee('Trusted by Our Clients');

        // Data-driven stat shows exact count
        $response->assertSee('2');
        $response->assertSee('Event Galleries');
    }

    public function test_admin_gallery_store_prevents_rapid_duplicate_submission()
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => 'admin']);

        $image = UploadedFile::fake()->create('photo.jpg', 100, 'image/jpeg');

        $payload = [
            'title' => 'Emerald Gala Anniversary',
            'event_date' => now()->subDay()->toDateString(),
            'event_type' => 'Corporate',
            'theme' => 'Emerald Elegance',
            'images' => [$image],
        ];

        // First submission
        $res1 = $this->actingAs($admin)->post(route('admin.gallery.store'), $payload);
        $res1->assertRedirect(route('admin.gallery'));
        $this->assertDatabaseCount('galleries', 1);

        // Immediate rapid second submission (same payload within 15 seconds)
        $image2 = UploadedFile::fake()->create('photo_dup.jpg', 100, 'image/jpeg');
        $payload['images'] = [$image2];

        $res2 = $this->actingAs($admin)->post(route('admin.gallery.store'), $payload);
        $res2->assertRedirect(route('admin.gallery'));
        $res2->assertSessionHas('success');

        // Total count in database remains 1, no duplicate record created
        $this->assertDatabaseCount('galleries', 1);
    }

    public function test_guest_footer_has_improved_readability_and_preserves_branding()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);

        // High contrast overlay class
        $response->assertSee('bg-white/90', false);
        // Legible text class
        $response->assertSee('text-slate-700', false);
        // Branding and information preserved
        $response->assertSee('Raflora Enterprises');
        $response->assertSee('0919 008 9881');
        $response->assertSee('raflora18@gmail.com');
        $response->assertSee('Corumi, Masambong');
        $response->assertSee('© 2026 Raflora Enterprises. All Rights Reserved.');
    }
}
