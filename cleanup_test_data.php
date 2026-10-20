<?php

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$driver = DB::connection()->getDriverName();
$tables = [
    'booking_items',
    'presentations',
    'proposals',
    'ai_analysis_results',
    'audit_logs',
    'notifications',
    'admin_alerts',
    'payments',
    'inventory_transactions',
    'bookings',
    'inventory_items',
];

if ($driver === 'mysql') {
    DB::statement('SET FOREIGN_KEY_CHECKS = 0');
} elseif ($driver === 'sqlite') {
    DB::statement('PRAGMA foreign_keys = OFF');
}

foreach ($tables as $table) {
    if (Schema::hasTable($table)) {
        DB::table($table)->truncate();
        echo $table . " truncated\n";
    }
}

if ($driver === 'mysql') {
    DB::statement('SET FOREIGN_KEY_CHECKS = 1');
} elseif ($driver === 'sqlite') {
    DB::statement('PRAGMA foreign_keys = ON');
}

$users = DB::table('users')->select('id', 'email', 'name')->get();
echo 'users_count=' . count($users) . "\n";
foreach ($users as $user) {
    echo $user->id . ':' . $user->email . "\n";
}
