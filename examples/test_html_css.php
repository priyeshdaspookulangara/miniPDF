<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

$html = '
    <h1 style="color: #ff0000; text-align: center;">MiniPDF HTML/CSS Support Test</h1>
    <p>This is a paragraph with <b>bold</b>, <i>italic</i>, <b><i>bold italic</i></b>, and <u>underlined</u> text.</p>
    <p>Testing line wrapping: This is a very long paragraph that should definitely wrap across multiple lines if everything is working correctly. It contains enough words to exceed the page width multiple times, allowing us to verify that the line breaking logic is robust.</p>
    <p>Testing <br> manual <br> line <br> breaks.</p>
    <h2>Lists and Backgrounds</h2>
    <ul>
        <li>First item in an unordered list</li>
        <li style="color: #0000ff;">Second item with blue color</li>
        <li>Third item with <i>italicized</i> text</li>
    </ul>
    <div style="background-color: #dddddd; padding: 10px; margin-bottom: 20px;">
        This block has a light gray background and a bottom margin.
    </div>
    <div style="background-color: #ffff00; text-align: right;">
        Right aligned text with yellow background.
    </div>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/output_html_css.pdf', $pdfBinary);
echo "PDF generated at examples/output_html_css.pdf\n";
