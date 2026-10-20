<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Client;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        // Default Admin Account
        User::updateOrCreate(
            ['email' => 'admin@raflora.com'],
            [
                'name'         => 'Raflora Admin',
                'first_name'   => 'Raflora',
                'last_name'    => 'Admin',
                'email'        => 'admin@raflora.com',
                'password'     => Hash::make('Admin@12345'),
                'role'         => 'admin',
                'mobile_number' => '09170000001',
                'email_verified_at' => null,
                'is_bootstrap' => true,
            ]
        );

        // Default Staff Account
        User::updateOrCreate(
            ['email' => 'staff@raflora.com'],
            [
                'name'         => 'Raflora Staff',
                'first_name'   => 'Maria',
                'last_name'    => 'Santos',
                'email'        => 'staff@raflora.com',
                'password'     => Hash::make('Staff@12345'),
                'role'         => 'staff',
                'mobile_number' => '09170000002',
                'email_verified_at' => now(),
            ]
        );

        // Demo Client Account
        $clientUser = User::updateOrCreate(
            ['email' => 'client@demo.com'],
            [
                'name'         => 'Demo Client',
                'first_name'   => 'Juan',
                'last_name'    => 'dela Cruz',
                'email'        => 'client@demo.com',
                'password'     => Hash::make('Client@12345'),
                'role'         => 'client',
                'mobile_number' => '09170000003',
                'email_verified_at' => now(),
            ]
        );

        Client::updateOrCreate(
            ['email' => $clientUser->email],
            [
                'full_name' => $clientUser->name,
                'phone' => $clientUser->mobile_number,
                'address' => '123 Demo Street, Quezon City',
            ]
        );
    }
}
