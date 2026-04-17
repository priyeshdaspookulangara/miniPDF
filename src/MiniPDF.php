<?php

namespace MiniPDF;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

class MiniPDF
{
    private string $html;
    private array $objects = [];
    private array $offsets = [];
    private array $images = [];
    private int $nextObjectId = 1;

    private float $pageWidth = 595.28;  // A4 Width at 72 DPI
    private float $pageHeight = 841.89; // A4 Height at 72 DPI
    private float $margin = 50.0;
    private float $currentX;
    private float $currentY;

    public function __construct(string $html)
    {
        $this->html = $html;
    }

    public function render(): string
    {
        $this->objects = [];
        $this->offsets = [];
        $this->nextObjectId = 1;

        $catalogId = $this->reserveObjectId();
        $pagesId = $this->reserveObjectId();
        $pageId = $this->reserveObjectId();
        $contentsId = $this->reserveObjectId();
        $fontRegularId = $this->reserveObjectId();
        $fontBoldId = $this->reserveObjectId();
        $fontItalicId = $this->reserveObjectId();
        $fontBoldItalicId = $this->reserveObjectId();

        $this->currentY = $this->pageHeight - $this->margin;
        $this->currentX = $this->margin;
        $this->images = [];

        $stream = $this->generateContentStream();

        $xobjects = "";
        foreach ($this->images as $name => $img) {
            $xobjects .= "/$name {$img['id']} 0 R ";
        }

        $this->addObject($catalogId, "<< /Type /Catalog /Pages $pagesId 0 R >>");
        $this->addObject($pagesId, "<< /Type /Pages /Kids [$pageId 0 R] /Count 1 >>");
        $this->addObject($pageId, "<< /Type /Page /Parent $pagesId 0 R /Resources << /Font << /F1 $fontRegularId 0 R /F2 $fontBoldId 0 R /F3 $fontItalicId 0 R /F4 $fontBoldItalicId 0 R >> /XObject << $xobjects >> >> /MediaBox [0 0 $this->pageWidth $this->pageHeight] /Contents $contentsId 0 R >>");
        $this->addObject($contentsId, "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "\nendstream");
        $this->addObject($fontRegularId, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>");
        $this->addObject($fontBoldId, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >>");
        $this->addObject($fontItalicId, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Oblique >>");
        $this->addObject($fontBoldItalicId, "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-BoldOblique >>");

        return $this->assemblePdf();
    }

    public function stream(string $filename = 'document.pdf'): void
    {
        $pdf = $this->render();
        if (!headers_sent()) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            header('Content-Length: ' . strlen($pdf));
        }
        echo $pdf;
    }

    private function reserveObjectId(): int
    {
        return $this->nextObjectId++;
    }

    private function addObject(int $id, string $content): void
    {
        $this->objects[$id] = $content;
    }

    private function assemblePdf(): string
    {
        $output = "%PDF-1.7\n";
        $output .= "%\xE2\xE3\xCF\xD3\n";

        ksort($this->objects);
        foreach ($this->objects as $id => $content) {
            $this->offsets[$id] = strlen($output);
            $output .= "$id 0 obj\n$content\nendobj\n";
        }

        $xrefOffset = strlen($output);
        $output .= "xref\n";
        $output .= "0 " . (count($this->objects) + 1) . "\n";
        $output .= "0000000000 65535 f \n";
        foreach ($this->offsets as $offset) {
            $output .= sprintf("%010d 00000 n \n", $offset);
        }

        $output .= "trailer\n";
        $output .= "<< /Size " . (count($this->objects) + 1) . " /Root 1 0 R >>\n";
        $output .= "startxref\n$xrefOffset\n%%EOF\n";

        return $output;
    }

    private function generateContentStream(): string
    {
        $dom = new DOMDocument();
        @$dom->loadHTML('<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>' . $this->html . '</body></html>');

        $body = $dom->getElementsByTagName('body')->item(0);

        $content = "";
        if ($body) {
            foreach ($body->childNodes as $child) {
                $content .= $this->renderNode($child);
            }
        }

        return $content;
    }

    private function renderNode(DOMNode $node, array $parentStyles = []): string
    {
        if ($node instanceof DOMText) {
            $text = preg_replace('/\s+/', ' ', $node->textContent);
            if (trim($text) === "" && !in_array($node->parentNode->nodeName, ['p', 'div', 'h1', 'h2', 'h3'])) return "";
            return $this->appendText($text, $parentStyles);
        }

        if ($node instanceof DOMElement) {
            $styles = $this->parseInlineStyles($node->getAttribute('style'));
            $currentStyles = array_merge($parentStyles, $styles);

            $tagName = strtolower($node->nodeName);

            if ($tagName === 'table') {
                return $this->renderTable($node, $currentStyles);
            }

            if ($tagName === 'h1') {
                $currentStyles['font-size'] = $styles['font-size'] ?? '24';
                $currentStyles['font-weight'] = $styles['font-weight'] ?? 'bold';
            } elseif ($tagName === 'h2') {
                $currentStyles['font-size'] = $styles['font-size'] ?? '20';
                $currentStyles['font-weight'] = $styles['font-weight'] ?? 'bold';
            } elseif ($tagName === 'h3') {
                $currentStyles['font-size'] = $styles['font-size'] ?? '18';
                $currentStyles['font-weight'] = $styles['font-weight'] ?? 'bold';
            } elseif ($tagName === 'b' || $tagName === 'strong') {
                $currentStyles['font-weight'] = 'bold';
            } elseif ($tagName === 'i' || $tagName === 'em') {
                $currentStyles['font-style'] = 'italic';
            } elseif ($tagName === 'u') {
                $currentStyles['text-decoration'] = 'underline';
            } elseif ($tagName === 'br') {
                $fontSize = (float)($currentStyles['font-size'] ?? 12);
                $this->currentY -= $fontSize * 1.2;
                $this->currentX = ($currentStyles['startX'] ?? $this->margin) + ($currentStyles['indent'] ?? 0);
                return "";
            } elseif ($tagName === 'td' || $tagName === 'th') {
                $pdfContent = "";
                foreach ($node->childNodes as $child) {
                    $pdfContent .= $this->renderNode($child, $currentStyles);
                }
                return $pdfContent;
            } elseif ($tagName === 'img') {
                $src = $node->getAttribute('src');
                $width = (float)($styles['width'] ?? $node->getAttribute('width') ?: 100);
                $height = (float)($styles['height'] ?? $node->getAttribute('height') ?: 100);

                if (str_starts_with($src, 'data:image/png;base64,')) {
                    $data = base64_decode(substr($src, 22));
                    $img = $this->processPng($data);
                    if ($img) {
                        $this->images[$img['name']] = $img;
                        $out = sprintf("q\n%.2f 0 0 %.2f %.2f %.2f cm\n/%s Do\nQ\n", $width, $height, $this->currentX, $this->currentY - $height, $img['name']);
                        $this->currentX += $width;
                        return $out;
                    }
                } elseif (str_starts_with($src, 'data:image/svg+xml;base64,')) {
                    $data = base64_decode(substr($src, 26));
                    $out = $this->processSvg($data, $width, $height);
                    $this->currentX += $width;
                    return $out;
                } elseif (str_starts_with($src, '<svg')) {
                    $out = $this->processSvg($src, $width, $height);
                    $this->currentX += $width;
                    return $out;
                }
                return "";
            }

            $isBlock = in_array($tagName, ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'li']);

            $pdfContent = "";
            if ($isBlock) {
                $fontSize = (float)($currentStyles['font-size'] ?? 12);
                $this->currentY -= $fontSize * 1.5;
                $this->currentX = $this->margin;


                if ($tagName === 'li') {
                    $this->currentX += 15;
                    $currentStyles['indent'] = 15;
                    $bulletColor = $this->parseHexColor($currentStyles['color'] ?? '#000000');
                    $pdfContent .= $this->drawTextLine("•", "/F1", $fontSize, $bulletColor[0]/255, $bulletColor[1]/255, $bulletColor[2]/255, "none");
                }

                $textAlign = $currentStyles['text-align'] ?? 'left';
                if ($textAlign !== 'left') {
                    $totalWidth = $this->calculateTotalInlineWidth($node, $currentStyles);
                    if ($textAlign === 'center') {
                        $this->currentX = ($this->pageWidth - $totalWidth) / 2;
                    } elseif ($textAlign === 'right') {
                        $this->currentX = $this->pageWidth - $this->margin - $totalWidth;
                    }
                }
            }

            foreach ($node->childNodes as $child) {
                $pdfContent .= $this->renderNode($child, $currentStyles);
            }

            if ($isBlock) {
                $this->currentX = $this->margin;
                $this->currentY -= 5; // Extra spacing after block

                if (isset($currentStyles['margin-bottom'])) {
                    $this->currentY -= (float)$currentStyles['margin-bottom'];
                }
            }

            return $pdfContent;
        }

        return "";
    }

    private function renderTable(DOMElement $table, array $styles): string
    {
        $rows = [];
        foreach ($table->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->nodeName) === 'tr') {
                $rows[] = $child;
            }
        }

        if (empty($rows)) return "";

        $maxCols = 0;
        foreach ($rows as $row) {
            $cells = 0;
            foreach ($row->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'])) {
                    $cells++;
                }
            }
            $maxCols = max($maxCols, $cells);
        }

