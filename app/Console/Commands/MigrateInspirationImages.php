<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Booking;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class MigrateInspirationImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'raflora:migrate-inspiration {--dry-run : Only show what would be done without moving files}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate public inspiration images to secure local storage';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');
        
        $bookings = Booking::whereNotNull('inspiration_image')->get();
        $totalCount = $bookings->count();
        
        $this->info("Found {$totalCount} bookings with inspiration images.");

        $successCount = 0;
        $missingCount = 0;
        $failedCount = 0;
        $alreadyMigratedCount = 0;

        foreach ($bookings as $booking) {
            $path = $booking->inspiration_image;

            if (Storage::disk('local')->exists($path)) {
                $alreadyMigratedCount++;
                if (!$dryRun) {
                    $this->line("Already secure: {$path}");
                }
                continue;
            }

            if (!Storage::disk('public')->exists($path)) {
                $missingCount++;
                if (!$dryRun) {
                    $this->warn("Missing from public disk: {$path}");
                    Log::warning("Inspiration Image Migration: File missing for Booking ID {$booking->id} at path {$path}");
                }
                continue;
            }

            if ($dryRun) {
                $this->line("[DRY RUN] Would copy {$path} from public to local disk.");
                $successCount++;
                continue;
            }

            try {
                // Read from public, write to local
                $fileContents = Storage::disk('public')->get($path);
                
                // Ensure directory exists by putting the file
                Storage::disk('local')->put($path, $fileContents);
                
                $successCount++;
                $this->info("Successfully migrated: {$path}");
                Log::info("Inspiration Image Migration: Migrated {$path} for Booking ID {$booking->id}");
                
                // Note: We deliberately do NOT delete the original from public disk here.
                // A separate verification process should run before cleanup.
            } catch (\Throwable $e) {
                $failedCount++;
                $this->error("Failed to migrate {$path}: " . $e->getMessage());
                Log::error("Inspiration Image Migration: Failed for Booking ID {$booking->id}", ['error' => $e->getMessage()]);
            }
        }

        $this->newLine();
        $this->info('Migration Summary:');
        $this->info("Total: {$totalCount}");
        $this->info("Successfully Copied: {$successCount}");
        $this->info("Already Secure: {$alreadyMigratedCount}");
        $this->warn("Missing Files: {$missingCount}");
        
        if ($failedCount > 0) {
            $this->error("Failures: {$failedCount}");
        }

        if ($dryRun) {
            $this->info('This was a dry run. No files were actually moved.');
        } else {
            $this->info('NOTE: Original public files have NOT been deleted for safety. Once you verify the local copies, you may manually delete the public/bookings/inspiration-images directory.');
        }

        return $failedCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }
}
