<?php
require_once __DIR__ . '/vendor/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf = $parser->parseFile(__DIR__ . '/Caps1.pdf');
file_put_contents(__DIR__ . '/pdf_output.txt', $pdf->getText());
echo "Done";
