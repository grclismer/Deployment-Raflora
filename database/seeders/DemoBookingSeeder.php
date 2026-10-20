<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Presentation;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoBookingSeeder extends Seeder
{
    public function run(): void
    {
        $client = Client::where('email', 'client@demo.com')->first();
        $admin = User::where('email', 'admin@raflora.com')->first();

        if (!$client || !$admin) {
            return;
        }

        $booking = Booking::updateOrCreate(
            ['id' => 1],
            [
                'client_id' => $client->id,
                'handled_by' => $admin->id,
                'event_type' => 'wedding',
                'event_date' => now()->addDays(14)->toDateString(),
                'venue' => 'The Garden Hall',
                'status' => 'quotation_sent',
                'special_requests' => 'Romantic garden installation with warm candlelight.',
                'total_quoted' => 18000,
                'final_quoted_price' => 18000,
                'multiplier' => 3.0,
                'price_valid_until' => now()->addDays(7)->toDateString(),
            ]
        );

        $inventoryItem = InventoryItem::where('name', 'White Roses')->first();
        if ($inventoryItem) {
            $booking->inventoryItems()->syncWithoutDetaching([
                $inventoryItem->id => [
                    'quantity' => 24,
                    'quoted_unit_price' => 18,
                    'is_ai_suggested' => 0,
                    'procurement_status' => 'pending',
                    'notes' => 'Demo booking sample',
                ],
            ]);
        }

        Presentation::updateOrCreate(
            ['booking_id' => $booking->id, 'version' => 'v1'],
            [
                'file_path' => 'bookings/proposals/demo-proposal.pdf',
                'file_name' => 'demo-proposal.pdf',
                'sent_at' => now()->subDay(),
                'sent_by' => $admin->id,
                'status' => 'sent',
            ]
        );

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'status_changed',
            'module' => 'booking',
            'details' => 'Seeded demo booking created',
            'entity_type' => Booking::class,
            'entity_id' => $booking->id,
        ]);

        AuditLog::create([
            'user_id' => $admin->id,
            'action' => 'inventory_updated',
            'module' => 'inventory',
            'details' => 'Seeded demo inventory snapshot',
            'entity_type' => InventoryItem::class,
            'entity_id' => $inventoryItem?->id,
        ]);
    }
}
