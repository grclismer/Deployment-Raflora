<?php
// Developer check (read-only): traces where #guest-step-1 closes in the rendered guest booking form.
chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$errors = new \Illuminate\Support\ViewErrorBag();
view()->share('errors', $errors);
$html = view('guest.booking-create', ['packages' => \App\Models\Package::all()])->render();

// Let's inspect where #guest-step-1 is closed in the rendered HTML!
$posStep1 = strpos($html, 'id="guest-step-1"');
if ($posStep1 === false) {
    exit("guest-step-1 not found in the rendered page.\n");
}
// Start at the "<" of the step's own <div> so depth 0 really means the step itself closed.
$posStep1 = strrpos(substr($html, 0, $posStep1), '<');
$sub = substr($html, $posStep1);

// Find each open and close div from $posStep1
preg_match_all('/(<\/?div\b[^>]*>)/i', $sub, $matches, PREG_OFFSET_CAPTURE);
$depth = 0;
foreach ($matches[0] as $m) {
    $tag = $m[0];
    $offset = $m[1];
    $isClose = (strpos($tag, '</') === 0);
    if ($isClose) {
        $depth--;
        echo "Depth: $depth after $tag at offset $offset\n";
        if ($depth === 0) {
            echo "--> GUEST-STEP-1 WAS CLOSED HERE! <--\n";
            echo "Context: " . substr($sub, max(0, $offset - 100), 200) . "\n\n";
            break;
        }
    } else {
        $depth++;
        // Print opening tag summary
        preg_match('/(?:id|class)="([^"]+)"/', $tag, $attr);
        $desc = isset($attr[1]) ? substr($attr[1], 0, 40) : '';
        echo "Depth: $depth OPEN $tag ($desc)\n";
    }
}
