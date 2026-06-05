<?php
// tdf.php - TheDraw .TDF font parser (PHP port of tdfrender.py).
// Spec: https://www.roysac.com/blog/2014/04/thedraw-fonts-file-tdf-specifications/

const TDF_NUM_CHARS = 94;     // ASC(33)='!' .. ASC(126)='~'
const TDF_FIRST_CHAR = 33;
const TDF_HEADER_AFTER_MARKER = 213;
const TDF_MARKER = "\x55\xAA\x00\xFF";

const TDF_TYPE_NAMES = [0 => 'Outline', 1 => 'Block', 2 => 'Color'];

function tdf_outline_map(int $b): int
{
    static $m = [
        65 => 205, 66 => 196, 67 => 179, 68 => 186, 69 => 213, 70 => 187,
        71 => 214, 72 => 191, 73 => 200, 74 => 190, 75 => 192, 76 => 189,
        77 => 181, 78 => 199, 79 => 247, 64 => 32,
    ];
    return $m[$b] ?? $b;
}

function tdf_parse(string $path): ?array
{
    $data = @file_get_contents($path);
    if ($data === false) return null;
    if (substr($data, 0, 19) !== "\x13TheDraw FONTS file") return null;

    $len = strlen($data);
    $fonts = [];
    $pos = strpos($data, TDF_MARKER, 0);
    while ($pos !== false) {
        [$font, $next] = tdf_parse_one($data, $pos, $len);
        if ($font !== null) $fonts[] = $font;
        if ($next === null || $next >= $len) break;
        $pos = strpos($data, TDF_MARKER, $next);
    }
    return $fonts;
}

function tdf_parse_one(string $data, int $markerPos, int $len): array
{
    $base = $markerPos + 4;
    if ($base + TDF_HEADER_AFTER_MARKER - 4 > $len) return [null, null];

    $nameLen = min(ord($data[$base]), 12);
    $raw = substr($data, $base + 1, 12);
    $n = substr($raw, 0, $nameLen);
    $z = strpos($n, "\x00");
    if ($z !== false) $n = substr($n, 0, $z);
    $name = trim($n);
    if ($name === '') {
        $z = strpos($raw, "\x00");
        $n2 = $z !== false ? substr($raw, 0, $z) : $raw;
        $name = trim($n2);
    }

    $type = ord($data[$base + 17]);
    $spacing = ord($data[$base + 18]);
    $blockSize = ord($data[$base + 19]) | (ord($data[$base + 20]) << 8);
    $offStart = $base + 21;
    $dataBlock = $markerPos + TDF_HEADER_AFTER_MARKER;

    $glyphs = [];
    for ($i = 0; $i < TDF_NUM_CHARS; $i++) {
        $off = ord($data[$offStart + $i * 2]) | (ord($data[$offStart + $i * 2 + 1]) << 8);
        if ($off === 0xFFFF) continue;
        $g = tdf_parse_glyph($data, $dataBlock + $off, $type, $len);
        if ($g !== null) $glyphs[TDF_FIRST_CHAR + $i] = $g;
    }

    $font = [
        'name' => $name,
        'type' => $type,
        'spacing' => $spacing,
        'glyphs' => $glyphs,
    ];
    return [$font, $dataBlock + $blockSize];
}

function tdf_parse_glyph(string $data, int $start, int $type, int $len): ?array
{
    if ($start + 2 > $len) return null;
    $w = ord($data[$start]);
    $h = ord($data[$start + 1]);
    $i = $start + 2;
    $rows = [[]];
    while ($i < $len) {
        $b = ord($data[$i]);
        if ($b === 0x00) break;
        if ($b === 0x0D) { $rows[] = []; $i++; continue; }
        if ($type === 2) {
            $attr = ($i + 1 < $len) ? ord($data[$i + 1]) : 0;
            $i += 2;
            $rows[count($rows) - 1][] = [$b, $attr];
        } else {
            $i++;
            if ($type === 0) {
                if ($b === 38) continue;
                $ch = tdf_outline_map($b);
            } else {
                $ch = $b;
            }
            $rows[count($rows) - 1][] = [$ch, null];
        }
    }
    if (count($rows) && count($rows[count($rows) - 1]) === 0) array_pop($rows);

    $effH = max(count($rows), $h);
    $effW = $w;
    foreach ($rows as $r) $effW = max($effW, count($r));
    return ['w' => $effW, 'h' => $effH, 'rows' => $rows];
}

