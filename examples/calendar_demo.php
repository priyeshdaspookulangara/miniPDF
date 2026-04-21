<?php

require_once __DIR__ . '/../src/MiniPDF.php';
use MiniPDF\MiniPDF;

if (isset($_GET['download'])) {
    $year = date('Y');
    $month = date('m');
    $monthName = date('F Y');

    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
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
</head>
<body>
    <h1>MiniPDF Calendar Generator</h1>
    <p>Click the link below to generate and download a PDF calendar of the current month.</p>
    <a href="?download=1">Download Calendar</a>
</body>
</html>
