# MiniPDF

MiniPDF is a lightweight, dependency-free PHP 8.x library for converting HTML and basic CSS into PDF 1.7 files. It manually assembles the PDF binary structure, making it extremely fast and portable.

## Features

- **Zero Dependencies**: Pure PHP implementation without requiring external libraries like wkhtmltopdf or extensions like imagick.
- **HTML5 Parsing**: Uses native `DOMDocument` for robust HTML parsing.
- **Table Support**: Automatic column width and row height calculation, with support for borders and backgrounds.
- **Image Support**: Support for PNG (via data URIs) and SVG (inline or base64 data URIs).
- **CSS Styling**: Support for common inline styles including colors, fonts, alignment, and backgrounds.
- **Word Wrapping**: Intelligent line breaking engine that respects margins and container boundaries.
- **Lightweight**: Minimal memory footprint.

## Installation

Simply include the `src/MiniPDF.php` file in your project:

```php
require_once 'path/to/src/MiniPDF.php';
use MiniPDF\MiniPDF;
```

## Quick Start: Generate a PDF from a PHP Page

Here is a basic example of how to use MiniPDF to generate a PDF and save it to a file.

```php
<?php
require_once 'src/MiniPDF.php';
use MiniPDF\MiniPDF;

$html = '
    <h1 style="color: #003366;">Hello MiniPDF!</h1>
    <p>This is a PDF generated from <b>HTML</b> and <i>CSS</i>.</p>
';

$minipdf = new MiniPDF($html);

// Render the PDF to a binary string
$pdfBinary = $minipdf->render();

// Save to a file
file_put_contents('document.pdf', $pdfBinary);
echo "PDF created successfully!";
```

## Streaming PDF to the Browser

If you want to display the PDF directly in the browser (e.g., when a user clicks a "Download PDF" button), use the `stream()` method.

```php
<?php
require_once 'src/MiniPDF.php';
use MiniPDF\MiniPDF;

$html = '<h1>Report</h1><p>Confidential content...</p>';
$minipdf = new MiniPDF($html);

// This will set the appropriate headers and output the PDF directly
$minipdf->stream('my-report.pdf');
```

## Advanced Features

### Rendering Tables

MiniPDF supports tables with automatic column distribution.

```php
$html = '
    <table style="width: 100%;">
        <tr style="background-color: #f2f2f2;">
            <th>Item</th>
            <th>Quantity</th>
            <th>Price</th>
        </tr>
        <tr>
            <td>Coffee</td>
            <td>2</td>
            <td>$10.00</td>
        </tr>
    </table>
';
```

### Complex Styling and Lists

```php
$html = '
    <div style="background-color: #f0f0f0; margin-bottom: 20px;">
        <h2 style="text-align: center;">Styling Example</h2>
    </div>
    <ul>
        <li><b>Bold</b> and <i>Italic</i> support</li>
        <li style="color: #ff0000;">Colored text</li>
        <li><u>Underlined</u> elements</li>
    </ul>
';
```

## Reference

### Supported HTML Tags

- **Headings**: `<h1>` to `<h6>`
- **Text Layout**: `<p>`, `<div>`, `<span>`, `<br>`
- **Formatting**: `<b>`, `<strong>`, `<i>`, `<em>`, `<u>`
- **Lists**: `<ul>`, `<li>`
- **Tables**: `<table>`, `<tr>`, `<th>`, `<td>`
- **Images**: `<img>` (supports `src` as base64 PNG/SVG or inline SVG)

### Supported CSS Properties (Inline Styles)

- `color`: Hex values (e.g., `#ff0000`)
- `background-color`: Hex values
- `font-size`: Point sizes
- `font-weight`: `bold` or `normal`
- `font-style`: `italic` or `normal`
- `text-decoration`: `underline` or `none`
- `text-align`: `left`, `center`, `right`
- `margin-bottom`: Spacing after block elements

### Fonts

The library includes the standard Helvetica font family:
- Helvetica (Regular)
- Helvetica-Bold
- Helvetica-Oblique (Italic)
- Helvetica-BoldOblique (Bold Italic)

## Running Examples

Explore the `examples/` directory for more implementation ideas:
- `examples/generate_sample.php`: A comprehensive showcase document (**sample.pdf**).
- `examples/test.php`: Simple basic example.
- `examples/test_table.php`: Detailed table layout.
- `examples/test_html_css.php`: Comprehensive styling showcase.
- `examples/generate_customers.php`: Practical example generating a customer list from an array.
