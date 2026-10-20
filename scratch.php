<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

foreach (\App\Models\Package::all() as $p) {
    echo $p->id . ' - ' . $p->title . "\n";
    echo $p->description . "\n";
    echo "-----\n";
}
