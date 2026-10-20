<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "AssetReturn count: " . App\Models\AssetReturn::count() . PHP_EOL;
echo "ReturnItem count: " . App\Models\ReturnItem::count() . PHP_EOL;

foreach (App\Models\ReturnItem::with('assetReturn')->orderBy('id')->get() as $ri) {
    echo "Item #{$ri->id}: ";
    echo "return_id={$ri->return_id} ";
    echo "(Booking #" . ($ri->assetReturn?->booking_id ?? 'N/A') . ") ";
    echo "inv_id={$ri->inventory_item_id} ";
    echo "qty_ret={$ri->quantity_returned} ";
    echo "good={$ri->quantity_good} ";
    echo "damaged={$ri->quantity_damaged} ";
    echo "lost={$ri->quantity_lost} ";
    echo "cond={$ri->condition} ";
    echo "dmg={$ri->damage_charge} ";
    echo "dec={$ri->charge_decision}" . PHP_EOL;
}
