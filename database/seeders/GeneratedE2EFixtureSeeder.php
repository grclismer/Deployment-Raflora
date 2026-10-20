<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

class GeneratedE2EFixtureSeeder extends Seeder
{
    public function run(): void
    {
        $this->guardEnvironment();

        $runId = (string) env('E2E_RUN_ID', '');
        $passwords = [
            'client' => (string) env('E2E_CLIENT_PASSWORD', ''),
            'admin' => (string) env('E2E_ADMIN_PASSWORD', ''),
            'staff' => (string) env('E2E_STAFF_PASSWORD', ''),
        ];

        if (!preg_match('/^[A-Za-z0-9_-]{4,32}$/', $runId)) {
            throw new RuntimeException('E2E_RUN_ID must contain 4-32 letters, numbers, underscores, or hyphens.');
        }

        foreach ($passwords as $role => $password) {
            if ($password === '') {
                throw new RuntimeException("E2E_{$role}_PASSWORD must be provided for local fixture creation.");
            }
        }

        $prefix = 'e2e-' . Str::lower($runId);

        $summary = DB::transaction(function () use ($prefix, $passwords): array {
            $clientUser = User::create([
                'name' => 'E2E Client ' . $prefix,
                'first_name' => 'E2E',
                'last_name' => 'Client ' . $prefix,
                'username' => $prefix . '-client',
                'email' => $prefix . '-client@raflora.local',
                'password' => Hash::make($passwords['client']),
                'role' => 'client',
            ]);

            $adminUser = User::create([
                'name' => 'E2E Admin ' . $prefix,
                'first_name' => 'E2E',
                'last_name' => 'Admin ' . $prefix,
                'username' => $prefix . '-admin',
                'email' => $prefix . '-admin@raflora.local',
                'password' => Hash::make($passwords['admin']),
                'role' => 'admin',
            ]);

            $staffUser = User::create([
                'name' => 'E2E Staff ' . $prefix,
                'first_name' => 'E2E',
                'last_name' => 'Staff ' . $prefix,
                'username' => $prefix . '-staff',
                'email' => $prefix . '-staff@raflora.local',
                'password' => Hash::make($passwords['staff']),
                'role' => 'staff',
            ]);

            $client = Client::create([
                'full_name' => $clientUser->name,
                'email' => $clientUser->email,
                'phone' => '09000000000',
                'address' => 'E2E Test Address',
            ]);

            $availableMaterial = InventoryItem::create([
                'name' => $prefix . '-material-available',
                'category' => 'floral',
                'is_perishable' => true,
                'current_stock' => 24,
                'unit_cost' => 12.50,
                'min_stock' => 5,
                'unit' => 'stems',
            ]);

            $shortageMaterial = InventoryItem::create([
                'name' => $prefix . '-material-shortage',
                'category' => 'floral',
                'is_perishable' => true,
                'current_stock' => 1,
                'unit_cost' => 8.00,
                'min_stock' => 3,
                'unit' => 'stems',
            ]);

            $booking = Booking::create([
                'client_id' => $client->id,
                'handled_by' => null,
                'staff_id' => null,
                'event_type' => 'Birthday Event',
                'event_date' => now()->addDays(30)->toDateString(),
                'event_time' => '14:00',
                'event_size' => 'small',
                'venue' => 'E2E Test Venue',
                'special_requests' => 'Generated E2E fixture prerequisite.',
                'status' => 'pending',
                'raw_materials_sum' => 0,
                'multiplier' => 3,
                'price_valid_until' => now()->addDays(37)->toDateString(),
                'suggested_procurement_date' => now()->addDays(24)->toDateString(),
            ]);

            $availableItem = BookingItem::create([
                'booking_id' => $booking->id,
                'inventory_item_id' => $availableMaterial->id,
                'item_name' => $availableMaterial->name,
                'quantity' => 6,
                'quoted_unit_price' => 12.50,
                'is_ai_suggested' => false,
                'procurement_status' => 'pending',
                'notes' => 'Available material for generated E2E fixture.',
            ]);

            $shortageItem = BookingItem::create([
                'booking_id' => $booking->id,
                'inventory_item_id' => $shortageMaterial->id,
                'item_name' => $shortageMaterial->name,
                'quantity' => 4,
                'quoted_unit_price' => 8.00,
                'is_ai_suggested' => false,
                'procurement_status' => 'pending',
                'notes' => 'Shortage material for generated E2E fixture.',
            ]);

            return [
                'database' => config('database.connections.mysql.database'),
                'run_id' => $prefix,
                'users' => [
                    'client' => ['id' => $clientUser->id, 'email' => $clientUser->email],
                    'admin' => ['id' => $adminUser->id, 'email' => $adminUser->email],
                    'staff' => ['id' => $staffUser->id, 'email' => $staffUser->email],
                ],
                'client' => ['id' => $client->id, 'email' => $client->email],
                'booking' => ['id' => $booking->id, 'status' => $booking->status],
                'booking_items' => [$availableItem->id, $shortageItem->id],
                'inventory_items' => [$availableMaterial->id, $shortageMaterial->id],
            ];
        });

        $this->command?->line('E2E_FIXTURE_SUMMARY=' . json_encode($summary, JSON_UNESCAPED_SLASHES));
    }

    private function guardEnvironment(): void
    {
        $database = (string) config('database.connections.mysql.database');
        $resolved = DB::selectOne('SELECT DATABASE() AS database_name')->database_name;

        if ($database !== 'raflora_e2e_db' || $resolved !== 'raflora_e2e_db') {
            throw new RuntimeException('GeneratedE2EFixtureSeeder is restricted to raflora_e2e_db.');
        }
    }
}