<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

$html = '
    <h1>Reproduction of Overlapping Issues</h1>
    <p>This table demonstrates how text wrapping in a cell doesn\'t increase the row height, causing overlap with the next row.</p>
    <table border="1">
        <tr>
            <th width="50%">Wrapped Text</th>
            <th width="50%">Standard Text</th>
        </tr>
        <tr>
            <td>This is a very long text that should definitely wrap into multiple lines. Since the current engine only calculates height based on font size, this should overlap the next row. It needs to wrap and push the next row down.</td>
            <td>Cell 1.2</td>
        </tr>
        <tr>
            <td>Cell 2.1 (Should be below the wrapped text)</td>
            <td>Cell 2.2</td>
        </tr>
    </table>
    <p>Below is a div that might clump if vertical tracking isn\'t perfect.</p>
    <div style="background-color: #eeeeee; padding: 10px;">
        This is a div.
    </div>
    <div style="background-color: #dddddd; padding: 10px;">
        This is another div that should be below the first one.
    </div>
';

$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

file_put_contents(__DIR__ . '/repro_issue.pdf', $pdfBinary);
echo "PDF generated at examples/repro_issue.pdf\n";
