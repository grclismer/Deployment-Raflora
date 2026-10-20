<?php

namespace Database\Seeders;

use App\Models\Booking;
use App\Models\BookingItem;
use App\Models\Client;
use App\Models\ClientNotification;
use App\Models\InventoryItem;
use App\Models\ReturnItem;
use App\Models\StaffChecklistItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class E2EBookingSeeder extends Seeder
{
    use WithoutModelEvents;

    private const BOOKING_ID = 1;

    public function run(): void
    {
        $this->guardEnvironment();

        DB::transaction(function (): void {
            $this->removeBookingOneState();

            $clientUser = User::updateOrCreate(
                ['email' => 'test.client@raflora.local'],
                [
                    'name' => 'Test Client',
                    'first_name' => 'Test',
                    'last_name' => 'Client',
                    'password' => Hash::make('TestClient123!'),
                    'role' => 'client',
                ]
            );
            $admin = User::updateOrCreate(
                ['email' => 'test.admin@raflora.local'],
                [
                    'name' => 'Test Admin',
                    'first_name' => 'Test',
                    'last_name' => 'Admin',
                    'password' => Hash::make('TestAdmin123!'),
                    'role' => 'admin',
                ]
            );
            $staff = User::updateOrCreate(
                ['email' => 'test.staff@raflora.local'],
                [
                    'name' => 'Test Staff',
                    'first_name' => 'Test',
                    'last_name' => 'Staff',
                    'password' => Hash::make('TestStaff123!'),
                    'role' => 'staff',
                ]
            );

            $client = Client::updateOrCreate(
                ['email' => $clientUser->email],
                [
                    'full_name' => $clientUser->name,
                    'phone' => null,
                    'address' => null,
                ]
            );

            $materialA = InventoryItem::updateOrCreate(
                ['name' => 'TEST-E2E-001 Material A'],
                [
                    'category' => 'floral',
                    'is_perishable' => true,
                    'current_stock' => 25,
                    'unit_cost' => 12.50,
                    'min_stock' => 5,
                    'unit' => 'stems',
                ]
            );
            $materialB = InventoryItem::updateOrCreate(
                ['name' => 'TEST-E2E-001 Material B'],
                [
                    'category' => 'floral',
                    'is_perishable' => true,
                    'current_stock' => 1,
                    'unit_cost' => 8.00,
                    'min_stock' => 3,
                    'unit' => 'stems',
                ]
            );

            $booking = Booking::updateOrCreate(
                ['id' => self::BOOKING_ID],
                [
                    'client_id' => $client->id,
                    'handled_by' => $admin->id,
                    'staff_id' => $staff->id,
                    'event_type' => 'Birthday Event',
                    'event_date' => Carbon::parse('2026-10-07'),
                    'event_time' => null,
                    'event_size' => 'small',
                    'venue' => 'Test Venue',
                    'special_requests' => 'TEST-E2E-001 Controlled end-to-end workflow test',
                    'inspiration_image' => null,
                    'status' => 'pending',
                    'raw_materials_sum' => 0,
                    'multiplier' => 3,
                    'final_quoted_price' => 0,
                    'total_quoted' => null,
                    'price_valid_until' => Carbon::today()->addDays(7),
                    'suggested_procurement_date' => Carbon::parse('2026-10-01'),
                    'admin_notes' => null,
                ]
            );

            BookingItem::create([
                'booking_id' => $booking->id,
                'inventory_item_id' => $materialA->id,
                'item_name' => $materialA->name,
                'quantity' => 12,
                'quoted_unit_price' => 12.50,
                'is_ai_suggested' => false,
                'confirmed_at' => null,
                'procurement_status' => 'pending',
                'notes' => 'Available material for controlled workflow',
            ]);
            BookingItem::create([
                'booking_id' => $booking->id,
                'inventory_item_id' => $materialB->id,
                'item_name' => $materialB->name,
                'quantity' => 4,
                'quoted_unit_price' => 8.00,
                'is_ai_suggested' => false,
                'confirmed_at' => null,
                'procurement_status' => 'pending',
                'notes' => 'Low-stock material to test shortage/planning scenario',
            ]);

            StaffChecklistItem::create([
                'booking_id' => $booking->id,
                'key' => 'test_e2e_booking_readiness',
                'title' => 'Test E2E booking readiness',
                'is_completed' => false,
                'notes' => 'Controlled checklist item for the E2E workflow test.',
            ]);
        });

        $this->command?->info('TEST-E2E-001 reset in raflora_test_db.');
    }

    private function guardEnvironment(): void
    {
        $environment = (string) app()->environment();
        $connection = (string) config('database.default');
        $database = (string) config('database.connections.mysql.database');

        if (!in_array($environment, ['local', 'testing'], true)
            || $connection !== 'mysql'
            || $database !== 'raflora_test_db') {
            throw new RuntimeException(
                'E2EBookingSeeder is restricted to APP_ENV=local/testing, DB_CONNECTION=mysql, DB_DATABASE=raflora_test_db.'
            );
        }
    }

    private function removeBookingOneState(): void
    {
        $returnIds = DB::table('returns')
            ->where('booking_id', self::BOOKING_ID)
            ->pluck('id');

        if ($returnIds->isNotEmpty()) {
            ReturnItem::whereIn('return_id', $returnIds)->delete();
        }

        foreach ([
            'payments',
            'inventory_transactions',
            'returns',
            'staff_checklist_items',
            'admin_alerts',
            'client_notifications',
            'quotation_history',
            'quotations',
            'presentations',
            'booking_items',
        ] as $table) {
            if (Schema::hasTable($table)) {
                DB::table($table)->where('booking_id', self::BOOKING_ID)->delete();
            }
        }

        if (Schema::hasTable('audit_logs')) {
            DB::table('audit_logs')
                ->where('entity_type', Booking::class)
                ->where('entity_id', self::BOOKING_ID)
                ->delete();
        }
    }
}
