<?php

declare(strict_types=1);

namespace MiniPDF;

/**
 * PDFScanner converts a PDF 1.7 file to structured HTML.
 */
class PDFScanner
{
    /** @var resource|null */
    private $fp;
    private array $xref = [];
    private array $trailer = [];

    /**
     * Converts a PDF file to a structured HTML string.
     *
     * @param string $filePath Path to the PDF file.
     * @return string Structured HTML representation.
     * @throws \RuntimeException if the file cannot be opened or parsed.
     */
    public function convertToHtml(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new \RuntimeException("File not found: $filePath");
        }
        $this->fp = fopen($filePath, 'rb');
        if (!$this->fp) {
            throw new \RuntimeException("Could not open file: $filePath");
        }

        try {
            $this->xref = [];
            $this->trailer = [];
            $this->parseXrefAndTrailer();
            $pages = $this->extractPages();
            $html = "";

            foreach ($pages as $page) {
                $html .= $this->processPage($page);
            }

            return $html;
        } finally {
            if ($this->fp) {
                fclose($this->fp);
            }
        }
    }

    private function parseXrefAndTrailer(): void
    {
        // Find startxref pointer at the end of the file
        fseek($this->fp, 0, SEEK_END);
        $size = ftell($this->fp);
        $readSize = min($size, 2048);
        fseek($this->fp, $size - $readSize);
        $tail = fread($this->fp, $readSize);

        if (!preg_match('/startxref\s+(\d+)/', $tail, $matches)) {
            throw new \RuntimeException("Could not find startxref");
        }

        $xrefOffset = (int)$matches[1];
        fseek($this->fp, $xrefOffset);

        $line = trim(fgets($this->fp));
        if ($line !== 'xref') {
            throw new \RuntimeException("Expected 'xref' at offset $xrefOffset. XRef streams not supported.");
        }

        // Build internal map of object byte-offsets from xref table
        while (!feof($this->fp)) {
            $pos = ftell($this->fp);
            $line = trim(fgets($this->fp));
            if ($line === 'trailer' || $line === '' || str_starts_with($line, '<<')) {
                if ($line !== 'trailer') fseek($this->fp, $pos);
                break;
            }

            $parts = explode(' ', $line);
            if (count($parts) !== 2) continue;

            $startId = (int)$parts[0];
            $count = (int)$parts[1];

            for ($i = 0; $i < $count; $i++) {
                $entry = trim(fgets($this->fp));
                if (empty($entry)) { $i--; continue; }
                $entryParts = preg_split('/\s+/', $entry);
                if (count($entryParts) >= 3 && $entryParts[2] === 'n') {
                    $this->xref[$startId + $i] = (int)$entryParts[0];
                }
            }
        }

        // Identify the Trailer dictionary
        $trailerContent = "";
        while ($line = fgets($this->fp)) {
            $trailerContent .= $line;
            if (str_contains($line, '>>')) break;
        }
        $this->trailer = $this->parseDictionary($trailerContent);
    }

    private function extractPages(): array
    {
        // Identify Root (Catalog) and Pages objects from Trailer
        $rootRef = $this->trailer['Root'] ?? null;
        if (!$rootRef) return [];

        $catalog = $this->resolveObject($rootRef);
        $pagesRef = $catalog['Pages'] ?? null;
        if (!$pagesRef) return [];

        $pagesRoot = $this->resolveObject($pagesRef);
        return $this->getKids($pagesRoot);
    }

    private function getKids(array $node): array
    {
        $kids = [];
        if (isset($node['Kids'])) {
            $refs = $node['Kids'];
            if (!is_array($refs)) $refs = [$refs];

            foreach ($refs as $ref) {
                if (!is_string($ref)) continue;
                $obj = $this->resolveObject($ref);
                if (($obj['Type'] ?? '') === '/Page') {
                    $kids[] = $obj;
                } else {
                    $kids = array_merge($kids, $this->getKids($obj));
                }
            }
        }
        return $kids;
    }

    private function resolveObject(string $ref): array
    {
        if (preg_match('/(\d+)\s+(\d+)\s+R/', $ref, $m)) {
            $id = (int)$m[1];
            if (!isset($this->xref[$id])) return [];

            fseek($this->fp, $this->xref[$id]);
            fgets($this->fp); // Skip header line (n m obj)

            $content = "";
            $streamData = null;
            while ($line = fgets($this->fp)) {
                if (trim($line) === 'endobj') break;
                if (trim($line) === 'stream') {
                    $dict = $this->parseDictionary($content);
                    $length = (int)($dict['Length'] ?? 0);

                    // Consume newline after 'stream' keyword
                    $pos = ftell($this->fp);
                    $c = fread($this->fp, 2);
                    if ($c === "\r\n") { /* OK */ }
                    elseif ($c[0] === "\n" || $c[0] === "\r") fseek($this->fp, $pos + 1);
                    else fseek($this->fp, $pos);

                    $streamData = fread($this->fp, $length);
                    continue;
                }
                $content .= $line;
            }

            $dict = $this->parseDictionary($content);
            if ($streamData !== null) {
                $dict['__stream'] = $streamData;
            }
            return $dict;
        }
        return [];
    }

    private function parseDictionary(string $content): array
    {
        $dict = [];
        $pattern = '/\/([A-Za-z0-9]+)\s+(\/[A-Za-z0-9]+|[^\/<>\[\]]+|<<.*?>>|\[.*?\])/s';
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $i => $key) {
                $val = trim($matches[2][$i]);
                if (str_starts_with($val, '[')) {
                    $inner = trim($val, '[]');
                    preg_match_all('/(\d+\s+\d+\s+R|[^\s]+)/', $inner, $arrayMatches);
                    $dict[$key] = $arrayMatches[1];
                } else {
                    $dict[$key] = $val;
                }
            }
        }
        return $dict;
    }

    private function processPage(array $page): string
    {
        $contentsRef = $page['Contents'] ?? null;
        if (!$contentsRef) return "";

        $contentObjects = is_array($contentsRef) ? $contentsRef : [$contentsRef];
        $fullStream = "";

        foreach ($contentObjects as $ref) {
            if (!is_string($ref)) continue;
            $obj = $this->resolveObject($ref);
            $data = $obj['__stream'] ?? '';

            // Object Stream Decompression (/FlateDecode)
            if (($obj['Filter'] ?? '') === '/FlateDecode') {
                $data = @gzuncompress($data) ?: $data;
            }
            $fullStream .= $data;
        }

        return $this->parseContentStream($fullStream);
    }

    private function parseContentStream(string $stream): string
    {
        $fragments = [];
        $currentX = 0.0;
        $currentY = 0.0;

        // Tokenize PDF content stream to identify BT, ET, Td, Tm, Tj, TJ
        $pattern = '/(?:\[.*?\]|\((?:[^()\\\\]|\\\\.)*\)|-?[\d.]+|BT|ET|[A-Za-z*\'"]+)/s';
        preg_match_all($pattern, $stream, $matches);
        $tokens = $matches[0];

        for ($i = 0; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            if ($token === 'BT') {
                $currentX = 0.0; $currentY = 0.0;
            } elseif ($token === 'Td' && $i >= 2) {
                $currentX += (float)$tokens[$i - 2];
                $currentY += (float)$tokens[$i - 1];
            } elseif ($token === 'Tm' && $i >= 6) {
                $currentX = (float)$tokens[$i - 2];
                $currentY = (float)$tokens[$i - 1];
            } elseif ($token === 'Tj' && $i >= 1) {
                $text = $this->decodeString($tokens[$i - 1]);
                if ($text !== "") {
                    $fragments[] = ['x' => $currentX, 'y' => $currentY, 'text' => $text];
                }
            } elseif ($token === 'TJ' && $i >= 1) {
                $text = $this->decodeTJ($tokens[$i - 1]);
                if ($text !== "") {
                    $fragments[] = ['x' => $currentX, 'y' => $currentY, 'text' => $text];
                }
            }
        }

        if (empty($fragments)) return "";

        // Sort fragments by Y (descending) and X (ascending)
        usort($fragments, function($a, $b) {
            if (abs($a['y'] - $b['y']) < 2.0) return $a['x'] <=> $b['x'];
            return $b['y'] <=> $a['y'];
        });

        // HTML Reconstruction using heuristics
        $html = "";
        $lastY = null;
        $currentLine = "";

        foreach ($fragments as $f) {
            if ($lastY === null) {
                $lastY = $f['y'];
                $currentLine = $f['text'];
            } elseif (abs($f['y'] - $lastY) < 2.0) {
                // If two strings share a similar Y-coordinate (+/- 2pt), treat them as part of the same line
                $currentLine .= " " . $f['text'];
            } else {
                // If a Y-coordinate jump is greater than the font size (heuristic), wrap in <p>
                $html .= "<p>" . htmlspecialchars(trim($currentLine)) . "</p>\n";
                $currentLine = $f['text'];
                $lastY = $f['y'];
            }
        }
        if ($currentLine !== "") {
            $html .= "<p>" . htmlspecialchars(trim($currentLine)) . "</p>\n";
        }

        return $html;
    }

    private function decodeString(string $s): string
    {
        if (str_starts_with($s, '(') && str_ends_with($s, ')')) {
            $s = substr($s, 1, -1);
            // Implement basic character mapping / escape handling
            return str_replace(['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'], ['(', ')', '\\', "\n", "\r", "\t"], $s);
        }
        return "";
    }

    private function decodeTJ(string $tj): string
    {
        if (str_starts_with($tj, '[') && str_ends_with($tj, ']')) {
            $inner = substr($tj, 1, -1);
            // Extract strings from TJ array
            preg_match_all('/\((?:[^()\\\\]|\\\\.)*\)/', $inner, $matches);
            $out = "";
            foreach ($matches[0] as $s) {
                $out .= $this->decodeString($s);
            }
            return $out;
        }
        return "";
    }
}
