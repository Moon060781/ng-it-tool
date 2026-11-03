<?php
/**
 * VO/fish_tts.php - Fish Audio TTS proxy (key stays on the server).
 * Config (never in git, outside web root): /home/<user>/cred/it-VO.env
 *   fallbacks: /home/<user>/vo.env, VO/.env
 *   FISH_API_KEY=...            (required)
 *   FISH_REFERENCE_ID=...       (cloned voice id, 32 hex chars)
 *   FISH_MODEL=s2.1-pro-free    (optional)
 */
define('FISH_PROXY', true);
header('X-Content-Type-Options: nosniff');

function fail($code, $msg) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
}

function load_env(array $paths) {
    foreach ($paths as $p) {
        if (!is_readable($p)) continue;
        $env = [];
        foreach (file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
            list($k, $v) = explode('=', $line, 2);
            $env[trim($k)] = trim(trim($v), "\"'");
        }
        return $env;
    }
    return [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail(405, 'POST only');

// Same-origin check: only our own pages may use this proxy
$src  = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '');
$host = preg_replace('/:\d+$/', '', isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : '');
if ($src === '' || parse_url($src, PHP_URL_HOST) !== $host) fail(403, 'Forbidden');

// Simple rate limit: 40 requests per 10 minutes per IP
$rf = sys_get_temp_dir() . '/vo_fish_' . md5(isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'x');
$now = time();
$hits = [];
if (is_readable($rf)) {
    $old = json_decode((string) file_get_contents($rf), true);
    if (is_array($old)) $hits = array_values(array_filter($old, function ($t) use ($now) { return $t > $now - 600; }));
}
if (count($hits) >= 40) fail(429, 'بہت زیادہ درخواستیں۔ کچھ دیر بعد کوشش کریں۔');
$hits[] = $now;
@file_put_contents($rf, json_encode($hits), LOCK_EX);

$env = load_env([dirname(__DIR__, 2) . '/cred/it-VO.env', dirname(__DIR__, 2) . '/vo.env', __DIR__ . '/.env']);
$key = isset($env['FISH_API_KEY']) ? $env['FISH_API_KEY'] : '';
if ($key === '') fail(500, 'سرور پر FISH_API_KEY نہیں ملی۔ /home/noorgeec/cred/it-VO.env چیک کریں۔');
$model = !empty($env['FISH_MODEL']) ? $env['FISH_MODEL'] : 's2.1-pro-free';

$in   = json_decode((string) file_get_contents('php://input'), true);
$text = is_array($in) && isset($in['text']) ? trim((string) $in['text']) : '';
if ($text === '' || mb_strlen($text, 'UTF-8') > 2000) fail(400, 'متن خالی ہے یا 2000 حروف سے زیادہ ہے۔');

$ref = is_array($in) && !empty($in['reference_id']) ? (string) $in['reference_id'] : (isset($env['FISH_REFERENCE_ID']) ? $env['FISH_REFERENCE_ID'] : '');
if (!preg_match('/^[a-f0-9]{32}$/i', $ref)) fail(400, 'ریفرنس آئی ڈی درست نہیں (32 حروف)۔');

$ch = curl_init('https://api.fish.audio/v1/tts');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 90,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $key, 'Content-Type: application/json', 'model: ' . $model],
    CURLOPT_POSTFIELDS     => json_encode(['text' => $text, 'reference_id' => $ref, 'format' => 'wav'], JSON_UNESCAPED_UNICODE),
]);
$out  = curl_exec($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($out === false) fail(502, 'Fish Audio سے رابطہ نہیں ہو سکا۔');
if ($code !== 200) {
    $map = [401 => 'Fish API key غلط ہے۔', 402 => 'Fish کریڈٹ ختم ہے۔', 429 => 'Fish کی حد پوری، کچھ دیر بعد کوشش کریں۔'];
    fail($code >= 400 ? $code : 502, isset($map[$code]) ? $map[$code] : 'Fish Audio error: HTTP ' . $code);
}
header('Content-Type: audio/wav');
header('Cache-Control: no-store');
echo $out;
