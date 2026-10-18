<?php
// Developer check: Admin verifying a submitted payment confirms the booking. Stock is not reserved
// here (Admin reserves materials later under Preparation), so "Reserved stock" stays 0.
// Everything runs inside a database transaction that is always rolled back, and mail is captured
// in memory, so nothing is kept and no email is sent.
require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Client;
use App\Models\InventoryItem;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

config(['mail.default' => 'array']);

DB::beginTransaction();
try {
    $admin = User::firstOrCreate(['email' => 'admin@test.com'], ['name' => 'Admin', 'password' => bcrypt('password'), 'role' => 'admin']);
    $client = Client::firstOrCreate(['email' => 'client@test.com'], ['full_name' => 'Client Test', 'phone' => '09170000000']);

    $inventoryItem = InventoryItem::create([
        'name' => 'White Roses Test ' . rand(),
        'category' => 'Fresh Flowers',
        'is_perishable' => false,
        'current_stock' => 10,
        'min_stock' => 0,
        'unit_cost' => 1.50,
        'unit' => 'stem',
    ]);

    $booking = Booking::create([
        'client_id' => $client->id,
        'event_type' => 'wedding',
        'event_date' => now()->addDays(20)->toDateString(),
        'venue' => 'The Garden Hall',
        'status' => 'payment_submitted',
        'total_quoted' => 15000,
    ]);

    $booking->inventoryItems()->attach($inventoryItem->id, [
        'quantity' => 4,
        'quoted_unit_price' => 100,
        'procurement_status' => 'pending',
    ]);

    $payment = Payment::create([
        'booking_id' => $booking->id,
        'amount' => 15000,
        'payment_type' => 'gcash',
        'reference_number' => 'REF-001-' . rand(),
        'status' => 'pending',
    ]);

    Auth::login($admin);

    $request = Request::create('/test', 'POST', ['amount_received' => 15000]);
    $request->setLaravelSession(app('session.store'));

    $controller = app(\App\Http\Controllers\Admin\BookingController::class);
    try {
        $response = $controller->verifyPayment($request, $payment);
        echo "Response status: " . $response->getStatusCode() . "\n";
        echo "Session success: " . session('success') . "\n";
        echo "Session error: " . session('error') . "\n";
    } catch (\Exception $e) {
        echo "Exception: " . $e->getMessage() . "\n";
    }

    echo "Booking status: " . $booking->fresh()->status . "\n";
    echo "Reserved stock: " . $inventoryItem->fresh()->reserved_stock . "\n";

    $txs = \App\Models\InventoryTransaction::where('booking_id', $booking->id)->get();
    echo "Transactions count: " . $txs->count() . "\n";
} finally {
    DB::rollBack();
    echo "Cleanup done (rolled back)\n";
}
