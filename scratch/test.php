<?php
// Developer check: a booking_lock transaction counts toward the item's reserved stock.
// Everything runs inside a database transaction that is always rolled back, so nothing is kept.
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\InventoryTransaction;
use Illuminate\Support\Facades\DB;

$item = InventoryItem::first();
$bookingId = Booking::value('id');
if (!$item || !$bookingId) {
    echo "Needs at least one inventory item and one booking.\n";
    exit;
}

echo "Current Item ID: " . $item->id . "\n";
echo "Reserved stock before: " . $item->reserved_stock . "\n";

DB::beginTransaction();
try {
    // Create a dummy transaction
    $tx = InventoryTransaction::create([
        'inventory_item_id' => $item->id,
        'booking_id' => $bookingId,
        'quantity_change' => -4.0,
        'transaction_type' => 'booking_lock',
        'reason' => 'test',
        'performed_by' => null,
    ]);

    echo "Created tx ID: " . $tx->id . " with change: " . $tx->quantity_change . "\n";
    echo "Reserved stock with the test lock: " . $item->fresh()->reserved_stock . "\n";
} finally {
    DB::rollBack();
    echo "Cleanup done (rolled back)\n";
}
