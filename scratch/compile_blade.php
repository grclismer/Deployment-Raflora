<?php
// Developer check (read-only): compiles a Blade view and runs PHP's syntax check on the result,
// so template mistakes show up without rendering the page (which would need real booking data).
// Usage: php scratch/compile_blade.php [view name, default admin.booking-show]
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$view = $argv[1] ?? 'admin.booking-show';
try {
    $source = view()->getFinder()->find($view);
    $compiled = app('blade.compiler')->compileString(file_get_contents($source));
} catch (\Throwable $e) {
    echo $e->getMessage() . "\n";
    echo $e->getFile() . ':' . $e->getLine() . "\n";
    exit(1);
}

$tmp = tempnam(sys_get_temp_dir(), 'blade') . '.php';
file_put_contents($tmp, $compiled);
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $output, $status);
@unlink($tmp);

echo $status === 0
    ? "$view compiles without PHP syntax errors.\n"
    : "$view has a PHP syntax error after compiling:\n" . implode("\n", $output) . "\n";
exit($status === 0 ? 0 : 1);
