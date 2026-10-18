<?php
// Developer check (read-only): compiles a Blade view and counts PHP's "if (...):" blocks against "endif;"
// using PHP's own tokenizer, so directives such as @error (which close with "...; endif;") count correctly.
// Usage: php scratch/check_php_ifs.php [view name, default admin.booking-show]
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
require __DIR__ . '/count_php_ifs.php';

$view = $argv[1] ?? 'admin.booking-show';
$source = view()->getFinder()->find($view);
$compiled = app('blade.compiler')->compileString(file_get_contents($source));

[$ifs, $endifs] = count_php_ifs($compiled);
echo "If: $ifs, Endif: $endifs" . ($ifs === $endifs ? " (balanced)\n" : " (NOT balanced)\n");
exit($ifs === $endifs ? 0 : 1);
