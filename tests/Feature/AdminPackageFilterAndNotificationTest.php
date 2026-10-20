<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPackageFilterAndNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_packages_index_renders_search_and_filter_toolbar(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index'));

        $response->assertOk();
        $response->assertSee('packageSearchInput', false);
        $response->assertSee('Search package name, category, or keyword...');
        $response->assertSee('All Categories');
        $response->assertSee('Latest First');
        $response->assertSee('Oldest First');
        $response->assertSee('Name (A-Z)');
        $response->assertSee('Price (Low to High)');
        $response->assertSee('Search');
    }

    public function test_admin_packages_search_filters_by_keyword(): void
    {
        Package::create([
            'package_code' => 'PKG-WED-01',
            'title' => 'Luxury Golden Wedding',
            'category' => 'Wedding',
            'description' => 'A grand golden wedding package.',
            'price' => 25000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        Package::create([
            'package_code' => 'PKG-CORP-01',
            'title' => 'Minimalist Corporate Gala',
            'category' => 'Corporate Event',
            'description' => 'A sleek corporate gala bundle.',
            'price' => 15000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        // Search for "Golden"
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['search' => 'Golden']));
        $response->assertOk();
        $response->assertSee('Luxury Golden Wedding');
        $response->assertDontSee('Minimalist Corporate Gala');

        // Search for "Corporate"
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['search' => 'Corporate']));
        $response->assertOk();
        $response->assertSee('Minimalist Corporate Gala');
        $response->assertDontSee('Luxury Golden Wedding');
    }

    public function test_admin_packages_category_filter(): void
    {
        Package::create([
            'package_code' => 'PKG-CAT-01',
            'title' => 'Spring Wedding Bundle',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        Package::create([
            'package_code' => 'PKG-CAT-02',
            'title' => 'Sweet 16 Birthday',
            'category' => 'Birthday',
            'price' => 10000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        // Filter Wedding
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['category' => 'Wedding']));
        $response->assertOk();
        $response->assertSee('Spring Wedding Bundle');
        $response->assertDontSee('Sweet 16 Birthday');

        // Filter Birthday
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['category' => 'Birthday']));
        $response->assertOk();
        $response->assertSee('Sweet 16 Birthday');
        $response->assertDontSee('Spring Wedding Bundle');
    }

    public function test_admin_packages_search_matches_description_and_included_items(): void
    {
        Package::create([
            'package_code' => 'PKG-DESC-01',
            'title' => 'Enchanted Forest',
            'category' => 'Wedding',
            'description' => 'Features hand-crafted floral arches and fairy lights',
            'included_items' => '10 Table centerpieces, 1 Floral Archway',
            'price' => 30000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['search' => 'Archway']));
        $response->assertOk();
        $response->assertSee('Enchanted Forest');

        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['search' => 'fairy lights']));
        $response->assertOk();
        $response->assertSee('Enchanted Forest');
    }

    public function test_admin_packages_sorting_behavior(): void
    {
        $cheap = Package::create([
            'package_code' => 'PKG-SORT-01',
            'title' => 'Basic Package A',
            'category' => 'General',
            'price' => 5000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        $expensive = Package::create([
            'package_code' => 'PKG-SORT-02',
            'title' => 'Premium Package Z',
            'category' => 'General',
            'price' => 50000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        // Sort price_asc
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['sort' => 'price_asc']));
        $response->assertOk();
        $content = $response->getContent();
        $posCheap = strpos($content, 'Basic Package A');
        $posExpensive = strpos($content, 'Premium Package Z');
        $this->assertTrue($posCheap < $posExpensive, 'Basic Package should appear before Premium Package on price_asc');

        // Sort price_desc
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['sort' => 'price_desc']));
        $response->assertOk();
        $content = $response->getContent();
        $posCheap = strpos($content, 'Basic Package A');
        $posExpensive = strpos($content, 'Premium Package Z');
        $this->assertTrue($posExpensive < $posCheap, 'Premium Package should appear before Basic Package on price_desc');
    }

    public function test_archived_packages_not_shown_in_active_filter(): void
    {
        Package::create([
            'package_code' => 'PKG-ACT-01',
            'title' => 'Active Wedding Package',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
            'is_archived' => false,
        ]);

        Package::create([
            'package_code' => 'PKG-ARC-01',
            'title' => 'Archived Wedding Package',
            'category' => 'Wedding',
            'price' => 20000,
            'is_active' => true,
            'is_archived' => true,
        ]);

        // Query active packages
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index', ['search' => 'Wedding']));
        $response->assertOk();
        $response->assertSee('Active Wedding Package');
        $response->assertDontSee('Archived Wedding Package');

        // Query archived packages
        $responseArchived = $this->actingAs($this->admin)->get(route('admin.packages.archived', ['search' => 'Wedding']));
        $responseArchived->assertOk();
        $responseArchived->assertSee('Archived Wedding Package');
        $responseArchived->assertDontSee('Active Wedding Package');
    }

    public function test_multiple_notifications_render_in_toast_container_with_independent_dismissal(): void
    {
        $response = $this->actingAs($this->admin)
            ->withSession(['error' => 'Primary error message occurred.'])
            ->withSession(['errors' => (new \Illuminate\Support\ViewErrorBag)->put('default', new \Illuminate\Support\MessageBag(['csv_file' => 'Row 3 format error.']))])
            ->get(route('admin.packages.index'));

        $response->assertOk();
        // Toast container exists
        $response->assertSee('id="rf-toast-container"', false);
        $response->assertSee('Primary error message occurred.');
        $response->assertSee('Row 3 format error.');

        // Verify click-to-dismiss and accessibility attributes
        $response->assertSee('rf-toast-alert', false);
        $response->assertSee('cursor-pointer', false);
        $response->assertSee('pointer-events-auto', false);
        $response->assertSee('title="Click to dismiss"', false);
        $response->assertSee('dismissRfToast', false);
        $response->assertSee('12000', false);

        // Verify X button was removed from toast notifications
        $response->assertDontSee('aria-label="Dismiss alert"', false);

        // Verify toast has solid opaque surface styling (zero bleed-through)
        $response->assertSee('opacity: 1 !important', false);
        $response->assertSee('isolation: isolate', false);
        $response->assertSee('background-color: #fff1f2', false);
        $response->assertSee('backdrop-filter: none !important', false);
        $response->assertSee('mix-blend-mode: normal !important', false);
    }

    public function test_admin_package_and_gallery_layouts_follow_inventory_placement_pattern(): void
    {
        // 1. Package Management layout checks
        $packageResponse = $this->actingAs($this->admin)->get(route('admin.packages.index'));
        $packageResponse->assertOk();
        $packageContent = $packageResponse->getContent();

        // Archived Packages in heading area
        $this->assertStringContainsString('Archived Packages', $packageContent);
        $this->assertStringContainsString(route('admin.packages.archived'), $packageContent);

        // Package Tools (outlined action) and Add Package (green primary action) on toolbar
        $this->assertStringContainsString('packageToolsButton', $packageContent);
        $this->assertStringContainsString('border-brand-', $packageContent);
        $this->assertStringContainsString('Add Package', $packageContent);
        $this->assertStringContainsString('bg-brand-700', $packageContent);
        $this->assertStringContainsString(route('admin.packages.create'), $packageContent);

        // 2. Gallery Management layout checks
        $galleryResponse = $this->actingAs($this->admin)->get(route('admin.gallery'));
        $galleryResponse->assertOk();
        $galleryContent = $galleryResponse->getContent();

        // Archived Galleries in heading area
        $this->assertStringContainsString('Archived Galleries', $galleryContent);
        $this->assertStringContainsString(route('admin.gallery.archived'), $galleryContent);

        // Add Gallery on right side of toolbar
        $this->assertStringContainsString('Add Gallery', $galleryContent);
        $this->assertStringContainsString(route('admin.gallery.create'), $galleryContent);

        // No fabricated Gallery Tools
        $this->assertStringNotContainsString('Gallery Tools', $galleryContent);
    }

    public function test_admin_packages_toolbar_has_show_filters_toggle_and_preserves_package_tabs(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.packages.index'));
        $response->assertOk();
        $content = $response->getContent();

        // 1. Primary visible toolbar controls
        $this->assertStringContainsString('id="packageSearchInput"', $content);
        $this->assertStringContainsString('id="packageToggleFiltersBtn"', $content);
        $this->assertStringContainsString('Show Filters', $content);

        // 2. Filter panel exists with Category, Sort, and Reset
        $this->assertStringContainsString('id="packageFilterPanel"', $content);
        $this->assertStringContainsString('id="packageCategoryFilter"', $content);
        $this->assertStringContainsString('id="packageSortFilter"', $content);
        $this->assertStringContainsString('Reset Filters', $content);

        // 3. Package tabs MUST preserve both Active Packages and Archived Packages
        $this->assertMatchesRegularExpression('/<nav[^>]*aria-label="Tabs"[^>]*>.*?Active Packages.*?Archived Packages.*?<\/nav>/s', $content);
    }

    public function test_admin_gallery_toolbar_has_show_filters_toggle_and_deduplicates_archived_tab(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin.gallery'));
        $response->assertOk();
        $content = $response->getContent();

        // 1. Primary visible toolbar controls
        $this->assertStringContainsString('id="galleryToggleFiltersBtn"', $content);
        $this->assertStringContainsString('Show Filters', $content);

        // 2. Filter panel exists with Event Type, Theme, Year, Sort, and Reset
        $this->assertStringContainsString('id="galleryFilterPanel"', $content);
        $this->assertStringContainsString('name="event_type"', $content);
        $this->assertStringContainsString('name="theme"', $content);
        $this->assertStringContainsString('name="year"', $content);
        $this->assertStringContainsString('name="sort"', $content);
        $this->assertStringContainsString('Reset Filters', $content);

        // 3. Header action MUST contain Archived Galleries
        $this->assertStringContainsString(route('admin.gallery.archived'), $content);

        // 4. Tab nav MUST only have Active Galleries (duplicate Archived Galleries tab removed)
        preg_match('/<nav[^>]*aria-label="Tabs"[^>]*>(.*?)<\/nav>/s', $content, $matches);
        $this->assertNotEmpty($matches, 'Gallery tab navigation must exist');
        $tabNavContent = $matches[1];
        $this->assertStringContainsString('Active Galleries', $tabNavContent);
        $this->assertStringNotContainsString('Archived Galleries', $tabNavContent);
    }
}

