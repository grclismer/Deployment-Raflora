<?php

namespace Tests\Feature;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class StorageInitializationCommandTest extends TestCase
{
    public function test_storage_initialize_command_succeeds_and_verifies_required_directories(): void
    {
        $exitCode = Artisan::call('storage:initialize');

        $this->assertSame(Command::SUCCESS, $exitCode);
        $output = Artisan::output();

        $this->assertStringContainsString('Starting Raflora storage initialization', $output);
        $this->assertStringContainsString('Raflora persistent storage initialization completed successfully', $output);

        // Verify required storage paths exist
        $this->assertTrue(File::isDirectory(storage_path('app/public')));
        $this->assertTrue(File::isDirectory(storage_path('app/private')));
        $this->assertTrue(File::isDirectory(storage_path('framework/views')));
        $this->assertTrue(File::isDirectory(storage_path('framework/sessions')));
        $this->assertTrue(File::isDirectory(storage_path('framework/cache')));
        $this->assertTrue(File::isDirectory(storage_path('framework/cache/data')));
        $this->assertTrue(File::isDirectory(storage_path('logs')));
    }

    public function test_storage_initialize_is_idempotent_on_consecutive_executions(): void
    {
        $exitCode1 = Artisan::call('storage:initialize');
        $this->assertSame(Command::SUCCESS, $exitCode1);

        $exitCode2 = Artisan::call('storage:initialize');
        $this->assertSame(Command::SUCCESS, $exitCode2);

        $output = Artisan::output();
        $this->assertStringContainsString('Existing public disk root directory verified', $output);
        $this->assertStringContainsString('Existing private disk root directory verified', $output);
        $this->assertStringContainsString('Public storage symlink is valid', $output);
    }

    public function test_public_storage_symlink_resolves_to_public_disk_root(): void
    {
        $linkPath = public_path('storage');
        $targetPath = storage_path('app/public');

        $this->assertTrue(file_exists($linkPath) || is_link($linkPath));

        $realLink = realpath($linkPath);
        $realTarget = realpath($targetPath);

        $this->assertNotEmpty($realLink);
        $this->assertNotEmpty($realTarget);
        $this->assertSame(
            rtrim(str_replace('\\', '/', $realLink), '/'),
            rtrim(str_replace('\\', '/', $realTarget), '/')
        );
    }

    public function test_private_storage_remains_isolated_from_public_storage_symlink(): void
    {
        $realLink = realpath(public_path('storage'));
        $realPrivate = realpath(storage_path('app/private'));

        $this->assertNotEmpty($realLink);
        $this->assertNotEmpty($realPrivate);

        $normLink = rtrim(str_replace('\\', '/', $realLink), '/') . '/';
        $normPrivate = rtrim(str_replace('\\', '/', $realPrivate), '/') . '/';

        // Ensure private disk is never a subpath of the public symlink target
        $this->assertFalse(str_starts_with($normPrivate, $normLink));
    }

    public function test_storage_initialize_fails_when_public_storage_is_a_regular_conflicting_file(): void
    {
        $linkPath = public_path('storage');
        $backupPath = public_path('storage_test_backup_' . uniqid());

        // Temporarily move the valid link/junction if present
        $hadExistingLink = file_exists($linkPath) || is_link($linkPath);
        if ($hadExistingLink) {
            rename($linkPath, $backupPath);
        }

        try {
            // Create a regular file at public/storage to simulate a conflicting file
            file_put_contents($linkPath, 'conflicting regular file content');

            $exitCode = Artisan::call('storage:initialize');

            $this->assertSame(Command::FAILURE, $exitCode);
            $output = Artisan::output();
            $this->assertStringContainsString('exists as a regular file or directory, not a symbolic link', $output);
            $this->assertStringContainsString('Aborting initialization to prevent silent overwrite', $output);
        } finally {
            // Clean up dummy regular file and restore original link/junction
            if (file_exists($linkPath) && !is_link($linkPath)) {
                @unlink($linkPath);
            }
            if ($hadExistingLink && file_exists($backupPath)) {
                rename($backupPath, $linkPath);
            } else {
                Artisan::call('storage:link', ['--force' => true]);
            }
        }
    }

    public function test_container_startup_script_safety_invariants(): void
    {
        $startupScript = base_path('scripts/container-startup.sh');

        $this->assertFileExists($startupScript);
        $content = file_get_contents($startupScript);

        // Must run storage:initialize
        $this->assertStringContainsString('php artisan storage:initialize', $content);

        // Critical safety: Must NOT execute database migrations on web container boot
        $executableLines = array_filter(
            explode("\n", $content),
            fn(string $line): bool => !str_starts_with(trim($line), '#')
        );
        $executableContent = implode("\n", $executableLines);

        $this->assertStringNotContainsString('artisan migrate', $executableContent);
        $this->assertStringNotContainsString('migrate:fresh', $executableContent);
        $this->assertStringNotContainsString('db:seed', $executableContent);
    }
}

