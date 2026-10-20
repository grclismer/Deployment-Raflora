<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentBuildSpecificationTest extends TestCase
{
    protected string $nixpacksFile;
    protected string $content;

    protected function setUp(): void
    {
        parent::setUp();
        $this->nixpacksFile = base_path('nixpacks.toml');
        $this->assertFileExists($this->nixpacksFile, 'nixpacks.toml must exist in the project root.');
        $this->content = file_get_contents($this->nixpacksFile);
    }

    public function test_nixpacks_specification_declares_required_providers(): void
    {
        $this->assertStringContainsString('providers = [', $this->content);
        $this->assertStringContainsString('"node"', $this->content);
        $this->assertStringContainsString('"php"', $this->content);
    }

    public function test_nixpacks_specification_pins_node_version(): void
    {
        $this->assertStringContainsString('NIXPACKS_NODE_VERSION = "20"', $this->content);
    }

    public function test_install_phase_installs_production_composer_dependencies_safely(): void
    {
        // Must use composer install with production flags
        $this->assertStringContainsString('composer install --no-dev', $this->content);
        $this->assertStringContainsString('--optimize-autoloader', $this->content);

        // Must NOT run composer setup (which contains destructive migrate --force)
        $this->assertStringNotContainsString('composer setup', $this->content);
        $this->assertStringNotContainsString('composer run setup', $this->content);
    }

    public function test_install_phase_installs_npm_dependencies_including_dev_dependencies(): void
    {
        // Must use npm ci with --include=dev to ensure Vite and Tailwind are installed
        $this->assertStringContainsString('npm ci --include=dev', $this->content);
    }

    public function test_build_phase_executes_production_asset_build(): void
    {
        $this->assertStringContainsString('[phases.build]', $this->content);
        $this->assertStringContainsString('npm run build', $this->content);
    }

    public function test_start_command_integrates_storage_initialization_and_port(): void
    {
        $this->assertStringContainsString('[start]', $this->content);
        $this->assertStringContainsString('sh scripts/container-startup.sh', $this->content);
        $this->assertStringContainsString('php artisan serve', $this->content);
        $this->assertStringContainsString('--host=0.0.0.0', $this->content);
        $this->assertStringContainsString('${PORT:-8080}', $this->content);
    }

    public function test_nixpacks_strictly_excludes_automatic_database_migrations_and_seeding(): void
    {
        $this->assertStringNotContainsString('artisan migrate', $this->content);
        $this->assertStringNotContainsString('migrate:fresh', $this->content);
        $this->assertStringNotContainsString('db:seed', $this->content);
    }

    public function test_nixpacks_does_not_hardcode_secrets_or_domains(): void
    {
        $this->assertStringNotContainsString('base64:', $this->content);
        $this->assertStringNotContainsString('railway.app', $this->content);
        $this->assertStringNotContainsString('raflora.com', $this->content);
    }
}