function tdf_metrics(string $path): array
{
    $fonts = tdf_parse($path);
    if (!$fonts) return [0, 0, 0, 99];
    $f0 = $fonts[0];
    $h = 0; $w = 0;
    foreach ($f0['glyphs'] as $g) {
        if ($g['h'] > $h) $h = $g['h'];
        if ($g['w'] > $w) $w = $g['w'];
    }
    return [$h, $w, count($fonts), $f0['type']];
}

// ---- ANSI rendering ------------------------------------------------------

// DOS attribute byte -> ANSI SGR escape (bright fg uses the 90-97 codes).
function tdf_ansi_attr(int $attr): string
{
    static $map = [0, 4, 2, 6, 1, 5, 3, 7];
    $fg = $attr & 0x0F;
    $bg = ($attr >> 4) & 0x07;
    $codes = ['0'];
    $codes[] = ($fg < 8) ? (string)(30 + $map[$fg]) : (string)(90 + $map[$fg - 8]);
    $codes[] = (string)(40 + $map[$bg]);
    return "\x1b[" . implode(';', $codes) . 'm';
}

// CP437 byte -> UTF-8 character.
function tdf_cp437(int $code): string
{
    $c = @iconv('CP437', 'UTF-8', chr($code));
    return ($c === false) ? '?' : $c;
}

// Build the cell grid for a word (PHP port of render_cells / buildGrid).
function tdf_build_grid(array $font, string $text, int $sw, int $gap): array
{
    $items = [];
    $chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    foreach ($chars as $ch) {
        if ($ch === ' ') { $items[] = [$sw, []]; continue; }
        $code = function_exists('mb_ord') ? mb_ord($ch, 'UTF-8') : ord($ch);
        $g = $font['glyphs'][$code] ?? null;
        if (!$g) { $items[] = [$sw, []]; continue; }
        $items[] = [$g['w'], $g['rows']];
    }
    if (!$items) return [];
    $lineH = 1;
    foreach ($items as $it) $lineH = max($lineH, count($it[1]));
    $grid = [];
    for ($r = 0; $r < $lineH; $r++) {
        $line = [];
        foreach ($items as $idx => $it) {
            [$w, $rows] = $it;
            $row = $r < count($rows) ? $rows[$r] : [];
            for ($c = 0; $c < $w; $c++) $line[] = $c < count($row) ? $row[$c] : null;
            if ($gap > 0 && $idx < count($items) - 1)
                for ($k = 0; $k < $gap; $k++) $line[] = null;
        }
        $grid[] = $line;
    }
    return $grid;
}

// Render a word to an ANSI text string (UTF-8 + SGR colour escapes).
function tdf_render_ansi(array $font, string $text, int $sw, int $gap, int $defFg, bool $color): string
{
    $grid = tdf_build_grid($font, $text, $sw, $gap);
    $out = [];
    foreach ($grid as $line) {
        $buf = '';
        $last = null;
        foreach ($line as $cell) {
            if ($cell === null) {                       // transparent cell
                if ($color && $last !== null) { $buf .= "\x1b[0m"; $last = null; }
                $buf .= ' ';
                continue;
            }
            $code = $cell[0];
            $attr = $cell[1];
            if ($attr === null) $attr = $defFg & 0x0F;  // block/outline -> chosen fg
            if ($color && $attr !== $last) { $buf .= tdf_ansi_attr($attr); $last = $attr; }
            $buf .= tdf_cp437($code);
        }
        if ($color && $last !== null) $buf .= "\x1b[0m";
        $out[] = $buf;
    }
    $res = implode("\n", $out);
    if ($color) $res .= "\x1b[0m";
    return $res . "\n";
}
