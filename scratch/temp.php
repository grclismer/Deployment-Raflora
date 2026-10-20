<?php
// Developer check (read-only): counts PHP if/endif blocks in an already-compiled view file
// (storage/framework/views/<hash>.php). For a view by name, use scratch/check_php_ifs.php.
// Usage: php scratch/temp.php <compiled view file>
chdir(dirname(__DIR__));

$path = $argv[1] ?? null;
$content = $path ? @file_get_contents($path) : false;
if ($content === false) {
    fwrite(STDERR, "Usage: php scratch/temp.php <compiled view file in storage/framework/views>\n");
    exit(1);
}

require __DIR__ . '/count_php_ifs.php';
[$ifs, $endifs] = count_php_ifs($content);
echo "Orig If: $ifs, Endif: $endifs\n";
