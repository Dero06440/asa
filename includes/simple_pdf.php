<?php

final class SimplePdfDocument
{
    private float $pageWidthPt;
    private float $pageHeightPt;
    private array $pages = [];
    private string $currentContent = '';
    private int $fontSizePt = 12;
    private float $lineHeightPt = 14;

    public function __construct(float $pageWidthMm, float $pageHeightMm)
    {
        $this->pageWidthPt = $this->mmToPt($pageWidthMm);
        $this->pageHeightPt = $this->mmToPt($pageHeightMm);
    }

    public function addPage(): void
    {
        if ($this->currentContent !== '') {
            $this->pages[] = $this->currentContent;
        }
        $this->currentContent = '';
    }

    public function setFontSize(float $fontSizePt, ?float $lineHeightPt = null): void
    {
        $this->fontSizePt = (int) round($fontSizePt);
        $this->lineHeightPt = $lineHeightPt ?? ($fontSizePt + 2);
    }

    public function textBlock(float $xMm, float $yTopMm, array $lines): void
    {
        if ($this->currentContent === '') {
            $this->addPage();
        }

        $xPt = $this->mmToPt($xMm);
        $yPt = $this->pageHeightPt - $this->mmToPt($yTopMm);

        $commands = ['BT', '/F1 ' . $this->fontSizePt . ' Tf'];
        foreach ($lines as $index => $line) {
            $safeLine = $this->encodeText((string) $line);
            $lineY = $yPt - ($index * $this->lineHeightPt);
            $commands[] = sprintf('1 0 0 1 %.2F %.2F Tm', $xPt, $lineY);
            $commands[] = '(' . $safeLine . ') Tj';
        }
        $commands[] = 'ET';

        $this->currentContent .= implode("\n", $commands) . "\n";
    }

    public function output(string $filename): void
    {
        if ($this->currentContent !== '') {
            $this->pages[] = $this->currentContent;
            $this->currentContent = '';
        }

        if (empty($this->pages)) {
            $this->addPage();
            $this->pages[] = $this->currentContent;
            $this->currentContent = '';
        }

        $objects = [];

        $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
        $pageKids = [];
        $nextObjectId = 5;

        foreach ($this->pages as $pageIndex => $content) {
            $contentObjectId = $nextObjectId++;
            $pageObjectId = $nextObjectId++;
            $pageKids[] = $pageObjectId . ' 0 R';

            $stream = $content;
            $objects[$contentObjectId] = "<< /Length " . strlen($stream) . " >>\nstream\n" . $stream . "endstream";
            $objects[$pageObjectId] = sprintf(
                '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 %.2F %.2F] /Resources << /Font << /F1 3 0 R >> >> /Contents %d 0 R >>',
                $this->pageWidthPt,
                $this->pageHeightPt,
                $contentObjectId
            );
        }

        $objects[2] = '<< /Type /Pages /Count ' . count($pageKids) . ' /Kids [' . implode(' ', $pageKids) . '] >>';
        $objects[3] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
        $objects[4] = '<< /Producer (Codex) /Title (' . $this->encodeText($filename) . ') >>';

        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $objectId => $objectBody) {
            $offsets[$objectId] = strlen($pdf);
            $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i] ?? 0);
        }

        $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R /Info 4 0 R >>\n";
        $pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdf));
        echo $pdf;
    }

    private function mmToPt(float $mm): float
    {
        return $mm * 72 / 25.4;
    }

    private function encodeText(string $text): string
    {
        $text = $this->normalizeText($text);
        $encoded = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
        if ($encoded === false) {
            $encoded = @iconv('UTF-8', 'Windows-1252//IGNORE', $text);
        }
        if ($encoded === false) {
            $encoded = '';
        }

        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\\(', '\\)', '', ' '],
            $encoded
        );
    }

    private function normalizeText(string $text): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');
        if ($text === '') {
            return '';
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'Windows-1252,ISO-8859-1');
            if (is_string($converted) && $converted !== '') {
                $text = $converted;
            }
        }

        if (preg_match('/[ÃÂâ€œž™œ]/u', $text) === 1) {
            $repaired = @mb_convert_encoding($text, 'Windows-1252', 'UTF-8');
            if (is_string($repaired) && $repaired !== '' && mb_check_encoding($repaired, 'UTF-8')) {
                $text = $repaired;
            }
        }

        return $text;
    }
}
