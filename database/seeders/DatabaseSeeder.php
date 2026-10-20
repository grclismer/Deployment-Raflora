<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Run: php artisan db:seed
     *
     * Default credentials after seeding:
     *   Admin:  admin@raflora.com   / Admin@12345
     *   Staff:  staff@raflora.com   / Staff@12345
     *   Client: client@demo.com     / Client@12345
     */
    public function run(): void
    {
        $this->call([
            AdminSeeder::class,
            InventorySeeder::class,
            PackageSeeder::class,
            DemoBookingSeeder::class,
        ]);
    }
}
