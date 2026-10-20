<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaviconConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_rafloralogo_ico_file_exists_and_is_non_empty(): void
    {
        $path = public_path('rafloralogo.ico');
        $this->assertFileExists($path, 'public/rafloralogo.ico must exist.');
        $this->assertGreaterThan(0, filesize($path), 'public/rafloralogo.ico must not be empty.');
    }

    public function test_existing_system_logos_remain_present(): void
    {
        $existingLogos = [
            'assets/images/raflora-logo-nobackground.png',
            'assets/images/raflora-logo.jpg',
            'assets/images/raflora_flower_emblem_transparent.png',
            'assets/images/raflora_text_wordmark_transparent.png',
        ];

        foreach ($existingLogos as $logo) {
            $this->assertFileExists(public_path($logo), "Existing system logo {$logo} must remain present.");
            $this->assertGreaterThan(0, filesize(public_path($logo)), "Existing system logo {$logo} must not be empty.");
        }
    }

    public function test_public_and_client_portal_pages_render_rafloralogo_favicon(): void
    {
        $routes = ['/', '/gallery', '/packages', '/about', route('guest.booking.create'), '/login', '/register'];
        foreach ($routes as $route) {
            $response = $this->get($route);
            $response->assertOk();
            $response->assertSee('<link rel="icon" type="image/x-icon" href="' . asset('rafloralogo.ico') . '">', false);
            $this->assertStringContainsString('/rafloralogo.ico', $response->getContent(), "Route {$route} must contain favicon link.");
        }

        $client = User::factory()->create(['role' => 'client']);
        $clientResponse = $this->actingAs($client)->get(route('client.dashboard'));
        $clientResponse->assertOk();
        $clientResponse->assertSee('<link rel="icon" type="image/x-icon" href="' . asset('rafloralogo.ico') . '">', false);
    }

    public function test_admin_portal_pages_render_rafloralogo_favicon(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $response = $this->actingAs($admin)->get(route('admin.dashboard'));

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/x-icon" href="' . asset('rafloralogo.ico') . '">', false);
        $this->assertStringContainsString('/rafloralogo.ico', $response->getContent());
    }

    public function test_staff_portal_pages_render_rafloralogo_favicon(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $response = $this->actingAs($staff)->get(route('staff.dashboard'));

        $response->assertOk();
        $response->assertSee('<link rel="icon" type="image/x-icon" href="' . asset('rafloralogo.ico') . '">', false);
        $this->assertStringContainsString('/rafloralogo.ico', $response->getContent());
    }

    public function test_booking_shortage_report_renders_rafloralogo_favicon(): void
    {
        $booking = Booking::create([
            'event_type' => 'wedding',
            'event_date' => now()->addDays(20)->toDateString(),
            'event_time' => '14:00',
            'venue' => 'Grand Hall',
            'status' => 'confirmed',
            'total_quoted' => 10000,
        ]);

        $rendered = view('admin.booking-shortage-report', [
            'booking' => $booking,
            'event' => 'Wedding',
            'client' => 'Test Client',
            'eventDate' => 'Nov 1, 2026',
            'generatedAt' => 'Oct 20, 2026 4:00 PM',
            'preparedBy' => 'Admin User',
            'rows' => [],
            'counts' => ['total' => 0, 'canvass' => 0, 'procure' => 0],
        ])->render();

        $this->assertStringContainsString(
            '<link rel="icon" type="image/x-icon" href="' . asset('rafloralogo.ico') . '">',
            $rendered,
            'Booking shortage report must include the rafloralogo.ico favicon link.'
        );
        $this->assertStringContainsString('/rafloralogo.ico', $rendered);
    }
}
