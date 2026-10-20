<?php
// Developer check (read-only): Blade block directives in a view must open and close in matching pairs.
// Usage: php scratch/check_nesting.php [view file, default resources/views/admin/booking-show.blade.php]
chdir(dirname(__DIR__));

$path = $argv[1] ?? 'resources/views/admin/booking-show.blade.php';
$content = @file_get_contents($path);
if ($content === false) {
    fwrite(STDERR, "Cannot read $path\n");
    exit(1);
}

$openers = ['@if', '@forelse', '@unless', '@foreach', '@can', '@auth', '@guest'];
$closers = ['@endif', '@endforelse', '@endunless', '@endforeach', '@endcan', '@endauth', '@endguest'];
preg_match_all('/@(if|elseif|else|endif|forelse|empty|endforelse|unless|endunless|foreach|endforeach|can|endcan|auth|endauth|guest|endguest)\b/', $content, $matches, PREG_OFFSET_CAPTURE);

$line = fn (int $offset): int => substr_count($content, "\n", 0, $offset) + 1;
$stack = [];
$problems = 0;
foreach ($matches[0] as [$tag, $offset]) {
    if (in_array($tag, $openers, true)) {
        $stack[] = [$tag, $offset];
    } elseif (in_array($tag, $closers, true)) {
        if (empty($stack)) {
            echo "Unexpected $tag on line {$line($offset)}\n";
            $problems++;
            continue;
        }
        [$open, $openOffset] = array_pop($stack);
        $expected = str_replace('@', '@end', $open);
        if ($expected !== $tag) {
            echo "Mismatch: $open on line {$line($openOffset)} expects $expected but got $tag on line {$line($offset)}\n";
            $problems++;
        }
    }
}

foreach ($stack as [$open, $openOffset]) {
    echo "Unclosed $open on line {$line($openOffset)}\n";
    $problems++;
}

echo $problems === 0 ? "All tags balanced correctly!\n" : "$problems problem(s) found.\n";
exit($problems === 0 ? 0 : 1);
