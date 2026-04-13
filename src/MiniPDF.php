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

        $stream = $this->generateContentStream();

        $this->addObject($catalogId, "<< /Type /Catalog /Pages $pagesId 0 R >>");
        $this->addObject($pagesId, "<< /Type /Pages /Kids [$pageId 0 R] /Count 1 >>");
        $this->addObject($pageId, "<< /Type /Page /Parent $pagesId 0 R /Resources << /Font << /F1 $fontRegularId 0 R /F2 $fontBoldId 0 R /F3 $fontItalicId 0 R /F4 $fontBoldItalicId 0 R >> >> /MediaBox [0 0 $this->pageWidth $this->pageHeight] /Contents $contentsId 0 R >>");
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
                $this->currentX = $this->margin + ($currentStyles['indent'] ?? 0);
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

        $words = preg_split('/(\s+)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        foreach ($words as $word) {
            if ($word === '') continue;

            $wordWidth = strlen($word) * $fontSize * 0.5;

            if ($this->currentX + $wordWidth > $this->pageWidth - $this->margin && !ctype_space($word)) {
                $this->currentY -= $fontSize * 1.2;
                $this->currentX = $this->margin;
                if (isset($styles['indent'])) {
                    $this->currentX += $styles['indent'];
                }
            }

            // Skip leading whitespace on new lines
            if (ctype_space($word) && $this->currentX === $this->margin + ($styles['indent'] ?? 0)) {
                continue;
            }

            if (!ctype_space($word) || $this->currentX > ($this->margin + ($styles['indent'] ?? 0))) {
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
}