        if ($maxCols === 0) return "";

        $colWidth = ($this->pageWidth - 2 * $this->margin) / $maxCols;
        $pdfContent = "";

        foreach ($rows as $row) {
            $startY = $this->currentY;
            $rowMaxY = $startY;
            $currentCellIdx = 0;
            $cellOperators = [];

            foreach ($row->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'])) {
                    $cellTagName = strtolower($child->nodeName);
                    $cellStyles = array_merge($styles, $this->parseInlineStyles($child->getAttribute('style')));
                    if ($cellTagName === 'th') {
                        $cellStyles['font-weight'] = 'bold';
                        $cellStyles['text-align'] = $cellStyles['text-align'] ?? 'center';
                    }

                    $cellStyles['startX'] = $this->margin + ($currentCellIdx * $colWidth);
                    $cellStyles['maxWidth'] = $colWidth;

                    $this->currentX = $cellStyles['startX'] + 5; // Internal padding
                    $this->currentY = $startY - (float)($cellStyles['font-size'] ?? 12) * 1.2;

                    $cellOperators[$currentCellIdx] = $this->renderNode($child, $cellStyles);
                    $rowMaxY = min($rowMaxY, $this->currentY);
                    $currentCellIdx++;
                }
            }

            // Finalize row height and draw borders
            $rowHeight = $startY - $rowMaxY + 5;
            $currentCellIdx = 0;
            foreach ($row->childNodes as $child) {
                if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), ['td', 'th'])) {
                    $x = $this->margin + ($currentCellIdx * $colWidth);
                    $cellStyles = array_merge($styles, $this->parseInlineStyles($child->getAttribute('style')));

                    // Cell background
                    if (isset($cellStyles['background-color'])) {
                        $bgColor = $this->parseHexColor($cellStyles['background-color']);
                        $pdfContent .= sprintf("%.2f %.2f %.2f rg\n", $bgColor[0]/255, $bgColor[1]/255, $bgColor[2]/255);
                        $pdfContent .= sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $rowMaxY - 5, $colWidth, $rowHeight);
                    }

                    // Border
                    $pdfContent .= sprintf("0.5 w\n0 0 0 RG\n");
                    $pdfContent .= sprintf("%.2f %.2f %.2f %.2f re S\n", $x, $rowMaxY - 5, $colWidth, $rowHeight);

                    if (isset($cellOperators[$currentCellIdx])) {
                        $pdfContent .= $cellOperators[$currentCellIdx];
                    }
                    $currentCellIdx++;
                }
            }

            $this->currentY = $rowMaxY - 5;
            $this->currentX = $this->margin;
        }

        return $pdfContent;
    }

    private function calculateTotalInlineWidth(DOMNode $node, array $parentStyles): float
    {
        $width = 0;
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text = preg_replace('/\s+/', ' ', $child->textContent);
                $fontSize = (float)($parentStyles['font-size'] ?? 12);
                $width += strlen($text) * $fontSize * 0.5;
            } elseif ($child instanceof DOMElement) {
                $tagName = strtolower($child->nodeName);
                if (!in_array($tagName, ['div', 'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                    $styles = $this->parseInlineStyles($child->getAttribute('style'));
                    $currentStyles = array_merge($parentStyles, $styles);
                    if ($tagName === 'b' || $tagName === 'strong') $currentStyles['font-weight'] = 'bold';
                    $width += $this->calculateTotalInlineWidth($child, $currentStyles);
                }
            }
        }
        return $width;
    }

    private function appendText(string $text, array $styles): string
    {
        $fontSize = (float)($styles['font-size'] ?? 12);
        $colorHex = $styles['color'] ?? '#000000';
        $fontWeight = $styles['font-weight'] ?? 'normal';
        $fontStyle = $styles['font-style'] ?? 'normal';
        $textDecoration = $styles['text-decoration'] ?? 'none';

        $color = $this->parseHexColor($colorHex);
        $r = $color[0] / 255;
        $g = $color[1] / 255;
        $b = $color[2] / 255;

        $fontKey = '/F1';
        if ($fontWeight === 'bold' && $fontStyle === 'italic') {
            $fontKey = '/F4';
        } elseif ($fontWeight === 'bold') {
            $fontKey = '/F2';
        } elseif ($fontStyle === 'italic') {
            $fontKey = '/F3';
        }

        $out = "";

        if (isset($styles['background-color'])) {
            $bgColor = $this->parseHexColor($styles['background-color']);
            $br = $bgColor[0] / 255;
            $bg = $bgColor[1] / 255;
            $bb = $bgColor[2] / 255;
            $out .= sprintf("q\n%.2f %.2f %.2f rg\n", $br, $bg, $bb);
        }

        $startX = $styles['startX'] ?? $this->margin;
        $maxWidth = $styles['maxWidth'] ?? ($this->pageWidth - 2 * $this->margin);
        $rightBoundary = $startX + $maxWidth;

        $words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($words as $word) {
            if ($word === '') continue;

            $wordWidth = strlen($word) * $fontSize * 0.5;

            if ($this->currentX + $wordWidth > $rightBoundary - 5 && !ctype_space($word)) {
                $this->currentY -= $fontSize * 1.2;
                $this->currentX = $startX + ($styles['indent'] ?? 0);
                if ($styles['startX'] ?? false) $this->currentX += 5; // Cell padding
            }

            // Skip leading whitespace on new lines
            $indent = $styles['indent'] ?? 0;
            $baseX = $startX + $indent;
            if ($styles['startX'] ?? false) $baseX += 5; // Cell padding

            if (ctype_space($word) && $this->currentX <= $baseX) {
                continue;
            }

            if (!ctype_space($word) || $this->currentX > $baseX) {
                if (isset($styles['background-color'])) {
                    $bgColor = $this->parseHexColor($styles['background-color']);
                    $out .= sprintf("%.2f %.2f %.2f rg\n", $bgColor[0]/255, $bgColor[1]/255, $bgColor[2]/255);
                    $out .= sprintf("%.2f %.2f %.2f %.2f re f\n", $this->currentX, $this->currentY - $fontSize * 0.2, $wordWidth, $fontSize * 1.2);
                }
                $out .= $this->drawTextLine($word, $fontKey, $fontSize, $r, $g, $b, $textDecoration);
            }
        }

        if (isset($styles['background-color'])) {
            $out .= "Q\n";
        }

        return $out;
    }

    private function drawTextLine(string $text, string $fontKey, float $fontSize, float $r, float $g, float $b, string $textDecoration): string
    {
        $escapedText = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $out = "BT\n";
        $out .= "$fontKey $fontSize Tf\n";
        $out .= sprintf("%.2f %.2f %.2f rg\n", $r, $g, $b);
        $out .= sprintf("%.2f %.2f Td\n", $this->currentX, $this->currentY);
        $out .= "($escapedText) Tj\n";
        $out .= "ET\n";

        $textWidth = strlen($text) * $fontSize * 0.5;

        if ($textDecoration === 'underline' && !ctype_space($text)) {
            $out .= sprintf("%.2f %.2f %.2f RG\n", $r, $g, $b);
            $out .= sprintf("%.2f w\n", $fontSize * 0.05);
            $out .= sprintf("%.2f %.2f m\n", $this->currentX, $this->currentY - 1);
            $out .= sprintf("%.2f %.2f l\n", $this->currentX + $textWidth, $this->currentY - 1);
            $out .= "S\n";
        }

        $this->currentX += $textWidth;

        return $out;
    }

    private function parseInlineStyles(string $styleString): array
    {
        $styles = [];
        if (empty($styleString)) return $styles;

        $parts = explode(';', $styleString);
        foreach ($parts as $part) {
            if (str_contains($part, ':')) {
                list($key, $value) = explode(':', $part, 2);
                $styles[trim(strtolower($key))] = trim($value);
            }
        }
        return $styles;
    }

    private function parseHexColor(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) == 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) != 6) return [0, 0, 0];

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2))
        ];
    }

    private function processSvg(string $data, float $targetWidth = 100, float $targetHeight = 100): string
    {
        $dom = new DOMDocument();
        if (!@$dom->loadXML($data)) return "";

        $svg = $dom->getElementsByTagName('svg')->item(0);
        if (!$svg) return "";

        $viewBox = $svg->getAttribute('viewBox');
        $vbWidth = (float)$svg->getAttribute('width') ?: 100;
        $vbHeight = (float)$svg->getAttribute('height') ?: 100;

        if ($viewBox) {
            $parts = preg_split('/[\s,]+/', trim($viewBox));
            if (count($parts) === 4) {
                $vbWidth = (float)$parts[2];
                $vbHeight = (float)$parts[3];
            }
        }

        $scaleX = $targetWidth / ($vbWidth ?: 1);
        $scaleY = $targetHeight / ($vbHeight ?: 1);

        $out = "q\n";
        $out .= sprintf("%.2f 0 0 %.2f %.2f %.2f cm\n", $scaleX, -$scaleY, $this->currentX, $this->currentY);

        foreach ($svg->childNodes as $node) {
            if ($node instanceof DOMElement) {
                $tagName = strtolower($node->nodeName);
                if ($tagName === 'rect') {
                    $x = (float)$node->getAttribute('x');
                    $y = (float)$node->getAttribute('y');
                    $w = (float)$node->getAttribute('width');
                    $h = (float)$node->getAttribute('height');
                    $fill = $node->getAttribute('fill') ?: '#000000';
                    $color = $this->parseHexColor($fill);
                    $out .= sprintf("%.2f %.2f %.2f rg\n", $color[0]/255, $color[1]/255, $color[2]/255);
                    $out .= sprintf("%.2f %.2f %.2f %.2f re f\n", $x, $y, $w, $h);
                } elseif ($tagName === 'circle') {
                    $cx = (float)$node->getAttribute('cx');
                    $cy = (float)$node->getAttribute('cy');
                    $r = (float)$node->getAttribute('r');
                    $fill = $node->getAttribute('fill') ?: '#000000';
                    $color = $this->parseHexColor($fill);
                    $out .= sprintf("%.2f %.2f %.2f rg\n", $color[0]/255, $color[1]/255, $color[2]/255);
                    // Approximate circle with 4 bezier curves
                    $k = 0.552284749831 * $r;
                    $out .= sprintf("%.2f %.2f m\n", $cx + $r, $cy);
                    $out .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r);
                    $out .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy);
                    $out .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c\n", $cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r);
                    $out .= sprintf("%.2f %.2f %.2f %.2f %.2f %.2f c f\n", $cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy);
                }
            }
        }

        $out .= "Q\n";
        return $out;
    }

    private function processPng(string $data): ?array
    {
        if (substr($data, 0, 8) !== "\x89PNG\r\n\x1a\n") return null;

        $pos = 8;
        $width = 0;
        $height = 0;
        $colorType = 0;
        $idat = "";

        while ($pos < strlen($data)) {
            $len = unpack('N', substr($data, $pos, 4))[1];
            $type = substr($data, $pos + 4, 4);
            $pos += 8;

            if ($type === 'IHDR') {
                $width = unpack('N', substr($data, $pos, 4))[1];
                $height = unpack('N', substr($data, $pos + 4, 4))[1];
                $colorType = ord($data[$pos + 9]);
            } elseif ($type === 'IDAT') {
                $idat .= substr($data, $pos, $len);
            } elseif ($type === 'IEND') {
                break;
            }

            $pos += $len + 4; // Skip data + CRC
        }

        if ($width === 0 || $height === 0) return null;

        $id = $this->reserveObjectId();
        $name = "Img" . count($this->images);

        $colorSpace = ($colorType === 2 || $colorType === 6) ? '/DeviceRGB' : '/DeviceGray';
        $bpc = 8;

        $this->addObject($id, "<< /Type /XObject /Subtype /Image /Width $width /Height $height /ColorSpace $colorSpace /BitsPerComponent $bpc /Filter /FlateDecode /Length " . strlen($idat) . " >>\nstream\n" . $idat . "\nendstream");

        return [
            'id' => $id,
            'name' => $name,
            'width' => (float)$width,
            'height' => (float)$height
        ];
    }
}
