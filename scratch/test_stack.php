<?php
// Developer check (read-only): prints the HTML tag nesting of guest booking step 1.
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$errors = new \Illuminate\Support\ViewErrorBag();
view()->share('errors', $errors);
$html = view('guest.booking-create', ['packages' => \App\Models\Package::all()])->render();

// Remove all <script>...</script> blocks so we don't match divs in JS strings
$noScript = preg_replace('/<script\b[^>]*>[\s\S]*?<\/script>/i', '', $html);

// Find where #guest-step-1 starts
$pos = strpos($noScript, 'id="guest-step-1"');
if ($pos === false) {
    exit("guest-step-1 not found in the rendered page.\n");
}
// Start at the "<" of the step's own tag so its opening <div> is counted too.
$pos = strrpos(substr($noScript, 0, $pos), '<');
$sub = substr($noScript, $pos);

// Stop before guest-step-2
$pos2 = strpos($sub, 'id="guest-step-2"');
if ($pos2) $sub = substr($sub, 0, $pos2);

preg_match_all('/(<\/?([a-zA-Z0-9\-]+)[^>]*>)/i', $sub, $matches, PREG_OFFSET_CAPTURE);

$stack = [];
foreach ($matches[1] as $idx => $m) {
    $tagStr = $m[0];
    $tagName = strtolower($matches[2][$idx][0]);
    $offset = $m[1];
    
    // Ignore void elements
    if (in_array($tagName, ['input', 'img', 'br', 'hr', 'meta', 'link'])) continue;
    
    // Ignore self-closing XML-style
    if (substr($tagStr, -2) === '/>') continue;
    
    $isClose = (strpos($tagStr, '</') === 0);
    
    if (!$isClose) {
        preg_match('/(?:id|class)="([^"]+)"/', $tagStr, $attr);
        $desc = isset($attr[1]) ? substr($attr[1], 0, 35) : '';
        $stack[] = ['tag' => $tagName, 'desc' => $desc, 'offset' => $offset];
        echo str_repeat("  ", count($stack) - 1) . "+ <$tagName> ($desc)\n";
    } else {
        $last = array_pop($stack);
        if ($last === null) {
            // A closing tag with nothing open: the step's markup closes more than it opened.
            echo "- </$tagName> MISMATCH! Nothing is open here\n";
            continue;
        }
        $matched = $last['tag'] === $tagName;
        echo str_repeat("  ", count($stack)) . "- </$tagName>" . ($matched ? " (matched <{$last['tag']}> {$last['desc']})" : " MISMATCH! Expected <{$last['tag']}>") . "\n";
    }
}
