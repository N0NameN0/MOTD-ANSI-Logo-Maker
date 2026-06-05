<?php
// api.php - JSON / ANSI API for the MOTD ANSI Logo Maker.
require __DIR__ . '/tdf.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');   // no MIME sniffing

$FONTS_DIR = __DIR__ . '/fonts';
// Cache in the system temp dir so it works without making the app directory
// writable by the web-server user. Unique per install; rebuilt when the font
// count changes. Falls back to the app dir if no temp dir is available.
$tmp = sys_get_temp_dir();
$METRICS_CACHE = ($tmp && is_writable($tmp))
    ? rtrim($tmp, '/') . '/malm_metrics_' . md5($FONTS_DIR) . '.json'
    : __DIR__ . '/metrics.json';

function fail(int $code, string $msg): void
{
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function safe_font_path(string $FONTS_DIR, string $name): string
{
    $name = basename($name);
    // strict whitelist; the D modifier stops a trailing newline matching the end anchor
    if (!preg_match('/^[A-Za-z0-9._-]+\.tdf$/iD', $name)) fail(400, 'bad file name');
    $path = $FONTS_DIR . '/' . $name;
    if (!is_file($path)) fail(404, 'font not found');
    return $path;
}

function list_font_files(string $FONTS_DIR): array
{
    $files = [];
    foreach (scandir($FONTS_DIR) ?: [] as $f) {
        if (preg_match('/\.tdf$/i', $f)) $files[] = $f;
    }
    sort($files, SORT_NATURAL | SORT_FLAG_CASE);
    return $files;
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    echo json_encode(list_font_files($FONTS_DIR));
    exit;
}

if ($action === 'metrics') {
    $files = list_font_files($FONTS_DIR);
    $refresh = isset($_GET['refresh']);
    // Use the cache unless forced, or unless the number of fonts changed.
    if (!$refresh && is_file($METRICS_CACHE)) {
        $cached = file_get_contents($METRICS_CACHE);
        if ($cached !== false) {
            $obj = json_decode($cached, true);
            if (is_array($obj) && count($obj) === count($files)) {
                echo $cached;
                exit;
            }
        }
    }
    $out = [];
    foreach ($files as $f) {
        $out[$f] = tdf_metrics($FONTS_DIR . '/' . $f);
    }
    $json = json_encode($out);
    @file_put_contents($METRICS_CACHE, $json);
    echo $json;
    exit;
}

if ($action === 'font') {
    $path = safe_font_path($FONTS_DIR, $_GET['file'] ?? '');
    $fonts = tdf_parse($path);
    if ($fonts === null) fail(422, 'parse error');
    $out = ['file' => basename($path), 'fonts' => []];
    foreach ($fonts as $f) {
        $out['fonts'][] = [
            'name' => $f['name'],
            'type' => $f['type'],
            'typeName' => TDF_TYPE_NAMES[$f['type']] ?? (string)$f['type'],
            'spacing' => $f['spacing'],
            'glyphs' => $f['glyphs'],
        ];
    }
    echo json_encode($out);
    exit;
}

if ($action === 'ansi') {
    $path = safe_font_path($FONTS_DIR, $_GET['file'] ?? '');
    $fonts = tdf_parse($path);
    if ($fonts === null) fail(422, 'parse error');
    $var = (int)($_GET['var'] ?? 0);
    $var = max(0, min($var, count($fonts) - 1));
    $font = $fonts[$var];
    // bound every numeric input and cap text length to prevent a huge-grid DoS
    $text = (string)($_GET['text'] ?? 'HELLO');
    if (mb_strlen($text, 'UTF-8') > 256) $text = mb_substr($text, 0, 256, 'UTF-8');
    $sw    = min(100, max(0, (int)($_GET['space'] ?? 6)));
    $gap   = min(100, max(0, (int)($_GET['gap'] ?? 1)));
    $defFg = max(0, min(15, (int)($_GET['fg'] ?? 7)));
    $color = (($_GET['color'] ?? '1') !== '0');

    header('Content-Type: text/plain; charset=utf-8');
    echo tdf_render_ansi($font, $text, $sw, $gap, $defFg, $color);
    exit;
}

fail(400, 'unknown action');
