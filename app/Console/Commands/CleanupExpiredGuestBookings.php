<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TemporaryGuestBooking;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class CleanupExpiredGuestBookings extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'guest-bookings:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Deletes expired temporary guest booking requests and their associated files.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $expiredBookings = TemporaryGuestBooking::where('expires_at', '<', Carbon::now())
            ->whereNull('claimed_at')
            ->get();

        $count = 0;
        foreach ($expiredBookings as $booking) {
            if ($booking->inspiration_image_path) {
                Storage::disk('local')->delete($booking->inspiration_image_path);
            }
            if (is_array($booking->analysis_data) && !empty($booking->analysis_data['images'])) {
                foreach ($booking->analysis_data['images'] as $img) {
                    if (!empty($img['image_path']) && $img['image_path'] !== $booking->inspiration_image_path) {
                        Storage::disk('local')->delete($img['image_path']);
                    }
                }
            }
            $booking->delete();
            $count++;
        }

        $this->info("Cleaned up {$count} expired temporary guest bookings.");

        // Cleanup orphaned temporary analysis images
        $orphanedCount = 0;
        $tempAnalysisDir = 'bookings/temp-analysis';
        
        if (Storage::disk('local')->exists($tempAnalysisDir)) {
            $files = Storage::disk('local')->files($tempAnalysisDir);
            $cutoff = Carbon::now()->subHours(24)->timestamp;

            foreach ($files as $file) {
                try {
                    $lastModified = Storage::disk('local')->lastModified($file);
                    if ($lastModified < $cutoff) {
                        Storage::disk('local')->delete($file);
                        $orphanedCount++;
                    }
                } catch (\Throwable $e) {
                    \Log::error("Failed to delete orphaned temporary image [{$file}]: " . $e->getMessage());
                }
            }
        }
        
        if ($orphanedCount > 0) {
            $this->info("Cleaned up {$orphanedCount} orphaned temporary analysis images.");
        }
    }
}
