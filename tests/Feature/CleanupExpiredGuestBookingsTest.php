<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use App\Models\TemporaryGuestBooking;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CleanupExpiredGuestBookingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_it_deletes_orphaned_temp_analysis_files_older_than_24_hours()
    {
        $stalePath = 'bookings/temp-analysis/stale_file.jpg';
        $recentPath = 'bookings/temp-analysis/recent_file.jpg';

        Storage::disk('local')->put($stalePath, 'dummy content');
        Storage::disk('local')->put($recentPath, 'dummy content');

        // We can't directly mock lastModified in a standard Storage fake without some tricks,
        // so we need to either touch the file using the filesystem, or mock the disk.
        // The easiest way is to use `touch()` on the full path if we use local disk,
        // but with Storage::fake() it's an array disk in some versions or local in others.
        // Let's get the absolute path for the fake disk and touch it.
        $staleFullPath = Storage::disk('local')->path($stalePath);
        $recentFullPath = Storage::disk('local')->path($recentPath);

        // Make the stale file 25 hours old
        touch($staleFullPath, Carbon::now()->subHours(25)->timestamp);
        // Make the recent file 1 hour old
        touch($recentFullPath, Carbon::now()->subHours(1)->timestamp);

        $this->artisan('guest-bookings:cleanup')->assertExitCode(0);

        Storage::disk('local')->assertMissing($stalePath);
        Storage::disk('local')->assertExists($recentPath);
    }

    public function test_it_handles_missing_temp_analysis_directory_gracefully()
    {
        // Directory does not exist
        $this->artisan('guest-bookings:cleanup')->assertExitCode(0);
    }

    public function test_it_still_cleans_up_expired_temporary_guest_bookings()
    {
        $expiredBooking = TemporaryGuestBooking::create([
            'guest_name' => 'Expired Guest',
            'guest_email' => 'expired@example.com',
            'guest_phone' => '09123456789',
            'guest_address' => 'Test Address',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'venue' => 'Venue',
            'expires_at' => Carbon::now()->subHours(25), // Expired
            'inspiration_image_path' => 'bookings/inspiration-images/expired.jpg',
            'claim_token_hash' => hash('sha256', Str::random(40)),
        ]);

        $activeBooking = TemporaryGuestBooking::create([
            'guest_name' => 'Active Guest',
            'guest_email' => 'active@example.com',
            'guest_phone' => '09123456789',
            'guest_address' => 'Test Address',
            'event_type' => 'wedding',
            'event_date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'venue' => 'Venue',
            'expires_at' => Carbon::now()->addHours(24), // Active
            'inspiration_image_path' => 'bookings/inspiration-images/active.jpg',
            'claim_token_hash' => hash('sha256', Str::random(40)),
        ]);

        Storage::disk('local')->put($expiredBooking->inspiration_image_path, 'dummy');
        Storage::disk('local')->put($activeBooking->inspiration_image_path, 'dummy');

        $this->artisan('guest-bookings:cleanup')->assertExitCode(0);

        $this->assertDatabaseMissing('temporary_guest_bookings', ['id' => $expiredBooking->id]);
        Storage::disk('local')->assertMissing($expiredBooking->inspiration_image_path);

        $this->assertDatabaseHas('temporary_guest_bookings', ['id' => $activeBooking->id]);
        Storage::disk('local')->assertExists($activeBooking->inspiration_image_path);
    }
}
