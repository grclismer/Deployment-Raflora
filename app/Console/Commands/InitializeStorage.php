<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class InitializeStorage extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:initialize {--force : Force re-creation of symlink if stale or broken}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initializes persistent public and private storage directories and validates the public storage symlink for container hosting.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting Raflora storage initialization...');

        // 1. Initialize persistent storage directories
        $persistentDirectories = [
            'public disk root' => storage_path('app/public'),
            'private disk root' => storage_path('app/private'),
            'framework views' => storage_path('framework/views'),
            'framework sessions' => storage_path('framework/sessions'),
            'framework cache' => storage_path('framework/cache'),
            'framework cache data' => storage_path('framework/cache/data'),
            'logs' => storage_path('logs'),
        ];

        foreach ($persistentDirectories as $label => $path) {
            if (!File::exists($path)) {
                $created = File::makeDirectory($path, 0775, true, true);
                if (!$created && !File::exists($path)) {
                    $this->error("Failed to create {$label} directory at [{$path}].");
                    return self::FAILURE;
                }
                $this->info("Created {$label} directory: {$path}");
            } else {
                $this->line("Existing {$label} directory verified: {$path}");
            }
        }

        // 2. Validate and initialize public storage symbolic link
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        $isLink = $this->isSymlinkOrJunction($linkPath);
        $pathExists = file_exists($linkPath) || is_dir($linkPath) || $isLink;

        if ($pathExists) {
            if ($isLink) {
                $realLink = realpath($linkPath);
                $realTarget = realpath($targetPath);

                $matches = ($realLink && $realTarget && strcasecmp(
                    rtrim(str_replace('\\', '/', $realLink), '/'),
                    rtrim(str_replace('\\', '/', $realTarget), '/')
                ) === 0);

                if ($matches && !$this->option('force')) {
                    $this->info("Public storage symlink is valid and points to [{$targetPath}].");
                } else {
                    $this->warn("Public storage symlink exists but is stale, invalid, or --force requested.");
                    $this->info('Re-creating symlink with --force...');
                    $exitCode = Artisan::call('storage:link', ['--force' => true]);
                    if ($exitCode !== 0) {
                        $this->error('Failed to re-create storage symlink.');
                        return self::FAILURE;
                    }
                    $this->info('Storage symlink successfully repaired.');
                }
            } else {
                // public/storage exists as a regular file or non-link directory!
                $this->error("CRITICAL: [{$linkPath}] exists as a regular file or directory, not a symbolic link.");
                $this->error('Aborting initialization to prevent silent overwrite or data loss. Please inspect public/storage manually.');
                return self::FAILURE;
            }
        } else {
            $this->info("Creating public storage symlink [{$linkPath} -> {$targetPath}]...");
            $exitCode = Artisan::call('storage:link', []);
            if ($exitCode !== 0) {
                $this->error('Failed to create storage symlink.');
                return self::FAILURE;
            }
            $this->info('Storage symlink successfully created.');
        }

        // 3. Verify private return evidence storage remains outside public access
        $privatePath = storage_path('app/private');
        $realLink = realpath($linkPath);
        $realPrivate = realpath($privatePath);

        if ($realLink && $realPrivate) {
            $normLink = rtrim(str_replace('\\', '/', $realLink), '/') . '/';
            $normPrivate = rtrim(str_replace('\\', '/', $realPrivate), '/') . '/';
            if (str_starts_with($normPrivate, $normLink)) {
                $this->error('CRITICAL: Private storage directory is exposed within the public storage symlink!');
                return self::FAILURE;
            }
        }

        $this->info('Raflora persistent storage initialization completed successfully.');
        return self::SUCCESS;
    }

    /**
     * Determine if the given path is a symbolic link or an NTFS junction point.
     */
    protected function isSymlinkOrJunction(string $path): bool
    {
        if (is_link($path)) {
            return true;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            // Check readlink reparse point detection
            $rawTarget = @readlink($path);
            if ($rawTarget !== false && $rawTarget !== null) {
                $normalizedTarget = rtrim(str_replace('/', '\\', $rawTarget), '\\');
                $parent = dirname($path);
                $realParent = realpath($parent);
                $expectedLocation = $realParent
                    ? rtrim($realParent, '\\/') . DIRECTORY_SEPARATOR . basename($path)
                    : rtrim(str_replace('/', '\\', $path), '\\');

                if (strcasecmp($normalizedTarget, $expectedLocation) !== 0) {
                    return true;
                }
            }

            // Check if realpath resolved to a different location than its parent directory would dictate
            $parent = dirname($path);
            $realParent = realpath($parent);
            if ($realParent) {
                $expectedLocation = rtrim($realParent, '\\/') . DIRECTORY_SEPARATOR . basename($path);
                $realPath = realpath($path);
                if ($realPath && strcasecmp($expectedLocation, $realPath) !== 0) {
                    return true;
                }
            }
        }

        return false;
    }
}
