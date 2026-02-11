<?php

require_once __DIR__ . '/../src/MiniPDF.php';

use MiniPDF\MiniPDF;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $html = $_POST['html'] ?? '';

    if (empty($html)) {
        http_response_code(400);
        echo "HTML content is required";
        exit;
    }

    // Basic server-side sanitization (MiniPDF uses DOMDocument which is already somewhat robust)
    $allowedTags = '<h1><div><p><b><strong><span><i><u>';
    $cleanHtml = strip_tags($html, $allowedTags);

    $minipdf = new MiniPDF($cleanHtml);
    $minipdf->stream('document.pdf');
} else {
    http_response_code(405);
    echo "Method Not Allowed";
}
