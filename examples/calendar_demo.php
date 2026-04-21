<?php

require_once __DIR__ . '/../src/MiniPDF.php';
use MiniPDF\MiniPDF;

if (isset($_GET['download'])) {
    $year = date('Y');
    $month = date('m');
    $monthName = date('F Y');

    $daysInMonth = date('t', strtotime("$year-$month-01"));
    $firstDayOfMonth = date('w', strtotime("$year-$month-01"));
    $today = date('j');

    $html = "<h1 style='text-align: center;'>$monthName</h1>";
    $html .= "<table border='1' style='width: 100%;'>";
    $html .= "<tr>
                <th>Sun</th><th>Mon</th><th>Tue</th><th>Wed</th><th>Thu</th><th>Fri</th><th>Sat</th>
              </tr>";

    $html .= "<tr>";

    // Empty cells for days before the first of the month
    for ($i = 0; $i < $firstDayOfMonth; $i++) {
        $html .= "<td></td>";
    }

    $currentDay = 1;
    $dayOfWeek = $firstDayOfMonth;

    while ($currentDay <= $daysInMonth) {
        if ($dayOfWeek == 7) {
            $html .= "</tr><tr>";
            $dayOfWeek = 0;
        }

        $style = "";
        if ($currentDay == $today) {
            $style = " style='color: #ff0000; font-weight: bold;'";
        }

        $html .= "<td$style>$currentDay</td>";

        $currentDay++;
        $dayOfWeek++;
    }

    // Fill the rest of the last row
    while ($dayOfWeek < 7) {
        $html .= "<td></td>";
        $dayOfWeek++;
    }

    $html .= "</tr></table>";

    $minipdf = new MiniPDF($html);
    $minipdf->stream("calendar_$month.pdf");
    exit;
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>Calendar Demo</title>
    <style>
        body { font-family: sans-serif; margin: 40px; line-height: 1.6; }
        .demo-box { border: 1px solid #ccc; padding: 20px; border-radius: 8px; max-width: 600px; }
        button { cursor: pointer; padding: 10px 15px; background: #007bff; color: white; border: none; border-radius: 4px; }
        button:hover { background: #0056b3; }
        a.btn-link { text-decoration: none; display: inline-block; margin-right: 10px; }
    </style>
</head>
<body>
    <div class="demo-box">
        <h1>MiniPDF Calendar Generator</h1>
        <p>Choose a download method for the current month's calendar:</p>

        <p>
            <strong>Standard Link:</strong><br>
            <a href="?download=1" target="_blank" class="btn-link">Download (New Tab)</a>
        </p>

        <p>
            <strong>AJAX Method (No Page Refresh/Tab):</strong><br>
            <button id="ajaxDownload">Download via AJAX</button>
        </p>
    </div>

    <script>
        document.getElementById('ajaxDownload').addEventListener('click', function() {
            const btn = this;
            btn.disabled = true;
            btn.textContent = 'Generating...';

            fetch('?download=1')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.blob();
                })
                .then(blob => {
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'calendar_<?php echo date('m'); ?>.pdf';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    btn.disabled = false;
                    btn.textContent = 'Download via AJAX';
                })
                .catch(error => {
                    console.error('Download failed:', error);
                    alert('Failed to download PDF.');
                    btn.disabled = false;
                    btn.textContent = 'Download via AJAX';
                });
        });
    </script>
</body>
</html>
