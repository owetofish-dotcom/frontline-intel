<?php
/**
 * Minimalny generator PDF bez zależności (czcionki standardowe Helvetica).
 * Wystarcza do raportów tabelarycznych. Polskie znaki transliterowane do
 * zakresu Latin-1 (PDF standardowych fontów nie ma pełnej polszczyzny) —
 * pełne UTF-8 zachowuje eksport CSV.
 */
declare(strict_types=1);

final class Pdf
{
    private array $pages = [];   // każda strona: lista poleceń tekstu/linii (content)
    private string $buf = '';
    private float $w = 595.28;   // A4 szer. (pt)
    private float $h = 841.89;   // A4 wys. (pt)

    public function addPage(): void
    {
        if ($this->buf !== '') {
            $this->pages[] = $this->buf;
        }
        $this->buf = '';
    }

    public function pageWidth(): float  { return $this->w; }
    public function pageHeight(): float { return $this->h; }

    /** Tekst w układzie „od góry": (x, yTop) w punktach. */
    public function text(float $x, float $yTop, string $s, float $size = 10, bool $bold = false): void
    {
        $font = $bold ? '/F2' : '/F1';
        $y = $this->h - $yTop;
        $this->buf .= "BT {$font} {$size} Tf " . $this->n($x) . ' ' . $this->n($y) . " Td (" . self::esc($s) . ") Tj ET\n";
    }

    public function line(float $x1, float $y1, float $x2, float $y2, float $width = 0.5): void
    {
        $this->buf .= $this->n($width) . " w " . $this->n($x1) . ' ' . $this->n($this->h - $y1) . " m "
            . $this->n($x2) . ' ' . $this->n($this->h - $y2) . " l S\n";
    }

    private function n(float $v): string { return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'); }

    /** Konwersja na Latin-1 z transliteracją polskich znaków + escaping PDF. */
    private static function esc(string $s): string
    {
        if (function_exists('iconv')) {
            $conv = @iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $s);
            if ($conv !== false) { $s = $conv; }
        } else {
            $s = strtr($s, [
                'ą'=>'a','ć'=>'c','ę'=>'e','ł'=>'l','ń'=>'n','ó'=>'o','ś'=>'s','ź'=>'z','ż'=>'z',
                'Ą'=>'A','Ć'=>'C','Ę'=>'E','Ł'=>'L','Ń'=>'N','Ó'=>'O','Ś'=>'S','Ź'=>'Z','Ż'=>'Z',
            ]);
        }
        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $s);
    }

    /** Zbuduj dokument PDF jako string. */
    public function output(): string
    {
        $this->addPage(); // domknij bieżącą stronę

        $objs = [];
        // 1 Catalog, 2 Pages, 3 F1, 4 F2, potem strony i contenty
        $pageCount = count($this->pages);
        $firstPageObj = 5;
        $kids = [];
        for ($i = 0; $i < $pageCount; $i++) {
            $kids[] = ($firstPageObj + $i * 2) . ' 0 R';
        }

        $objs[1] = "<</Type /Catalog /Pages 2 0 R>>";
        $objs[2] = "<</Type /Pages /Kids [" . implode(' ', $kids) . "] /Count {$pageCount}>>";
        $objs[3] = "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding>>";
        $objs[4] = "<</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding>>";

        for ($i = 0; $i < $pageCount; $i++) {
            $pageObj    = $firstPageObj + $i * 2;
            $contentObj = $pageObj + 1;
            $mb = "[0 0 " . $this->n($this->w) . ' ' . $this->n($this->h) . "]";
            $objs[$pageObj] = "<</Type /Page /Parent 2 0 R /MediaBox {$mb} "
                . "/Resources <</Font <</F1 3 0 R /F2 4 0 R>>>> /Contents {$contentObj} 0 R>>";
            $stream = $this->pages[$i];
            $objs[$contentObj] = "<</Length " . strlen($stream) . ">>\nstream\n{$stream}\nendstream";
        }

        ksort($objs);
        $maxId = array_key_last($objs);
        $out = "%PDF-1.4\n";
        $offsets = [];
        for ($id = 1; $id <= $maxId; $id++) {
            $offsets[$id] = strlen($out);
            $body = $objs[$id] ?? "<<>>";
            $out .= "{$id} 0 obj\n{$body}\nendobj\n";
        }
        $xrefPos = strlen($out);
        $out .= "xref\n0 " . ($maxId + 1) . "\n0000000000 65535 f \n";
        for ($id = 1; $id <= $maxId; $id++) {
            $out .= str_pad((string) $offsets[$id], 10, '0', STR_PAD_LEFT) . " 00000 n \n";
        }
        $out .= "trailer\n<</Size " . ($maxId + 1) . " /Root 1 0 R>>\nstartxref\n{$xrefPos}\n%%EOF";
        return $out;
    }
}
