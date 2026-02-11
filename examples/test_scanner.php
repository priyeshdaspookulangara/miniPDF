<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/PDFScanner.php';

use MiniPDF\PDFScanner;

$filePath = __DIR__ . '/output_complex.pdf';

if (!file_exists($filePath)) {
    echo "Test PDF not found. Please run examples/test_complex.php first.\n";
    exit(1);
}

$scanner = new PDFScanner();
try {
    $html = $scanner->convertToHtml($filePath);
    echo "--- Extracted HTML from $filePath ---\n";
    echo $html;
    echo "------------------------------------------\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
