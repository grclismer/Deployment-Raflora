<?php
// Developer check (read-only): counts @if / @endif in a view.
// Usage: php scratch/check_tags.php [view file, default resources/views/admin/booking-show.blade.php]
chdir(dirname(__DIR__));

$path = $argv[1] ?? 'resources/views/admin/booking-show.blade.php';
$content = @file_get_contents($path);
if ($content === false) {
    fwrite(STDERR, "Cannot read $path\n");
    exit(1);
}

preg_match_all('/@if\b/', $content, $m1);
preg_match_all('/@endif\b/', $content, $m2);
echo 'Ifs: ' . count($m1[0]) . ' Endifs: ' . count($m2[0]) . "\n";
