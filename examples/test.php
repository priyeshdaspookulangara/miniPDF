<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

$html = '
    <h1 style="color: #ff0000; text-align: center;">Hello MiniPDF</h1>
    <p style="font-size: 14px;">This is a <b>basic</b> PDF generator written in 100% PHP.</p>
    <div style="color: #0000ff; text-align: right;">
        Support for inline styles like <span style="font-size: 18px; color: #00ff00;">color</span> and font-size.
    </div>
    <p>Coordinate mapping maps HTML to A4 page.</p>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/output.pdf', $pdfBinary);
echo "PDF generated at examples/output.pdf\n";
