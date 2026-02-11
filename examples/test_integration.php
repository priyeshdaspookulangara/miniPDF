<?php

// Simulate POST request to generate.php
$_POST['html'] = '<h1 style="color: #ff0000; text-align: center;">AJAX Test</h1><p>Testing the <b>integration</b> flow.</p><div style="text-align: right; color: #0000ff;">Right aligned text</div>';
$_SERVER['REQUEST_METHOD'] = 'POST';

ob_start();
include __DIR__ . '/../web_editor/generate.php';
$pdf = ob_get_clean();

if (str_starts_with($pdf, "%PDF-1.7")) {
    echo "Success: PDF generated successfully from simulated AJAX request.\n";
    file_put_contents(__DIR__ . '/ajax_test.pdf', $pdf);
    echo "Saved to examples/ajax_test.pdf\n";
} else {
    echo "Failure: PDF generation failed.\n";
    echo substr($pdf, 0, 100) . "\n";
}
