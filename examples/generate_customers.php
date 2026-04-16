<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

// Sample customer data
$customers = [
    ['id' => 1, 'name' => 'John Doe', 'email' => 'john@example.com', 'status' => 'Active'],
    ['id' => 2, 'name' => 'Jane Smith', 'email' => 'jane@example.com', 'status' => 'Inactive'],
    ['id' => 3, 'name' => 'Robert Johnson', 'email' => 'robert@example.com', 'status' => 'Active'],
    ['id' => 4, 'name' => 'Emily Davis', 'email' => 'emily@example.com', 'status' => 'Active'],
    ['id' => 5, 'name' => 'Michael Brown', 'email' => 'michael@example.com', 'status' => 'Pending'],
];

// Generate HTML table
$html = '
    <h1 style="text-align: center; color: #333333;">Customer List</h1>
    <table style="width: 100%;">
        <tr style="background-color: #444444; color: #ffffff;">
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
        </tr>';

foreach ($customers as $customer) {
    $statusColor = $customer['status'] === 'Active' ? '#00aa00' : ($customer['status'] === 'Inactive' ? '#ff0000' : '#888888');

    $html .= '
        <tr>
            <td style="text-align: center;">' . $customer['id'] . '</td>
            <td>' . $customer['name'] . '</td>
            <td>' . $customer['email'] . '</td>
            <td style="color: ' . $statusColor . '; font-weight: bold;">' . $customer['status'] . '</td>
        </tr>';
}

$html .= '</table>';
$html .= '<p style="text-align: right; font-size: 10px; margin-top: 20px;">Generated on: ' . date('Y-m-d H:i:s') . '</p>';

// Initialize MiniPDF and render
$minipdf = new MiniPDF($html);
$pdfBinary = $minipdf->render();

// Save to file
file_put_contents(__DIR__ . '/output_customers.pdf', $pdfBinary);

echo "Customer list PDF generated at examples/output_customers.pdf\n";
