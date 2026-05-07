<?php

/**
 * Very small PDF writer for text-only reports.
 * Keeps dependencies out of the project (no composer/vendor).
 */
final class SimplePdf
{
    /** @var list<array{text:string,color:array{int,int,int},size:int}> */
    private array $lines = [];

    public function __construct(private string $title = 'Report')
    {
    }

    /**
     * @param array{int,int,int}|null $rgb Text color as [R,G,B] in 0..255.
     */
    public function addLine(string $line = '', ?array $rgb = null, int $size = 11): void
    {
        $color = $this->normalizeRgb($rgb);
        $size = max(8, min(18, (int)$size));
        $this->lines[] = [
            'text' => $this->escape($this->toWinAnsi($line)),
            'color' => $color,
            'size' => $size,
        ];
    }

    /**
     * Sends a 1-page PDF to the browser.
     */
    public function outputDownload(string $filename): void
    {
        $contentStream = $this->buildContentStream();
        $pdf = $this->buildPdf($contentStream);

        // Prevent "headers already sent" / corrupted PDF due to stray output.
        if (function_exists('ob_get_level')) {
            while (ob_get_level() > 0) {
                @ob_end_clean();
            }
        }
        if (headers_sent()) {
            // Fail gracefully with plain text if we can't send headers.
            echo 'Cannot generate PDF (headers already sent).';
            return;
        }

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    private function buildContentStream(): string
    {
        // Simple single-page text layout (Courier-like).
        $y = 780;
        $chunks = [];
        $chunks[] = "BT\n/F1 11 Tf\n0 0 0 rg\n50 {$y} Td\n";

        // Title
        $chunks[] = "/F1 13 Tf\n" . $this->rgbToPdf([15, 23, 42]) . " rg\n";
        $chunks[] = '(' . $this->escape($this->toWinAnsi($this->title)) . ") Tj\n0 -20 Td\n";

        foreach ($this->lines as $line) {
            $step = max(12, $line['size'] + 3);
            $chunks[] = "/F1 {$line['size']} Tf\n" . $this->rgbToPdf($line['color']) . " rg\n";
            $chunks[] = '(' . $line['text'] . ") Tj\n0 -{$step} Td\n";
        }

        $chunks[] = "ET\n";
        return implode('', $chunks);
    }

    private function escape(string $s): string
    {
        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $s);
    }

    /**
     * @param array{int,int,int} $rgb
     */
    private function rgbToPdf(array $rgb): string
    {
        $r = number_format($rgb[0] / 255, 3, '.', '');
        $g = number_format($rgb[1] / 255, 3, '.', '');
        $b = number_format($rgb[2] / 255, 3, '.', '');
        return "{$r} {$g} {$b}";
    }

    /**
     * @param array{int,int,int}|null $rgb
     * @return array{int,int,int}
     */
    private function normalizeRgb(?array $rgb): array
    {
        if (!is_array($rgb) || count($rgb) !== 3) {
            return [0, 0, 0];
        }

        return [
            max(0, min(255, (int)$rgb[0])),
            max(0, min(255, (int)$rgb[1])),
            max(0, min(255, (int)$rgb[2])),
        ];
    }

    /**
     * Best-effort conversion to WinAnsi-friendly bytes for Type1 fonts.
     * Non-representable chars get transliterated or replaced.
     */
    private function toWinAnsi(string $s): string
    {
        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
            if (is_string($converted) && $converted !== '') {
                return $converted;
            }
        }
        // Fallback: replace non-ASCII
        return preg_replace('/[^\x09\x0A\x0D\x20-\x7E]/', '?', $s) ?? $s;
    }

    private function buildPdf(string $contentStream): string
    {
        // Minimal PDF with 5 objects: catalog, pages, page, font, content.
        $objects = [];

        $objects[] = "<< /Type /Catalog /Pages 2 0 R >>";
        $objects[] = "<< /Type /Pages /Kids [3 0 R] /Count 1 >>";
        $objects[] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>";
        $objects[] = "<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>";
        $objects[] = "<< /Length " . strlen($contentStream) . " >>\nstream\n" . $contentStream . "endstream";

        $offsets = [];
        $pdf = "%PDF-1.4\n";
        foreach ($objects as $i => $obj) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $obj . "\nendobj\n";
        }

        $xrefPos = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= str_pad((string)$offsets[$i], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefPos}\n%%EOF";
        return $pdf;
    }
}

