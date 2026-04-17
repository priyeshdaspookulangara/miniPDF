<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

// Minimal base64 PNG (1x1 transparent)
$pngBase64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8/5+hHgAHggJ/PchI7wAAAABJRU5ErkJggg==';

// Simple SVG
$svgData = '<svg width="100" height="100" viewBox="0 0 100 100">
    <rect x="10" y="10" width="80" height="80" fill="#ff0000" />
    <circle cx="50" cy="50" r="30" fill="#0000ff" />
</svg>';
$svgBase64 = base64_encode($svgData);

$html = '
    <h1>Image Support Test</h1>
    <p>PNG Image (Data URI):</p>
    <img src="data:image/png;base64,' . $pngBase64 . '" width="50" height="50" style="border: 1px solid black;">

    <p>SVG Image (Base64):</p>
    <img src="data:image/svg+xml;base64,' . $svgBase64 . '" width="100" height="100">

    <p>Inline SVG:</p>
    <img src=\'' . $svgData . '\' width="80" height="80">

    <p>Images in a Table:</p>
    <table>
        <tr>
            <th>Icon</th>
            <th>Description</th>
        </tr>
        <tr>
            <td><img src="data:image/svg+xml;base64,' . $svgBase64 . '" width="20" height="20"></td>
            <td>A small SVG icon in a cell.</td>
        </tr>
    </table>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/output_images.pdf', $pdfBinary);
echo "PDF generated at examples/output_images.pdf\n";
