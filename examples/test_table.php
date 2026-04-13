<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

$html = '
    <h1>Table Support Test</h1>
    <table style="width: 100%; border: 1px solid black;">
        <tr style="background-color: #eeeeee;">
            <th>Name</th>
            <th>Description</th>
            <th>Status</th>
        </tr>
        <tr>
            <td>Item 1</td>
            <td>This is a short description.</td>
            <td style="color: #00aa00;">Success</td>
        </tr>
        <tr>
            <td>Item 2</td>
            <td>This is a much longer description that should wrap within the table cell if our logic is working correctly. It needs to be long enough to exceed the column width.</td>
            <td style="color: #ff0000; background-color: #ffeeee;">Error</td>
        </tr>
    </table>
    <p>Text after the table.</p>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/test_table.pdf', $pdfBinary);
echo "PDF generated at examples/test_table.pdf\n";
