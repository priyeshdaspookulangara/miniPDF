<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

$html = '
    <h1>Testing HTML/CSS Support</h1>
    <p>This is <b>bold</b>, <i>italic</i>, and <u>underlined</u>.</p>
    <p style="font-style: italic; color: #ff00ff;">This is italic via CSS.</p>
    <ul style="color: #0000ff;">
        <li>Item 1</li>
        <li>Item 2</li>
    </ul>
    <div style="background-color: #eeeeee; padding: 10px;">
        Block with background color and padding.
    </div>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/output_test.pdf', $pdfBinary);
echo "PDF generated at examples/output_test.pdf\n";
