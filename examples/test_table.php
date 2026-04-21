<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

$html = '
    <h1>Table Test</h1>
    <table border="1">
        <tr>
            <th>Header 1</th>
            <th>Header 2</th>
        </tr>
        <tr>
            <td>Cell 1.1</td>
            <td>Cell 1.2</td>
        </tr>
        <tr>
            <td>Cell 2.1</td>
            <td>Cell 2.2</td>
        </tr>
    </table>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/table_test.pdf', $pdfBinary);
echo "PDF generated at examples/table_test.pdf\n";
