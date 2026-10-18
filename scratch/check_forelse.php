<?php
// Developer check (read-only): counts @forelse / @empty / @endforelse in a view.
// Usage: php scratch/check_forelse.php [view file, default resources/views/admin/booking-show.blade.php]
chdir(dirname(__DIR__));

$path = $argv[1] ?? 'resources/views/admin/booking-show.blade.php';
$c = @file_get_contents($path);
if ($c === false) {
    fwrite(STDERR, "Cannot read $path\n");
    exit(1);
}

preg_match_all('/@forelse\b/', $c, $m1);
preg_match_all('/@empty\b/', $c, $m2);
preg_match_all('/@endforelse\b/', $c, $m3);
echo 'Forelses: ' . count($m1[0]) . ' Empties: ' . count($m2[0]) . ' Endforelses: ' . count($m3[0]) . "\n";
