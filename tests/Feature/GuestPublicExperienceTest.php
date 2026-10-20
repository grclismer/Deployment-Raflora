<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Booking;
use App\Models\Package;

class GuestPublicExperienceTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_start_redirects_guest_with_query_parameters()
    {
        $response = $this->get('/booking/start?package_id=5');
        
        $response->assertRedirect(route('guest.booking.create', ['package_id' => 5]));
    }

    public function test_booking_start_redirects_auth_user_with_query_parameters()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = $this->get('/booking/start?package_id=3');
        
        $response->assertRedirect(route('bookings.create', ['package_id' => 3]));
    }

    public function test_home_page_contains_desktop_navigation()
    {
        $response = $this->get(route('home'));
        
        $response->assertStatus(200);
        $response->assertSee('HOME');
        $response->assertSee('GALLERY');
        $response->assertSee('ABOUT');
        $response->assertSee('BOOKING');
        $response->assertSee('id="mobileMenuToggle"', false);
    }

    /** On the Home page, GALLERY/PACKAGES/ABOUT nav links must use anchor hrefs. */
    public function test_home_page_nav_gallery_link_targets_home_section()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('href="#gallery"', false);
    }

    public function test_home_page_nav_packages_link_targets_home_section()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('href="#packages-preview"', false);
    }

    public function test_home_page_nav_about_link_targets_home_section()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('href="#about"', false);
    }

    /** Home page must have the correct section IDs for anchor links to work. */
    public function test_home_page_has_gallery_section_id()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('id="gallery"', false);
    }

    public function test_home_page_has_packages_preview_section_id()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('id="packages-preview"', false);
    }

    public function test_home_page_has_about_section_id()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('id="about"', false);
    }

    /** On standalone pages, nav links must use route URLs, not anchors. */
    public function test_gallery_page_nav_gallery_link_targets_route()
    {
        $response = $this->get(route('gallery'));
        $response->assertStatus(200);
        $response->assertSee('href="' . route('gallery') . '"', false);
        $response->assertDontSee('href="#gallery"', false);
    }

    public function test_gallery_page_nav_packages_link_targets_route()
    {
        $response = $this->get(route('gallery'));
        $response->assertStatus(200);
        $response->assertSee('href="' . route('packages.index') . '"', false);
        $response->assertDontSee('href="#packages-preview"', false);
    }

    public function test_gallery_page_nav_about_link_targets_route()
    {
        $response = $this->get(route('gallery'));
        $response->assertStatus(200);
        $response->assertSee('href="' . route('about') . '"', false);
        $response->assertDontSee('href="#about"', false);
    }

    /** Packages index compact filter: search input, category select, sort select present. */
    public function test_packages_index_has_compact_filter_controls()
    {
        $response = $this->get(route('packages.index'));
        $response->assertStatus(200);
        $response->assertSee('id="packageSearch"', false);
        $response->assertSee('id="category"', false);
        $response->assertSee('id="sort"', false);
    }

    /** Shared modal must exist at body level (outside overflow-hidden containers). */
    public function test_packages_index_has_shared_modal_element()
    {
        $response = $this->get(route('packages.index'));
        $response->assertStatus(200);
        $response->assertSee('id="packageDetailModal"', false);
        $response->assertSee('id="packageModalTitle"', false);
        $response->assertSee('id="packageModalPrice"', false);
        $response->assertSee('id="packageModalBookBtn"', false);
    }

    /** Package cards must embed data for the modal JS function. */
    public function test_packages_index_cards_embed_package_data_for_modal()
    {
        Package::create([
            'title'       => 'Rose Bouquet Package',
            'description' => 'Beautiful roses',
            'category'    => 'Wedding',
            'price'       => 5000,
            'is_active'   => true,
        ]);

        $response = $this->get(route('packages.index'));
        $response->assertStatus(200);
        $response->assertSee('openPackageModal(', false);
        $response->assertSee('Rose Bouquet Package');
    }

    /** Search + category filter returns only matching results. */
    public function test_packages_search_with_category_filter()
    {
        Package::create([
            'title'     => 'Violet Wedding',
            'category'  => 'Wedding',
            'price'     => 3000,
            'is_active' => true,
        ]);
        Package::create([
            'title'     => 'Violet Birthday',
            'category'  => 'Birthday',
            'price'     => 2000,
            'is_active' => true,
        ]);

        $response = $this->get(route('packages.index', [
            'search'   => 'Violet',
            'category' => 'Wedding',
        ]));

        $response->assertStatus(200);
        $response->assertSee('Violet Wedding');
        $response->assertDontSee('Violet Birthday');
    }

    /** Packages pagination preserves search query state. */
    public function test_packages_pagination_preserves_query_string()
    {
        $response = $this->get(route('packages.index', [
            'search'   => 'rose',
            'category' => 'Wedding',
            'sort'     => 'price_desc',
        ]));

        $response->assertStatus(200);
        $response->assertSee('value="rose"', false);
    }

    /** Guest booking create page contains all Step 1 fields and the Next button. */
    public function test_guest_booking_create_contains_step1_fields_and_next_button()
    {
        $response = $this->get(route('guest.booking.create'));
        $response->assertStatus(200);
        $response->assertSee('id="guest-step-1"', false);
        $response->assertSee('name="guest_name"', false);
        $response->assertSee('name="guest_email"', false);
        $response->assertSee('name="guest_phone"', false);
        $response->assertSee('id="btn-next"', false);
    }

    /** Step 2 and Step 3 must be initially hidden. */
    public function test_guest_booking_create_step2_and_step3_initially_hidden()
    {
        $response = $this->get(route('guest.booking.create'));
        $response->assertStatus(200);
        $response->assertSee('id="guest-step-2"', false);
        $response->assertSee('id="guest-step-3"', false);
        // These steps start hidden
        $response->assertSee('class="space-y-6 hidden animate-fade-in"', false);
    }

    /** Navbar must have the mobile toggle button and panel for hamburger. */
    public function test_navbar_has_mobile_toggle_button()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('id="mobileMenuToggle"', false);
        $response->assertSee('id="mobileMenuPanel"', false);
    }

    /** Mobile panel must start hidden (style="display:none"). */
    public function test_navbar_mobile_panel_starts_hidden()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        // Panel uses inline style display:none so it never shows on desktop
        $response->assertSee('id="mobileMenuPanel"', false);
        $response->assertSee('style="display:none"', false);
    }

    public function test_guest_booking_create_contains_navbar()
    {
        $response = $this->get(route('guest.booking.create'));
        
        $response->assertStatus(200);
        $response->assertSee('HOME');
        $response->assertSee('GALLERY');
        $response->assertSee('ABOUT');
    }

    public function test_avatar_dropdown_contains_logout_form()
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        
        $response = $this->get(route('home'));
        
        $response->assertStatus(200);
        $response->assertSee('action="' . route('logout') . '"', false);
        $response->assertSee('Log Out');
    }

    public function test_guest_analysis_contains_account_conversion_cta()
    {
        $token = 'test_token';
        $booking = new \App\Models\TemporaryGuestBooking();
        $booking->event_type = 'wedding';
        $booking->event_date = now()->addDays(10)->toDateString();
        $booking->guest_name = 'Test Guest';
        $booking->guest_email = 'guest@example.com';
        $booking->guest_phone = '09171234567';
        $booking->guest_address = 'Test Address';
        $booking->booking_type = 'custom';
        $booking->venue = 'Manila Hotel';
        $booking->claim_token_hash = hash('sha256', $token);
        $booking->expires_at = now()->addHours(24);
        $booking->save();
        
        $response = $this->get(route('guest.bookings.show', ['token' => $token]));
        
        $response->assertStatus(200);
        $response->assertSee('Claim &amp; Manage Your Booking Request', false);
        $response->assertSee('Create Account');
    }
}
